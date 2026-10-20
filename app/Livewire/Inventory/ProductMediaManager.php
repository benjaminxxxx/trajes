<?php

namespace App\Livewire\Inventory;

use App\Models\Color;
use App\Models\Product;
use App\Services\Core\AccountRule;
use App\Services\Inventory\ProductService;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class ProductMediaManager extends Component
{
    use WithFileUploads;

    public bool $show = false;

    public ?int $productId = null;

    /** @var TemporaryUploadedFile[] */
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
            'newFiles' => ['required', 'array'],
            'newFiles.*' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,mp4,mov', $this->sizeLimit(...)],
            'colorId' => ['nullable', AccountRule::exists('colors')],
        ], [
            'newFiles.required' => 'Elige al menos una foto o video.',
            'newFiles.*.mimes' => 'Solo se aceptan fotos (JPG, PNG, WEBP) o videos (MP4, MOV).',
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

    /**
     * Fotos y videos tienen límites distintos (ver business.media).
     */
    protected function sizeLimit(string $attribute, mixed $file, \Closure $fail): void
    {
        if (! $file instanceof TemporaryUploadedFile) {
            return;
        }

        $isVideo = str_starts_with((string) $file->getMimeType(), 'video/');
        $maxMb = $isVideo ? config('business.media.max_video_mb') : config('business.media.max_photo_mb');

        if ($file->getSize() > $maxMb * 1024 * 1024) {
            $fail(sprintf('«%s» pesa %s MB: %s hasta %d MB.',
                $file->getClientOriginalName(),
                number_format($file->getSize() / 1024 / 1024, 1),
                $isVideo ? 'los videos pueden pesar' : 'las fotos pueden pesar',
                $maxMb,
            ));
        }
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
            'maxPhotoMb' => config('business.media.max_photo_mb'),
            'maxVideoMb' => config('business.media.max_video_mb'),
        ]);
    }
}
