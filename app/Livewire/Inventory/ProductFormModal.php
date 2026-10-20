<?php

namespace App\Livewire\Inventory;

use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\Core\AccountRule;
use App\Services\Inventory\ProductSearch;
use App\Services\Inventory\ProductService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Alta y edición rápida de producto en un modal reutilizable (catálogo y
 * compras). Pide solo lo esencial; SKU, descripción y estado quedan en
 * "Más opciones".
 *
 * - Un producto nuevo empieza por "¿Qué vas a registrar?": se elige la
 *   categoría y se ven los productos que ya tiene, para comprar más de uno
 *   existente en vez de duplicarlo. Si la categoría está vacía, va directo
 *   al formulario.
 * - Características (tela, corte, cuero...) se escriben con sugerencias y se
 *   guardan como texto en el producto (ver ProductService::sincronizarCaracteristicas).
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

    // category: elegir qué se registra | products: los que ya existen | form
    public string $step = 'form';

    public ?int $chosenCategoryId = null;

    // Búsqueda rápida en el paso de categorías ("¿ya lo tengo?").
    public string $finder = '';

    public ?int $productId = null;

    public string $context = '';

    public string $name = '';

    public string $category = '';

    public string $brand = '';

    public string $gender = 'unisex';

    /** @var float|int|string */
    public $price = 0;

    /** @var float|int|string */
    public $purchase_price = 0;

    // Operatividad: both (alquiler y venta) | rental | sale
    public string $usage = 'both';

    public bool $moreOptions = false;

    public string $code = '';

    public ?string $description = null;

    public bool $is_active = true;

    /** @var array<int, array{attribute_id: ?int, name: string, value: string}> */
    public array $characteristics = [];

    public string $newCharacteristic = '';

    /** @var array<int, string> Etiquetas manuales (Quinceañera, Señora...). */
    public array $tags = [];

    public string $newTag = '';

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
            $product = Product::with(['characteristics', 'tags', 'category:id,name', 'brand:id,name'])->findOrFail($productId);
            $this->tags = $product->tags->pluck('name')->all();

            $this->productId = $product->id;
            $this->name = $product->name;
            $this->category = (string) $product->category?->name;
            $this->brand = (string) $product->brand?->name;
            $this->gender = $product->gender;
            $this->price = (float) $product->price;
            $this->purchase_price = (float) $product->purchase_price;
            $this->usage = match (true) {
                $product->allow_rental && ! $product->allow_sale => 'rental',
                $product->allow_sale && ! $product->allow_rental => 'sale',
                default => 'both',
            };
            $this->code = $product->code;
            $this->description = $product->description;
            $this->is_active = $product->is_active;
            $this->characteristics = $product->characteristics
                ->map(fn ($characteristic) => $characteristic->only(['attribute_id', 'name', 'value']))
                ->all();
            $this->hasPhysicalItems = $product->physicalProducts()->exists();
            $this->moreOptions = filled($product->description) || ! $product->is_active;
            $this->refreshCharacteristicRows($product->category_id);
        } else {
            $this->code = 'PROD-'.strtoupper(Str::random(6));
            // Sin categorías todavía no hay nada que elegir: directo al formulario.
            $this->step = Category::where('is_active', true)->exists() ? 'category' : 'form';
            $this->refreshCharacteristicRows(null);
        }

        $this->show = true;
    }

    public function chooseCategory(int $categoryId): void
    {
        $category = Category::where('is_active', true)->findOrFail($categoryId);
        $this->chosenCategoryId = $category->id;

        $category->products()->exists()
            ? $this->step = 'products'
            : $this->startNew($category->id);
    }

    /**
     * Pasa al formulario de un producto nuevo, con la categoría ya puesta.
     */
    public function startNew(?int $categoryId = null): void
    {
        $this->category = (string) ($categoryId ? Category::find($categoryId)?->name : '');
        $this->step = 'form';
        $this->refreshCharacteristicRows($categoryId);
    }

    public function backToCategories(): void
    {
        $this->resetValidation();
        $this->step = 'category';
        $this->chosenCategoryId = null;
    }

    /**
     * Ya existe: en compras se agrega a la compra; en el catálogo se abre
     * una compra nueva con ese producto (el stock solo entra comprando).
     */
    public function useExisting(int $productId): void
    {
        $product = Product::findOrFail($productId);

        if ($this->context === 'purchase') {
            $this->show = false;
            $this->dispatch('product-saved', productId: $product->id, context: $this->context, created: false);

            return;
        }

        $this->authorize('compras.registrar');
        $this->redirectRoute('purchasing.purchases.create', ['producto' => $product->id], navigate: true);
    }

    public function updatedCategory(): void
    {
        $this->refreshCharacteristicRows($this->resolvedCategoryId());
    }

    public function addCharacteristic(): void
    {
        $name = Str::squish($this->newCharacteristic);
        $this->newCharacteristic = '';

        if ($name === '') {
            return;
        }

        $exists = collect($this->characteristics)->contains(fn (array $row) => Str::lower($row['name']) === Str::lower($name));

        if (! $exists) {
            $attribute = Attribute::whereRaw('LOWER(name) = ?', [Str::lower($name)])->first();
            $this->characteristics[] = ['attribute_id' => $attribute?->id, 'name' => $attribute?->name ?? $name, 'value' => ''];
        }
    }

    public function addTag(): void
    {
        $tag = mb_substr(Str::squish($this->newTag), 0, 60);
        $this->newTag = '';

        if ($tag !== '' && ! collect($this->tags)->contains(fn (string $existing) => mb_strtolower($existing) === mb_strtolower($tag))) {
            $this->tags[] = $tag;
        }
    }

    public function removeTag(int $index): void
    {
        unset($this->tags[$index]);
        $this->tags = array_values($this->tags);
    }

    public function removeCharacteristic(int $index): void
    {
        unset($this->characteristics[$index]);
        $this->characteristics = array_values($this->characteristics);
    }

    /**
     * Filas de características: las que aplican a la categoría (vacías para
     * llenar) más las que ya tienen valor o el usuario agregó a mano.
     */
    protected function refreshCharacteristicRows(?int $categoryId): void
    {
        $applicable = Attribute::forCategory($categoryId)->orderBy('name')->get(['id', 'name']);

        $rows = collect($this->characteristics)->filter(fn (array $row) => filled($row['value'])
            || $row['attribute_id'] === null
            || $applicable->contains('id', $row['attribute_id']));

        foreach ($applicable as $attribute) {
            if (! $rows->contains(fn (array $row) => $row['attribute_id'] === $attribute->id || Str::lower($row['name']) === Str::lower($attribute->name))) {
                $rows->push(['attribute_id' => $attribute->id, 'name' => $attribute->name, 'value' => '']);
            }
        }

        $this->characteristics = $rows->values()->all();
    }

    protected function resolvedCategoryId(): ?int
    {
        $name = trim($this->category);

        return $name === '' ? null : Category::where('slug', Str::slug($name))->value('id');
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
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'usage' => ['required', 'in:both,rental,sale'],
            'code' => ['required', 'string', 'max:50', AccountRule::unique('products', 'code')->ignore($this->productId)],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'characteristics' => ['array'],
            'characteristics.*.attribute_id' => ['nullable', 'integer'],
            'characteristics.*.name' => ['required', 'string', 'max:60'],
            'characteristics.*.value' => ['nullable', 'string', 'max:120'],
            'tags' => ['array', 'max:20'],
            'tags.*' => ['string', 'max:60'],
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
            'price' => 'precio de venta',
            'purchase_price' => 'precio de compra',
            'code' => 'código',
        ]);

        $data = [
            'name' => trim($validated['name']),
            'code' => $validated['code'],
            'description' => $validated['description'],
            'price' => $validated['price'],
            'purchase_price' => (float) ($validated['purchase_price'] ?: 0),
            'gender' => $validated['gender'],
            'allow_rental' => $validated['usage'] !== 'sale',
            'allow_sale' => $validated['usage'] !== 'rental',
            'is_active' => $validated['is_active'],
            'characteristics' => $validated['characteristics'] ?? [],
            'tags' => $validated['tags'] ?? [],
            // Lo nuevo que se escribió (una tela, un corte) queda como sugerencia.
            'can_add_suggestions' => Gate::allows('inventario.atributos'),
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

    /**
     * Productos de la lista del asistente, con lo necesario para reconocerlos.
     */
    protected function productCards(Builder $query): Collection
    {
        return $query->with(['primaryMedia', 'characteristics', 'brand:id,name'])
            ->withSum('variants as stock_total', 'stock')
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(30)
            ->get();
    }

    /**
     * Productos que coinciden con lo escrito (nombre, código o característica).
     */
    protected function matching(string $term, ?int $categoryId = null): Builder
    {
        $words = collect(preg_split('/\s+/', trim($term)))->filter(fn (string $word) => mb_strlen($word) >= 3);

        return Product::query()
            ->when($categoryId, fn (Builder $q) => $q->where('category_id', $categoryId))
            ->where(function (Builder $q) use ($words, $term) {
                $q->where('code', 'like', '%'.trim($term).'%')
                    ->orWhere(function (Builder $q) use ($words) {
                        foreach ($words as $word) {
                            $q->where(fn (Builder $w) => $w->where('name', 'like', "%{$word}%")
                                ->orWhereHas('characteristics', fn (Builder $c) => $c->where('value', 'like', "%{$word}%")));
                        }
                    });
            });
    }

    public function render(): View
    {
        $isNew = $this->productId === null;
        $categoryId = $this->step === 'form' ? $this->resolvedCategoryId() : $this->chosenCategoryId;

        return view('livewire.inventory.product-form-modal', [
            'maxPhotos' => self::MAX_PHOTOS,
            'categoryOptions' => $this->show ? Category::where('is_active', true)->orderBy('name')->pluck('name') : collect(),
            'brandOptions' => $this->show ? Brand::where('is_active', true)->orderBy('name')->pluck('name') : collect(),
            'chooserCategories' => $this->show && $this->step === 'category'
                ? Category::where('is_active', true)->withCount('products')->orderByDesc('products_count')->orderBy('name')->get(['id', 'name'])
                : collect(),
            'finderResults' => $this->show && $this->step === 'category' && mb_strlen(trim($this->finder)) >= 2
                ? $this->productCards(app(ProductSearch::class)->apply(Product::query(), $this->finder)->with('category:id,name'))
                : collect(),
            'chosenCategory' => $this->step === 'products' ? Category::find($this->chosenCategoryId) : null,
            'existingProducts' => $this->show && $this->step === 'products'
                ? $this->productCards(Product::where('category_id', $this->chosenCategoryId))
                : collect(),
            // Aviso de posible duplicado mientras se escribe el nombre.
            'similarProducts' => $this->show && $isNew && $this->step === 'form' && mb_strlen(trim($this->name)) >= 3
                ? $this->productCards($this->matching($this->name, $categoryId))->take(3)
                : collect(),
            'suggestions' => $this->show && $this->step === 'form'
                ? Attribute::with('values:id,attribute_id,value')
                    ->whereKey(collect($this->characteristics)->pluck('attribute_id')->filter())
                    ->get()->mapWithKeys(fn (Attribute $attribute) => [$attribute->id => $attribute->values->pluck('value')])
                : collect(),
            'characteristicNames' => $this->show && $this->step === 'form'
                ? Attribute::active()->orderBy('name')->pluck('name')
                : collect(),
            'tagSuggestions' => $this->show && $this->step === 'form' ? app(ProductSearch::class)->tagSuggestions() : [],
            'automaticTags' => $this->show && $this->step === 'form' ? $this->automaticTagsPreview() : [],
        ]);
    }

    /**
     * Lo que se etiqueta solo con lo que hay en el formulario (y, si ya
     * existe, las tallas y colores de sus variantes).
     *
     * @return array<int, string>
     */
    protected function automaticTagsPreview(): array
    {
        $variantTags = $this->productId
            ? collect(Product::with('variants.size:id,name', 'variants.color:id,name')->find($this->productId)?->variants)
                ->flatMap(fn ($variant) => [$variant->color?->name, $variant->size ? "Talla {$variant->size->name}" : null])
            : collect();

        return collect([
            trim($this->category),
            Product::GENDER_LABELS[$this->gender] ?? null,
            trim($this->brand),
            ...collect($this->characteristics)->pluck('value'),
            ...$variantTags,
        ])->filter()->unique(fn (string $tag) => mb_strtolower($tag))->values()->all();
    }
}
