<?php

namespace App\Livewire\Inventory;

use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\Core\AccountRule;
use App\Services\Inventory\ProductService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Alta y edición rápida de producto en un modal reutilizable (catálogo y
 * compras). Pide solo lo esencial; SKU, descripción, estado y ficha técnica
 * quedan en "Más opciones".
 *
 * - Categoría y marca se escriben con sugerencias: si no existen, se crean.
 * - Las primeras fotos se suben aquí mismo: se guardan después de crear el
 *   producto (cuando ya hay id) y pasan por ProductImageOptimizer.
 *
 * Se abre con `open-product-modal` y avisa con `product-saved`; `context`
 * permite a quien lo abrió saber que el aviso es para él.
 */
class ProductFormModal extends Component
{
    use WithFileUploads;

    public const MAX_PHOTOS = 8;

    public bool $show = false;

    public ?int $productId = null;

    public string $context = '';

    public string $name = '';

    public string $category = '';

    public string $brand = '';

    public string $gender = 'unisex';

    /** @var float|int|string */
    public $price = 0;

    // Operatividad: both (alquiler y venta) | rental | sale
    public string $usage = 'both';

    public bool $moreOptions = false;

    public string $code = '';

    public ?string $description = null;

    public bool $is_active = true;

    /** @var array<int, int> */
    public array $selectedAttributeValueIds = [];

    public bool $hasPhysicalItems = false;

    /** @var array<int, TemporaryUploadedFile> Fotos recién elegidas (se vacía al acumularlas). */
    public array $photoUploads = [];

    /** @var array<int, TemporaryUploadedFile> */
    public array $photos = [];

    #[On('open-product-modal')]
    public function open(?int $productId = null, string $context = ''): void
    {
        $this->resetValidation();
        $this->reset();
        $this->context = $context;

        if ($productId) {
            $product = Product::with(['attributeValues:id', 'category:id,name', 'brand:id,name'])->findOrFail($productId);

            $this->productId = $product->id;
            $this->name = $product->name;
            $this->category = (string) $product->category?->name;
            $this->brand = (string) $product->brand?->name;
            $this->gender = $product->gender;
            $this->price = (float) $product->price;
            $this->usage = match (true) {
                $product->allow_rental && ! $product->allow_sale => 'rental',
                $product->allow_sale && ! $product->allow_rental => 'sale',
                default => 'both',
            };
            $this->code = $product->code;
            $this->description = $product->description;
            $this->is_active = $product->is_active;
            $this->selectedAttributeValueIds = $product->attributeValues->pluck('id')->all();
            $this->hasPhysicalItems = $product->physicalProducts()->exists();
            $this->moreOptions = filled($product->description) || ! $product->is_active || $this->selectedAttributeValueIds !== [];
        } else {
            $this->code = 'PROD-'.strtoupper(Str::random(6));
        }

        $this->show = true;
    }

    public function updatedPhotoUploads(): void
    {
        $this->validate([
            'photoUploads.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:12288'],
        ], [
            'photoUploads.*.image' => 'Solo se aceptan fotos (JPG, PNG, WEBP).',
            'photoUploads.*.mimes' => 'Solo se aceptan fotos (JPG, PNG, WEBP).',
            'photoUploads.*.max' => 'Cada foto puede pesar hasta 12 MB.',
        ]);

        $this->photos = array_slice([...$this->photos, ...$this->photoUploads], 0, self::MAX_PHOTOS);
        $this->photoUploads = [];
    }

    public function removePhoto(int $index): void
    {
        unset($this->photos[$index]);

        $this->photos = array_values($this->photos);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'gender' => ['required', 'in:masculino,femenino,unisex,nino'],
            'price' => ['required', 'numeric', 'min:0'],
            'usage' => ['required', 'in:both,rental,sale'],
            'code' => ['required', 'string', 'max:50', AccountRule::unique('products', 'code')->ignore($this->productId)],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'selectedAttributeValueIds' => ['array'],
            'selectedAttributeValueIds.*' => [AccountRule::exists('attribute_values')],
            'photos' => ['array', 'max:'.self::MAX_PHOTOS],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:12288'],
        ];
    }

    public function save(ProductService $service): void
    {
        $this->authorize('inventario.productos');

        $validated = $this->validate(attributes: [
            'name' => 'nombre',
            'category' => 'categoría',
            'brand' => 'marca',
            'price' => 'precio',
            'code' => 'código',
        ]);

        $data = [
            'name' => trim($validated['name']),
            'code' => $validated['code'],
            'description' => $validated['description'],
            'price' => $validated['price'],
            'gender' => $validated['gender'],
            'allow_rental' => $validated['usage'] !== 'sale',
            'allow_sale' => $validated['usage'] !== 'rental',
            'is_active' => $validated['is_active'],
            'attribute_value_ids' => $validated['selectedAttributeValueIds'],
        ];

        $isNew = $this->productId === null;

        // Si la categoría o la marca son nuevas y el producto no llega a
        // guardarse (ej. nombre duplicado), no deben quedar creadas a medias.
        $product = DB::transaction(function () use ($service, $data, $validated) {
            if ($this->productId) {
                $product = Product::findOrFail($this->productId);

                // Con prendas registradas la clasificación no cambia: no se resuelve ni crea nada.
                if (! $product->physicalProducts()->exists()) {
                    $data += $this->resolveClassification($service, $validated);
                } else {
                    $data += $product->only(['category_id', 'brand_id']);
                    $data['gender'] = $product->gender;
                }

                return $service->actualizar($product, $data);
            }

            return $service->crear($data + $this->resolveClassification($service, $validated));
        });

        $photosFailed = false;

        try {
            foreach ($this->photos as $photo) {
                $service->agregarMedia($product, $photo);
            }
        } catch (\Throwable $e) {
            report($e);
            $photosFailed = true;
        }

        $this->show = false;
        $this->reset(['photos', 'photoUploads']);

        $this->dispatch('product-saved', productId: $product->id, context: $this->context, created: $isNew);

        $photosFailed
            ? Flux::toast(variant: 'warning', text: 'Producto guardado, pero no se pudieron procesar todas las fotos. Súbalas desde la galería del producto.')
            : Flux::toast(variant: 'success', text: $isNew ? 'Producto registrado correctamente.' : 'Producto actualizado correctamente.');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{category_id: int, brand_id: ?int}
     */
    protected function resolveClassification(ProductService $service, array $validated): array
    {
        return [
            'category_id' => $service->resolverCategoria($validated['category'], Gate::allows('inventario.categorias'))->id,
            'brand_id' => $service->resolverMarca($validated['brand'], Gate::allows('inventario.atributos'))?->id,
        ];
    }

    public function render(): View
    {
        return view('livewire.inventory.product-form-modal', [
            'maxPhotos' => self::MAX_PHOTOS,
            'categoryOptions' => $this->show ? Category::where('is_active', true)->orderBy('name')->pluck('name') : collect(),
            'brandOptions' => $this->show ? Brand::where('is_active', true)->orderBy('name')->pluck('name') : collect(),
            'technicalAttributes' => $this->show && $this->moreOptions ? Attribute::with('values')->orderBy('name')->get() : collect(),
        ]);
    }
}
