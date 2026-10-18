<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\Size;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    use RunsInAccount;

    public function run(): void
    {
        $this->ensureAccount();

        $categories = Category::all();
        $colors = Color::all()->keyBy('name');
        $sizes = Size::all();

        if ($categories->isEmpty()) {
            $this->command->warn('No se encontraron categorías. Revisa CategorySeeder.');

            return;
        }

        $generoAttr = Attribute::where('name', 'Género')->first();
        $materialAttr = Attribute::where('name', 'Material')->first();
        $corteAttr = Attribute::where('name', 'Corte / Estilo')->first();

        $catalog = [
            [
                'code' => 'TRN-SLM-001',
                'name' => 'Terno Ejecutivo Slim Fit',
                'description' => 'Terno confeccionado en lana fría, corte moderno Slim Fit ideal para eventos formales.',
                'category_name' => 'Ternos y Trajes',
                'price' => 120.00,
                'gender' => 'Caballero',
                'material' => 'Lana Fría',
                'corte' => 'Slim Fit',
                'images' => [
                    ['path' => 'products/terno-negro-1.jpg', 'is_primary' => true, 'sort' => 1],
                    ['path' => 'products/terno-negro-2.jpg', 'is_primary' => false, 'sort' => 2],
                ],
                'variants' => [
                    ['color' => 'Negro', 'size_type' => 'numerica', 'sizes' => ['38', '40', '42'], 'stock' => 3],
                    ['color' => 'Azul Marino', 'size_type' => 'numerica', 'sizes' => ['38', '40'], 'stock' => 2],
                ],
            ],
            [
                'code' => 'VST-GAL-002',
                'name' => 'Vestido Elegante de Noche',
                'description' => 'Vestido largo de gala en raso satinado con fina caída.',
                'category_name' => 'Vestidos',
                'price' => 150.00,
                'gender' => 'Dama',
                'material' => 'Razo / Satén',
                'corte' => 'Largo (Noche)',
                'images' => [
                    ['path' => 'products/vestido-rojo-1.jpg', 'is_primary' => true, 'sort' => 1],
                ],
                'variants' => [
                    ['color' => 'Vino / Guinda', 'size_type' => 'estandar', 'sizes' => ['S', 'M', 'L'], 'stock' => 2],
                    ['color' => 'Verde Esmeralda', 'size_type' => 'estandar', 'sizes' => ['S', 'M'], 'stock' => 1],
                ],
            ],
        ];

        foreach ($catalog as $item) {
            $category = $categories->firstWhere('name', $item['category_name'])
                ?? Category::firstOrCreate(
                    ['slug' => Str::slug($item['category_name'])],
                    ['name' => $item['category_name'], 'is_active' => true]
                );

            // 1. Crear / Buscar Producto
            $product = Product::firstOrCreate(
                ['code' => $item['code']],
                [
                    'category_id' => $category->id,
                    'name' => $item['name'],
                    'slug' => Str::slug($item['name']),
                    'description' => $item['description'],
                    'price' => $item['price'],
                    'is_active' => true,
                ]
            );

            // 2. Insertar Ficha Técnica (attribute_value_product)
            $attributeValueIds = [];

            if ($generoAttr && isset($item['gender'])) {
                $val = AttributeValue::where('attribute_id', $generoAttr->id)
                    ->where('value', $item['gender'])
                    ->first();
                if ($val) {
                    $attributeValueIds[] = $val->id;
                }
            }

            if ($materialAttr && isset($item['material'])) {
                $val = AttributeValue::where('attribute_id', $materialAttr->id)
                    ->where('value', $item['material'])
                    ->first();
                if ($val) {
                    $attributeValueIds[] = $val->id;
                }
            }

            if ($corteAttr && isset($item['corte'])) {
                $val = AttributeValue::where('attribute_id', $corteAttr->id)
                    ->where('value', $item['corte'])
                    ->first();
                if ($val) {
                    $attributeValueIds[] = $val->id;
                }
            }

            AttributeValue::with('attribute')->whereKey($attributeValueIds)->get()
                ->each(fn (AttributeValue $value) => $product->characteristics()->firstOrCreate(
                    ['name' => $value->attribute->name],
                    ['attribute_id' => $value->attribute_id, 'value' => $value->value],
                ));
            // 3. Insertar Galería de Imágenes (product_media)
            if (isset($item['images'])) {
                foreach ($item['images'] as $img) {
                    ProductMedia::firstOrCreate(
                        [
                            'product_id' => $product->id,
                            'file_path' => $img['path'],
                        ],
                        [
                            'file_type' => 'image',
                            'is_primary' => $img['is_primary'],
                            'sort_order' => $img['sort'],
                        ]
                    );
                }
            }

            // 4. Insertar Variantes (product_variants)
            foreach ($item['variants'] as $variantGroup) {
                $color = $colors->get($variantGroup['color']);

                foreach ($variantGroup['sizes'] as $sizeName) {
                    $size = $sizes->where('type', $variantGroup['size_type'])->firstWhere('name', $sizeName);

                    if ($color && $size) {
                        ProductVariant::firstOrCreate(
                            [
                                'product_id' => $product->id,
                                'color_id' => $color->id,
                                'size_id' => $size->id,
                            ],
                            [
                                'sku' => strtoupper("{$product->code}-{$color->id}-{$size->name}"),
                                'price' => $product->price,
                                'stock' => $variantGroup['stock'],
                                'is_active' => true,
                            ]
                        );
                    }
                }
            }
        }
    }
}
