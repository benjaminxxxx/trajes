<?php

namespace App\Livewire\Rental;

use App\Models\Rental;
use App\Models\RentalMedia;
use App\Services\Rental\RentalDocumentService;
use App\Services\Rental\RentalService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Detalle de un alquiler. Solo permite editar datos que no afectan las
 * prendas (cliente, fechas, notas); para cambiar prendas se anula el
 * alquiler y se vuelve a registrar.
 */
class RentalDetailModal extends Component
{
    use ManagesDocumentPhotos;

    public ?int $rentalId = null;

    public bool $isOpen = false;

    public ?int $client_id = null;

    public ?string $client_name = null;

    public ?string $pickup_date = null;

    public ?string $expected_return_date = null;

    public ?string $notes = null;

    public string $cancellationReason = '';

    #[On('open-rental-detail')]
    public function loadRental(int $id): void
    {
        $this->resetValidation();
        $this->reset();

        $this->rentalId = $id;
        unset($this->rental);

        $rental = $this->rental;

        $this->client_id = $rental->client_id;
        $this->client_name = $rental->client?->display_name;
        $this->pickup_date = $rental->pickup_date?->format('Y-m-d\TH:i');
        $this->expected_return_date = $rental->expected_return_date?->format('Y-m-d\TH:i');
        $this->notes = $rental->notes;

        $this->isOpen = true;
    }

    #[Computed]
    public function rental(): ?Rental
    {
        if (! $this->rentalId) {
            return null;
        }

        return Rental::with([
            'client:id,display_name,document_type,document_number',
            'user:id,name',
            'cancelledBy:id,name',
            'details.physicalProduct:id,code,status,product_variant_id',
            'details.physicalProduct.variant.product:id,name,code',
            'details.physicalProduct.variant.size:id,name',
            'details.physicalProduct.variant.color:id,name',
            'documents',
        ])->findOrFail($this->rentalId);
    }

    #[On('entity-selected')]
    public function handleEntitySelected(string $context, int $id, string $label): void
    {
        if ($context === 'rental-edit-client') {
            $this->client_id = $id;
            $this->client_name = $label;
        }
    }

    #[On('entity-cleared')]
    public function handleEntityCleared(string $context): void
    {
        if ($context === 'rental-edit-client') {
            $this->client_id = null;
            $this->client_name = null;
        }
    }

    public function save(): void
    {
        $this->authorize('alquileres.editar');
        abort_if(! $this->rental || $this->rental->isCancelled(), 403, 'El alquiler está anulado.');

        $validated = $this->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'pickup_date' => ['required', 'date'],
            'expected_return_date' => ['required', 'date', 'after:pickup_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'client_id.required' => 'Elige un cliente para el alquiler.',
        ]);

        $this->rental->update($validated);

        $this->afterChange('Alquiler actualizado correctamente.');
    }

    /**
     * Adjunta más fotos del documento a un alquiler ya registrado (ej. faltó el reverso).
     */
    public function saveDocuments(RentalDocumentService $documents): void
    {
        $this->authorize('alquileres.registrar');
        abort_unless($this->rental, 404);

        $this->validate($this->documentPhotoRules(required: true), [
            'documentPhotos.required' => 'Toma o elige al menos una foto.',
        ]);

        $documents->attach($this->rental, $this->pendingDocumentPhotos());

        $this->resetDocumentPhotos();
        unset($this->rental);
        Flux::toast(variant: 'success', text: 'Fotos del documento agregadas.');
    }

    public function deleteDocument(int $mediaId, RentalDocumentService $documents): void
    {
        $this->authorize('alquileres.editar');

        $documents->delete(RentalMedia::where('rental_id', $this->rentalId)->findOrFail($mediaId));

        unset($this->rental);
        Flux::toast(variant: 'success', text: 'Foto eliminada.');
    }

    public function cancel(RentalService $service): void
    {
        $this->authorize('alquileres.anular');
        abort_if(! $this->rental?->isCancellable(), 403, 'El alquiler no se puede anular.');

        $this->validate([
            'cancellationReason' => ['required', 'string', 'min:5', 'max:500'],
        ], [], ['cancellationReason' => 'motivo de anulación']);

        try {
            $service->cancel($this->rental, trim($this->cancellationReason));
        } catch (\RuntimeException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::modal('cancel-rental')->close();
        $this->afterChange("Alquiler {$this->rental->code} anulado. Las prendas volvieron a estar disponibles.");
    }

    protected function afterChange(string $message): void
    {
        $this->isOpen = false;
        $this->dispatch('rentalUpdated');
        Flux::toast(variant: 'success', text: $message);
    }

    public function render(): View
    {
        return view('livewire.rental.rental-detail-modal');
    }
}
