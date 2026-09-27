<?php

namespace App\Livewire\Inventory;

use App\Models\Color;
use App\Models\Product;
use App\Services\Inventory\ProductService;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class ProductMediaManager extends Component
{
    use WithFileUploads;

    public bool $show = false;
    public ?int $productId = null;

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile[] */
    public array $newFiles = [];

    public ?int $colorId = null;
    public bool $isPrimary = false;

    #[On('manageMedia')]
    public function open(int $productId): void
    {
        $this->productId = $productId;
        $this->reset(['newFiles', 'colorId', 'isPrimary']);
        $this->show = true;
    }

    public function uploadMedia(ProductService $service): void
    {
        $this->validate([
            'newFiles.*' => 'required|file|mimes:jpg,jpeg,png,webp,mp4,mov|max:20480', // Max 20MB por archivo
            'colorId' => 'nullable|exists:colors,id',
        ]);

        $product = Product::findOrFail($this->productId);

        foreach ($this->newFiles as $index => $file) {
            $service->agregarMedia(
                product: $product,
                file: $file,
                colorId: $colorId = $this->colorId,
                isPrimary: $this->isPrimary && $index === 0
            );
        }

        $this->reset(['newFiles', 'colorId', 'isPrimary']);
        session()->flash('media_success', 'Archivos subidos con éxito.');
    }

    public function deleteMedia(int $mediaId, ProductService $service): void
    {
        $product = Product::findOrFail($this->productId);
        $service->eliminarMedia($product, $mediaId);
    }

    public function setPrimary(int $mediaId, ProductService $service): void
    {
        $product = Product::findOrFail($this->productId);
        $service->marcarComoPrincipal($product, $mediaId);
    }

    public function render()
    {
        $product = $this->productId
            ? Product::with(['media.color'])->find($this->productId)
            : null;

        return view('livewire.inventory.product-media-manager', [
            'product' => $product,
            'colors' => Color::orderBy('name')->get(['id', 'name']),
        ]);
    }
}