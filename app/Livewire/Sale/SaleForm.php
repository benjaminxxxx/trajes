<?php

namespace App\Livewire\Sale;

use App\Livewire\Shared\ManagesCartItems;
use App\Models\Asset;
use App\Models\Client;
use App\Models\PhysicalProduct;
use App\Models\Sale;
use App\Services\Billing\BillingService;
use App\Services\Sale\SaleService;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

class SaleForm extends Component
{
    use ManagesCartItems;

    public ?int $client_id = null;

    public ?string $client_name = null;

    public string $document_type = 'nota_venta';

    public ?string $document_number = null;

    public bool $has_invoice_pending = false;

    public ?string $sale_date = null;

    public ?string $notes = null;

    public array $items = [];

    /**
     * Consumibles a entregar con la venta: asset_id => ['enabled' => bool, 'quantity' => ?int].
     * Sin cantidad elegida se usa la sugerida (una por prenda aplicable).
     *
     * @var array<int, array{enabled?: bool, quantity?: int|string|null}>
     */
    public array $deliveries = [];

    /**
     * Bienes del negocio que se venden en esta venta (los que tienen precio
     * de venta). Precio con IGV; en 0 el bien sale de regalo.
     *
     * @var array<string, array{asset_id: int, name: string, available: int, quantity: int|string, unit_price: float|string}>
     */
    public array $assetLines = [];

    // Selector "agregar bien": al elegir uno se suma al detalle y vuelve a vacío.
    public string $assetToAdd = '';

    // Fuerza a que el buscador de cliente se remonte limpio tras guardar.
    public int $clientPickerVersion = 0;

    // Código de la venta anulada desde la que se precargó el formulario.
    public ?string $redoFromCode = null;

    public function mount(): void
    {
        $this->sale_date = now()->toDateString();

        if ($fromId = request()->integer('from')) {
            $this->prefillFromCancelledSale($fromId);
        }

        if ($unitId = request()->integer('prenda')) {
            $this->prefillPhysicalUnit($unitId);
        }
    }

    /**
     * Precarga una prenda física puntual (ej. el cliente que alquiló un terno
     * decide comprarlo al devolverlo). Solo si está disponible para venta.
     */
    protected function prefillPhysicalUnit(int $unitId): void
    {
        $unit = PhysicalProduct::with(['variant.product', 'variant.size:id,name', 'variant.color:id,name'])
            ->where('status', 'available')
            ->find($unitId);
        $product = $unit?->variant?->product;

        if (! $product?->allow_sale) {
            return;
        }

        $price = (float) ($unit->suggested_sale_price ?? $unit->variant->sale_price ?? $product->price ?? 0);

        $this->addPickedItems([[
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_code' => $product->code,
            'category_id' => $product->category_id,
            'size_id' => $unit->variant->size_id,
            'color_id' => $unit->variant->color_id,
            'variant_label' => $unit->variant->label(),
            'physical_product_id' => $unit->id,
            'physical_code' => $unit->code,
            'quantity' => 1,
            'unit_price' => $price,
        ]], fn (array $item) => [...$item, 'line_total' => round($price, 2)]);
    }

    /**
     * Precarga cabecera y líneas de una venta anulada para registrarla de
     * nuevo con los datos corregidos (la anulada se conserva tal cual).
     */
    protected function prefillFromCancelledSale(int $saleId): void
    {
        $sale = Sale::with([
            'client:id,display_name',
            'details.variant.product:id,name,code,category_id',
            'details.variant.size:id,name',
            'details.variant.color:id,name',
            'details.physicalProduct:id,code,status',
        ])
            ->where('status', Sale::STATUS_CANCELLED)
            ->find($saleId);

        if (! $sale) {
            return;
        }

        $this->redoFromCode = $sale->code;
        $this->client_id = $sale->client_id;
        $this->client_name = $sale->client?->display_name;
        $this->document_type = $sale->document_type;
        $this->has_invoice_pending = $sale->has_invoice_pending;
        $this->notes = $sale->notes;

        foreach ($sale->details as $detail) {
            $product = $detail->variant?->product;

            if (! $product) {
                continue;
            }

            // En BD se guarda sin IGV; el formulario trabaja con IGV incluido.
            $lineTotal = round((float) $detail->subtotal * (1 + SaleService::TAX_RATE), 2);
            $unit = $detail->physicalProduct?->status === 'available' ? $detail->physicalProduct : null;

            $this->addCartItem([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_code' => $product->code,
                'category_id' => $product->category_id,
                'size_id' => $detail->variant->size_id,
                'color_id' => $detail->variant->color_id,
                'physical_product_id' => $unit?->id,
                'physical_code' => $unit?->code,
                'variant_label' => $detail->variant->label(),
                'quantity' => $detail->quantity,
                'unit_price' => round($lineTotal / $detail->quantity, 2),
                'line_total' => $lineTotal,
                'discount_percent' => 0,
            ]);
        }
    }

    #[On('product-picker-selected')]
    public function handlePickedItems(string $context, array $items): void
    {
        if ($context !== 'sale-gallery') {
            return;
        }

        // El subtotal de la línea (con IGV) sigue a cantidad × precio al agregar o sumar unidades.
        $this->addPickedItems($items, fn (array $item) => [
            ...$item,
            'line_total' => round((int) $item['quantity'] * (float) $item['unit_price'], 2),
        ]);
    }

    #[On('entity-selected')]
    public function handleEntitySelected(string $context, int $id, string $label): void
    {
        if ($context === 'sale-client') {
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
        if ($context === 'sale-client') {
            $this->client_id = null;
            $this->client_name = null;
        }
    }

    /**
     * Consumibles que aplican a las categorías del carrito (o a todas si el
     * bien no tiene categorías), con la cantidad sugerida: una por prenda.
     *
     * @return Collection<int, Asset>
     */
    public function deliverableAssets(): Collection
    {
        if ($this->items === []) {
            return collect();
        }

        $unitsByCategory = collect($this->items)
            ->groupBy(fn (array $item) => (int) ($item['category_id'] ?? 0))
            ->map(fn (Collection $rows) => $rows->sum(fn (array $item) => ! empty($item['physical_product_id']) ? 1 : max(1, (int) ($item['quantity'] ?? 1))));

        return Asset::deliverable()
            ->where('quantity', '>', 0)
            ->with('categories:id')
            ->orderBy('name')
            ->get()
            ->map(function (Asset $asset) use ($unitsByCategory) {
                $categoryIds = $asset->categories->pluck('id');
                $asset->suggested_quantity = (int) ($categoryIds->isEmpty()
                    ? $unitsByCategory->sum()
                    : $unitsByCategory->only($categoryIds->all())->sum());

                return $asset;
            })
            ->filter(fn (Asset $asset) => $asset->suggested_quantity > 0)
            ->values();
    }

    /**
     * Bienes con precio de venta y stock que aún no están en el detalle.
     *
     * @return Collection<int, Asset>
     */
    public function sellableAssets(): Collection
    {
        return Asset::sellable()
            ->where('quantity', '>', 0)
            ->whereNotIn('id', array_column($this->assetLines, 'asset_id'))
            ->orderBy('name')
            ->get(['id', 'name', 'quantity', 'sale_price']);
    }

    public function updatedAssetToAdd(string $assetId): void
    {
        $asset = Asset::sellable()->where('quantity', '>', 0)->find((int) $assetId);

        if ($asset) {
            $this->assetLines["asset-{$asset->id}"] = [
                'asset_id' => $asset->id,
                'name' => $asset->name,
                'available' => $asset->quantity,
                'quantity' => 1,
                'unit_price' => (float) $asset->sale_price,
            ];
        }

        $this->assetToAdd = '';
    }

    public function removeAssetLine(string $key): void
    {
        unset($this->assetLines[$key]);
    }

    /**
     * Lo que sale del stock de bienes con esta venta: los entregables de
     * regalo marcados y los bienes vendidos del detalle.
     *
     * @return array<int, array{asset_id: int, quantity: int, unit_price: float}>
     */
    protected function assetDeliveries(): array
    {
        $gifts = collect($this->selectedDeliveries())
            ->map(fn (int $quantity, int $assetId) => ['asset_id' => $assetId, 'quantity' => $quantity, 'unit_price' => 0.0])
            ->values();

        $sold = collect($this->assetLines)->map(fn (array $line) => [
            'asset_id' => (int) $line['asset_id'],
            'quantity' => (int) $line['quantity'],
            'unit_price' => (float) $line['unit_price'],
        ])->values();

        return [...$gifts, ...$sold];
    }

    /**
     * @return array<int, int> asset_id => cantidad a entregar
     */
    protected function selectedDeliveries(): array
    {
        return $this->deliverableAssets()
            ->filter(fn (Asset $asset) => ! empty($this->deliveries[$asset->id]['enabled']))
            ->mapWithKeys(function (Asset $asset) {
                $quantity = $this->deliveries[$asset->id]['quantity'] ?? null;

                return [$asset->id => ($quantity === null || $quantity === '') ? $asset->suggested_quantity : (int) $quantity];
            })
            ->filter(fn (int $quantity) => $quantity > 0)
            ->all();
    }

    public function billingEnabled(): bool
    {
        return app(BillingService::class)->isEnabled();
    }

    public function save(SaleService $service): void
    {
        $this->authorize('ventas.registrar');

        $this->validate([
            'document_type' => 'required|in:nota_venta,boleta,factura',
            'sale_date' => 'required|date',
            // Una venta puede ser solo de bienes (ej. un portaterno suelto).
            'items' => [$this->assetLines === [] ? 'required' : 'nullable', 'array'],
            'assetLines.*.quantity' => ['required', 'integer', 'min:1'],
            'assetLines.*.unit_price' => ['required', 'numeric', 'min:0'],
        ], [
            'items.required' => 'Agrega al menos un producto o un bien a la venta.',
        ], [
            'assetLines.*.quantity' => 'cantidad',
            'assetLines.*.unit_price' => 'precio',
        ]);

        // Con facturación electrónica el número lo asigna SUNAT al facturar desde Ventas:
        // elegir boleta o factura deja la venta pendiente de facturar.
        $electronic = $this->billingEnabled();

        try {
            $sale = $service->register([
                'client_id' => $this->client_id,
                'document_type' => $this->document_type,
                'document_number' => $electronic ? null : $this->document_number,
                'has_invoice_pending' => $this->has_invoice_pending
                    || ($electronic && in_array($this->document_type, ['boleta', 'factura'], true)),
                'sale_date' => $this->sale_date,
                'notes' => $this->notes,
            ], $this->normalizedLines(), $this->assetDeliveries());

            $this->reset(['client_id', 'client_name', 'document_number', 'has_invoice_pending', 'notes', 'items', 'deliveries', 'assetLines', 'redoFromCode']);
            $this->document_type = 'nota_venta';
            $this->sale_date = now()->toDateString();
            $this->clientPickerVersion++;

            Flux::toast(variant: 'success', text: "Venta {$sale->code} registrada correctamente.");
            $this->dispatch('saleSaved', saleId: $sale->id);

            // Tras registrar se muestra el comprobante listo para compartir.
            $this->redirectRoute('receipts.show', ['type' => 'venta', 'id' => $sale->id], navigate: true);
        } catch (\Throwable $th) {
            report($th);
            Flux::toast(variant: 'danger', text: 'No se pudo registrar la venta: '.$th->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.sale.sale-form');
    }
}
