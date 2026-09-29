<?php

namespace App\Livewire\Inventory;

use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\Inventory\ProductService;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Str;

class ProductForm extends Component
{
    public ?int $productId = null;

    public ?int $category_id = null;
    public ?int $brand_id = null;
    public string $code = '';
    public string $name = '';
    public ?string $description = null;
    public float $price = 0;
    public string $gender = 'unisex';
    public bool $allow_sale = true;
    public bool $allow_rental = true;
    public bool $is_active = true;

    public array $selectedAttributeValueIds = [];

    public bool $isEditing = false;
    public bool $hasPhysicalItems = false;

    public function mount(?Product $product = null): void
    {
        if ($product && $product->exists) {
            $this->isEditing = true;
            $this->productId = $product->id;

            $product->load(['attributeValues']);

            $this->category_id = $product->category_id;
            $this->brand_id = $product->brand_id;
            $this->code = $product->code;
            $this->name = $product->name;
            $this->description = $product->description;
            $this->price = (float) $product->price;
            $this->gender = $product->gender;
            $this->allow_sale = $product->allow_sale;
            $this->allow_rental = $product->allow_rental;
            $this->is_active = $product->is_active;

            $this->selectedAttributeValueIds = $product->attributeValues->pluck('id')->toArray();
            $this->hasPhysicalItems = $product->physicalProducts()->exists();
        } else {
            $this->code = 'PROD-' . strtoupper(Str::random(6));
        }
    }

    protected function rules(): array
    {
        return [
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'code' => ['required', 'string', 'max:50', Rule::unique('products', 'code')->ignore($this->productId)],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'gender' => 'required|in:masculino,femenino,unisex,nino',
            'allow_sale' => 'boolean',
            'allow_rental' => 'boolean',
            'is_active' => 'boolean',
            'selectedAttributeValueIds' => 'array',
            'selectedAttributeValueIds.*' => 'exists:attribute_values,id',
        ];
    }

    public function save(ProductService $service)
    {
        
        $data = $this->validate();

        $data['attribute_value_ids'] = $this->selectedAttributeValueIds;
        unset($data['selectedAttributeValueIds']);

        if ($this->isEditing) {
            $product = Product::findOrFail($this->productId);
            $service->actualizar($product, $data);
            session()->flash('message', 'Producto actualizado correctamente.');
        } else {
            $service->crear($data);
            session()->flash('message', 'Producto registrado correctamente.');
        }

        return $this->redirectRoute('inventory.products.index');
    }

    public function render()
    {
        return view('livewire.inventory.product-form', [
            'categoryOptions' => Category::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            // Marcas activas, más la que el producto ya tiene aunque esté desactivada.
            'brandOptions' => Brand::where(fn ($q) => $q->where('is_active', true)->orWhere('id', $this->brand_id))
                ->orderBy('name')->get(['id', 'name']),
            'attributes' => Attribute::with('values')->orderBy('name')->get(),
        ]);
    }
}