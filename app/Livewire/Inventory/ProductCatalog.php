<?php

namespace App\Livewire\Inventory;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Services\Inventory\ProductSearch;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Catálogo para mostrar al cliente, como hojear un PDF: solo fotos, nombre y
 * precio. Al tocar un producto se abre el reel (ProductDetailModal en modo
 * `catalog`), que además permite compartir por WhatsApp y, a quien tenga
 * `catalogo.costos`, ver el costo y el proveedor para negociar el precio.
 */
class ProductCatalog extends Component
{
    public const PAGE_SIZE = 24;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'tipo', except: '')]
    public string $gender = '';

    #[Url(as: 'categoria', except: null)]
    public ?int $categoryId = null;

    public int $limit = self::PAGE_SIZE;

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'gender', 'categoryId'], true)) {
            $this->limit = self::PAGE_SIZE;
        }
    }

    public function selectGender(string $gender): void
    {
        $this->gender = $this->gender === $gender || ! array_key_exists($gender, Product::GENDER_LABELS) ? '' : $gender;
        $this->limit = self::PAGE_SIZE;
    }

    public function loadMore(): void
    {
        $this->limit += self::PAGE_SIZE;
    }

    /**
     * Productos visibles con su precio de lista y su portada. Se pide uno más
     * del límite para saber si queda algo por cargar.
     *
     * @return Collection<int, Product>
     */
    #[Computed]
    public function products(): Collection
    {
        $products = Product::query()
            ->with('category:id,name')
            ->where('is_active', true)
            ->where(fn ($query) => $query->where('allow_sale', true)->orWhere('allow_rental', true))
            ->when($this->gender !== '', fn ($query) => $query->where('gender', $this->gender))
            ->when($this->categoryId, fn ($query) => $query->where('category_id', $this->categoryId))
            ->when(trim($this->search) !== '', fn ($query) => app(ProductSearch::class)->apply($query, $this->search))
            ->withMin('variants as sale_price_from', 'sale_price')
            ->withMin('variants as rental_price_from', 'rental_price')
            ->orderBy('name')
            ->limit($this->limit + 1)
            ->get();

        $covers = ProductMedia::whereIn('product_id', $products->pluck('id'))
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->get()
            ->unique('product_id')
            ->keyBy('product_id');

        foreach ($products as $product) {
            $product->cover_url = $covers[$product->id]->url ?? null;
            $product->cover_is_video = ($covers[$product->id]->file_type ?? null) === 'video';
            [$product->list_price, $product->list_price_label] = $this->listPrice($product);
        }

        return $products;
    }

    #[Computed]
    public function hasMore(): bool
    {
        return $this->products->count() > $this->limit;
    }

    /**
     * Categorías que tienen al menos un producto activo.
     *
     * @return Collection<int, Category>
     */
    #[Computed]
    public function categories(): Collection
    {
        return Category::whereHas('products', fn ($query) => $query->where('is_active', true))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function openDetail(int $productId): void
    {
        $this->dispatch('product-detail-requested', productId: $productId, mode: 'catalog');
    }

    /**
     * Precio que ve el cliente: el de venta si se vende, si no el de alquiler.
     *
     * @return array{0: float|null, 1: string}
     */
    protected function listPrice(Product $product): array
    {
        if ($product->allow_sale) {
            return [(float) ($product->sale_price_from ?? $product->price), ''];
        }

        return [$product->rental_price_from !== null ? (float) $product->rental_price_from : null, 'alquiler'];
    }

    public function render(): View
    {
        return view('livewire.inventory.product-catalog', [
            'genders' => Product::GENDER_LABELS,
        ]);
    }
}
