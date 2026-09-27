<?php

namespace App\Livewire\Rental;

use App\Livewire\Shared\ManagesCartItems;
use App\Services\Rental\RentalService;
use Flux\Flux;
use Livewire\Attributes\On;
use Livewire\Component;

class RentalForm extends Component
{
    use ManagesCartItems;

    public ?int $client_id = null;
    public ?string $client_name = null;
    public ?string $pickup_date = null;
    public ?string $expected_return_date = null;
    public float $deposit_amount = 0;
    public ?string $notes = null;

    public array $items = [];

    public int $clientPickerVersion = 0;

    public function mount(): void
    {
        $this->pickup_date = now()->format('Y-m-d\TH:i');
        $this->expected_return_date = now()->addDays(1)->format('Y-m-d\TH:i');
    }

    #[On('gallery-product-added')]
    public function handleGalleryProduct(string $context, int $productId, string $productName, ?string $code, ?int $categoryId, float $price): void
    {
        if ($context !== 'rental-gallery') {
            return;
        }

        $this->addCartItem([
            'product_id' => $productId,
            'product_name' => $productName,
            'product_code' => $code,
            'category_id' => $categoryId,
            'size_id' => null,
            'color_id' => null,
            'quantity' => 1,
            'unit_price' => $price,
            'discount_percent' => 0,
        ]);
    }

    #[On('entity-selected')]
    public function handleEntitySelected(string $context, int $id, string $label): void
    {
        if ($context === 'rental-client') {
            $this->client_id = $id;
            $this->client_name = $label;
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

    public function save(RentalService $service): void
    {
        $this->validate([
            'client_id' => 'required|integer',
            'pickup_date' => 'required|date',
            'expected_return_date' => 'required|date|after:pickup_date',
            'items' => 'required|array|min:1',
        ], [
            'client_id.required' => 'Elige un cliente para el alquiler.',
            'items.required' => 'Agrega al menos una prenda al alquiler.',
        ]);

        try {
            $rental = $service->register([
                'client_id' => $this->client_id,
                'pickup_date' => $this->pickup_date,
                'expected_return_date' => $this->expected_return_date,
                'deposit_amount' => $this->deposit_amount,
                'notes' => $this->notes,
            ], $this->normalizedLines());

            $this->reset(['client_id', 'client_name', 'deposit_amount', 'notes', 'items']);
            $this->pickup_date = now()->format('Y-m-d\TH:i');
            $this->expected_return_date = now()->addDays(1)->format('Y-m-d\TH:i');
            $this->clientPickerVersion++;

            Flux::toast(variant: 'success', text: "Alquiler {$rental->code} registrado correctamente.");
            $this->dispatch('rentalSaved', rentalId: $rental->id);
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