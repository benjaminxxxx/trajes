<?php

namespace App\Livewire\Rental;

use App\Livewire\Shared\ManagesCartItems;
use App\Models\Client;
use App\Models\Rental;
use App\Services\Core\AccountRule;
use App\Services\Rental\RentalDocumentService;
use App\Services\Rental\RentalService;
use App\Services\Rental\ReservationService;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class RentalForm extends Component
{
    use ManagesCartItems, ManagesDocumentPhotos;

    public ?int $client_id = null;

    public ?string $client_name = null;

    public ?string $pickup_date = null;

    public ?string $expected_return_date = null;

    public float $deposit_amount = 0;

    public ?string $notes = null;

    public array $items = [];

    public int $clientPickerVersion = 0;

    // Código del alquiler anulado desde el que se precargó el formulario.
    public ?string $redoFromCode = null;

    // Alquiler anulado cuyas fotos del DNI se reutilizan si no se toman nuevas.
    #[Locked]
    public ?int $redoFromId = null;

    public int $reusableDocuments = 0;

    public function mount(): void
    {
        $this->pickup_date = now()->format('Y-m-d\TH:i');
        $this->expected_return_date = now()->addDays(1)->format('Y-m-d\TH:i');

        if ($fromId = request()->integer('from')) {
            $this->prefillFromCancelledRental($fromId);
        }
    }

    /**
     * Precarga cliente y prendas de un alquiler anulado para registrarlo de
     * nuevo con los datos corregidos. Cada detalle es una prenda y vuelve al
     * carrito como una fila con su código.
     */
    protected function prefillFromCancelledRental(int $rentalId): void
    {
        $rental = Rental::with([
            'client:id,display_name',
            'details.physicalProduct.variant.product:id,name,code,category_id',
            'details.physicalProduct.variant.size:id,name',
            'details.physicalProduct.variant.color:id,name',
        ])
            ->where('status', Rental::STATUS_CANCELLED)
            ->find($rentalId);

        if (! $rental) {
            return;
        }

        $this->redoFromCode = $rental->code;
        $this->redoFromId = $rental->id;
        $this->reusableDocuments = $rental->documents()->count();
        $this->client_id = $rental->client_id;
        $this->client_name = $rental->client?->display_name;
        $this->deposit_amount = (float) $rental->deposit_amount;
        $this->notes = $rental->notes;

        foreach ($rental->details as $detail) {
            $unit = $detail->physicalProduct;
            $variant = $unit?->variant;

            if (! $variant?->product) {
                continue;
            }

            // Se propone la misma prenda si sigue disponible; si no, queda la
            // línea por talla/color y el servicio toma otra disponible.
            $isAvailable = $unit->status === 'available';

            $this->addCartItem([
                'product_id' => $variant->product->id,
                'product_name' => $variant->product->name,
                'product_code' => $variant->product->code,
                'category_id' => $variant->product->category_id,
                'size_id' => $variant->size_id,
                'color_id' => $variant->color_id,
                'physical_product_id' => $isAvailable ? $unit->id : null,
                'physical_code' => $isAvailable ? $unit->code : null,
                'variant_label' => $variant->label(),
                'quantity' => 1,
                'unit_price' => (float) $detail->price,
                'discount_percent' => 0,
            ]);
        }
    }

    #[On('product-picker-selected')]
    public function handlePickedItems(string $context, array $items): void
    {
        if ($context === 'rental-gallery') {
            $this->addPickedItems($items);
        }
    }

    #[On('entity-selected')]
    public function handleEntitySelected(string $context, int $id, string $label): void
    {
        if ($context === 'rental-client') {
            $this->client_id = $id;
            $this->client_name = $label;
        }
    }

    /**
     * Cliente recién creado desde el botón "+": se deja seleccionado y se
     * remonta el buscador para que lo muestre.
     */
    #[On('client-created')]
    public function selectCreatedClient(int $clientId): void
    {
        $client = Client::find($clientId);

        if ($client) {
            $this->client_id = $client->id;
            $this->client_name = $client->display_name;
            $this->clientPickerVersion++;
        }
    }

    #[On('entity-cleared')]
    public function handleEntityCleared(string $context): void
    {
        if ($context === 'rental-client') {
            $this->client_id = null;
            $this->client_name = null;
        }
    }

    /**
     * Separa las prendas del carrito para el cliente (hasta la fecha de salida)
     * sin registrar el alquiler. Solo marca: no bloquea venderlas ni alquilarlas.
     */
    public function reserve(ReservationService $reservations): void
    {
        $this->authorize('alquileres.registrar');

        $unitIds = collect($this->items)->pluck('physical_product_id')->filter()->map(fn ($id) => (int) $id)->values()->all();

        $this->validate([
            'client_id' => ['required', 'integer', AccountRule::exists('clients')],
            'pickup_date' => ['required', 'date'],
        ], [
            'client_id.required' => 'Elige para qué cliente se separan las prendas.',
        ]);

        if ($unitIds === []) {
            $this->addError('items', 'Agrega al menos una prenda para separarla.');

            return;
        }

        $count = $reservations->reserve(
            $unitIds,
            Client::findOrFail($this->client_id),
            substr($this->pickup_date, 0, 10),
            $this->notes,
        );

        Flux::toast(variant: 'success', text: "{$count} prenda(s) separadas para {$this->client_name}.");

        $this->reset(['client_id', 'client_name', 'deposit_amount', 'notes', 'items', 'redoFromCode', 'redoFromId', 'reusableDocuments']);
        $this->resetDocumentPhotos();
        $this->pickup_date = now()->format('Y-m-d\TH:i');
        $this->expected_return_date = now()->addDays(1)->format('Y-m-d\TH:i');
        $this->clientPickerVersion++;
    }

    public function save(RentalService $service, RentalDocumentService $documents): void
    {
        $this->authorize('alquileres.registrar');

        $this->validate([
            'client_id' => 'required|integer',
            'pickup_date' => 'required|date',
            'expected_return_date' => 'required|date|after:pickup_date',
            'items' => 'required|array|min:1',
            ...$this->documentPhotoRules(required: $this->reusableDocuments === 0),
        ], [
            'client_id.required' => 'Elige un cliente para el alquiler.',
            'items.required' => 'Agrega al menos una prenda al alquiler.',
            'documentPhotos.required' => 'Toma al menos una foto del DNI del cliente.',
            'documentPhotos.max' => 'Puedes adjuntar hasta 6 fotos.',
        ]);

        try {
            // Alquiler y fotos del DNI van juntos: si una foto falla, no queda el alquiler a medias.
            $rental = DB::transaction(function () use ($service, $documents) {
                $rental = $service->register([
                    'client_id' => $this->client_id,
                    'pickup_date' => $this->pickup_date,
                    'expected_return_date' => $this->expected_return_date,
                    'deposit_amount' => $this->deposit_amount,
                    'notes' => $this->notes,
                ], $this->normalizedLines());

                if ($this->documentPhotos !== []) {
                    $documents->attach($rental, $this->pendingDocumentPhotos());
                } elseif ($this->redoFromId) {
                    $documents->copy(Rental::findOrFail($this->redoFromId), $rental);
                }

                return $rental;
            });

            $this->reset(['client_id', 'client_name', 'deposit_amount', 'notes', 'items', 'redoFromCode', 'redoFromId', 'reusableDocuments']);
            $this->resetDocumentPhotos();
            $this->pickup_date = now()->format('Y-m-d\TH:i');
            $this->expected_return_date = now()->addDays(1)->format('Y-m-d\TH:i');
            $this->clientPickerVersion++;

            Flux::toast(variant: 'success', text: "Alquiler {$rental->code} registrado correctamente.");
            $this->dispatch('rentalSaved', rentalId: $rental->id);

            // Tras registrar se muestra el comprobante listo para compartir.
            $this->redirectRoute('receipts.show', ['type' => 'alquiler', 'id' => $rental->id], navigate: true);
        } catch (\Throwable $th) {
            report($th);
            Flux::toast(variant: 'danger', text: 'No se pudo registrar el alquiler: '.$th->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.rental.rental-form');
    }
}
