<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Size;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategoryAndAttributeSeeder extends Seeder
{
    use RunsInAccount;

    public function run(): void
    {
        $this->ensureAccount();

        // ----------------------------------------------------
        // 1. REGISTRO DE TALLAS POR GRUPOS
        // ----------------------------------------------------
        $sizeData = [
            // Ternos/Trajes Adulto
            ['name' => '34', 'type' => 'terno', 'sort_order' => 10],
            ['name' => '36', 'type' => 'terno', 'sort_order' => 20],
            ['name' => '38', 'type' => 'terno', 'sort_order' => 30],
            ['name' => '40', 'type' => 'terno', 'sort_order' => 40],
            ['name' => '42', 'type' => 'terno', 'sort_order' => 50],
            ['name' => '44', 'type' => 'terno', 'sort_order' => 60],

            // Ropa Estándar (Damas / Camisas)
            ['name' => 'XS', 'type' => 'estandar', 'sort_order' => 100],
            ['name' => 'S',  'type' => 'estandar', 'sort_order' => 110],
            ['name' => 'M',  'type' => 'estandar', 'sort_order' => 120],
            ['name' => 'L',  'type' => 'estandar', 'sort_order' => 130],
            ['name' => 'XL', 'type' => 'estandar', 'sort_order' => 140],

            // Ternos/Ropa Niños
            ['name' => '2',  'type' => 'nino', 'sort_order' => 200],
            ['name' => '4',  'type' => 'nino', 'sort_order' => 210],
            ['name' => '6',  'type' => 'nino', 'sort_order' => 220],
            ['name' => '8',  'type' => 'nino', 'sort_order' => 230],
            ['name' => '10', 'type' => 'nino', 'sort_order' => 240],
            ['name' => '12', 'type' => 'nino', 'sort_order' => 250],
            ['name' => '14', 'type' => 'nino', 'sort_order' => 260],
            ['name' => '16', 'type' => 'nino', 'sort_order' => 270],

            // Calzado Hombre
            ['name' => '39', 'type' => 'calzado_hombre', 'sort_order' => 300],
            ['name' => '40', 'type' => 'calzado_hombre', 'sort_order' => 310],
            ['name' => '41', 'type' => 'calzado_hombre', 'sort_order' => 320],
            ['name' => '42', 'type' => 'calzado_hombre', 'sort_order' => 330],
            ['name' => '43', 'type' => 'calzado_hombre', 'sort_order' => 340],

            // Calzado Mujer
            ['name' => '35', 'type' => 'calzado_mujer', 'sort_order' => 400],
            ['name' => '36', 'type' => 'calzado_mujer', 'sort_order' => 410],
            ['name' => '37', 'type' => 'calzado_mujer', 'sort_order' => 420],
            ['name' => '38', 'type' => 'calzado_mujer', 'sort_order' => 430],

            // Calzado Niños
            ['name' => '22', 'type' => 'calzado_nino', 'sort_order' => 500],
            ['name' => '24', 'type' => 'calzado_nino', 'sort_order' => 510],
            ['name' => '26', 'type' => 'calzado_nino', 'sort_order' => 520],
            ['name' => '28', 'type' => 'calzado_nino', 'sort_order' => 530],
            ['name' => '30', 'type' => 'calzado_nino', 'sort_order' => 540],
            ['name' => '32', 'type' => 'calzado_nino', 'sort_order' => 550],
            ['name' => '34', 'type' => 'calzado_nino', 'sort_order' => 560],
        ];

        $sizes = [];
        foreach ($sizeData as $data) {
            $sizes[$data['name'].'_'.$data['type']] = Size::create($data);
        }

        // Helper para mapear IDs de tallas rápido
        $getSizes = function (array $keys) use ($sizes) {
            return collect($keys)->map(fn ($key) => $sizes[$key]->id)->toArray();
        };

        // ----------------------------------------------------
        // 2. REGISTRO DE COLORES
        // ----------------------------------------------------
        $colorData = [
            'negro' => ['name' => 'Negro', 'hex_code' => '#000000'],
            'azul_noche' => ['name' => 'Azul Noche', 'hex_code' => '#000080'],
            'azul_marino' => ['name' => 'Azul Marino', 'hex_code' => '#1B263B'],
            'gris_oscuro' => ['name' => 'Gris Oscuro', 'hex_code' => '#3B3B3B'],
            'gris_claro' => ['name' => 'Gris Claro', 'hex_code' => '#D3D3D3'],
            'blanco' => ['name' => 'Blanco', 'hex_code' => '#FFFFFF'],
            'marfil' => ['name' => 'Marfil / Blanco Humo', 'hex_code' => '#F5F5DC'],
            'vino' => ['name' => 'Vino / Guinda', 'hex_code' => '#800020'],
            'rojo' => ['name' => 'Rojo', 'hex_code' => '#FF0000'],
            'rosa_palo' => ['name' => 'Rosa Palo', 'hex_code' => '#FFD1DC'],
            'esmeralda' => ['name' => 'Verde Esmeralda', 'hex_code' => '#008055'],
            'dorado' => ['name' => 'Dorado', 'hex_code' => '#FFD700'],
            'plateado' => ['name' => 'Plateado', 'hex_code' => '#C0C0C0'],
            'beige' => ['name' => 'Beige / Champán', 'hex_code' => '#F7E7CE'],
            'marrón' => ['name' => 'Marrón / Suela', 'hex_code' => '#6E2C00'],
        ];

        $colors = [];
        foreach ($colorData as $key => $data) {
            $colors[$key] = Color::create($data);
        }

        $getColors = function (array $keys) use ($colors) {
            return collect($keys)->map(fn ($key) => $colors[$key]->id)->toArray();
        };

        // ----------------------------------------------------
        // 3. CATEGORÍAS ESENCIALES Y ASIGNACIÓN DE TALLAS/COLORES
        // ----------------------------------------------------
        $categories = [
            [
                'name' => 'Ternos y Esmóquines',
                'description' => 'Trajes formales de etiqueta para caballeros (Matrimonios, Promociones, Galas).',
                'sort_order' => 1,
                'sizes' => $getSizes(['34_terno', '36_terno', '38_terno', '40_terno', '42_terno', '44_terno']),
                'colors' => $getColors(['negro', 'azul_noche', 'azul_marino', 'gris_oscuro', 'gris_claro', 'vino', 'beige']),
            ],
            [
                'name' => 'Vestidos de Dama',
                'description' => 'Vestidos elegantes de noche, gala, fiesta y novias.',
                'sort_order' => 2,
                'sizes' => $getSizes(['XS_estandar', 'S_estandar', 'M_estandar', 'L_estandar', 'XL_estandar']),
                'colors' => $getColors(['negro', 'azul_noche', 'vino', 'rojo', 'rosa_palo', 'esmeralda', 'dorado', 'plateado', 'blanco', 'marfil']),
            ],
            [
                'name' => 'Trajes de Niño',
                'description' => 'Ternos pequeños para pajes, bautizos y primera comunión.',
                'sort_order' => 3,
                'sizes' => $getSizes(['2_nino', '4_nino', '6_nino', '8_nino', '10_nino', '12_nino', '14_nino', '16_nino']),
                'colors' => $getColors(['negro', 'azul_marino', 'gris_claro', 'blanco', 'beige']),
            ],
            [
                'name' => 'Vestidos de Niña',
                'description' => 'Vestidos de fiesta, pajesitas y primera comunión.',
                'sort_order' => 4,
                'sizes' => $getSizes(['2_nino', '4_nino', '6_nino', '8_nino', '10_nino', '12_nino', '14_nino', '16_nino']),
                'colors' => $getColors(['blanco', 'marfil', 'rosa_palo', 'beige', 'rojo']),
            ],
            [
                'name' => 'Ropa Blanca y Camisas',
                'description' => 'Camisas formales, cuellos pajarita y guayaberas para venta y alquiler.',
                'sort_order' => 5,
                'sizes' => $getSizes(['S_estandar', 'M_estandar', 'L_estandar', 'XL_estandar']),
                'colors' => $getColors(['blanco', 'marfil', 'black' => 'negro']),
            ],
            [
                'name' => 'Calzado de Varón',
                'description' => 'Zapatos de vestir formales, de charol y cuero.',
                'sort_order' => 6,
                'sizes' => $getSizes(['39_calzado_hombre', '40_calzado_hombre', '41_calzado_hombre', '42_calzado_hombre', '43_calzado_hombre']),
                'colors' => $getColors(['negro', 'marrón']),
            ],
            [
                'name' => 'Calzado de Dama',
                'description' => 'Tacos y calzado de gala.',
                'sort_order' => 7,
                'sizes' => $getSizes(['35_calzado_mujer', '36_calzado_mujer', '37_calzado_mujer', '38_calzado_mujer']),
                'colors' => $getColors(['negro', 'plateado', 'dorado', 'beige']),
            ],
            [
                'name' => 'Calzado Infantil',
                'description' => 'Zapatos de fiesta y vestir para niños y niñas.',
                'sort_order' => 8,
                'sizes' => $getSizes(['22_calzado_nino', '24_calzado_nino', '26_calzado_nino', '28_calzado_nino', '30_calzado_nino', '32_calzado_nino', '34_calzado_nino']),
                'colors' => $getColors(['negro', 'blanco', 'marrón', 'beig' => 'beige']),
            ],
            [
                'name' => 'Accesorios',
                'description' => 'Corbatas, corbatines/michi, tirantes, correas y gemelos.',
                'sort_order' => 9,
                'sizes' => [], // Los accesorios por lo general son talla única
                'colors' => $getColors(['negro', 'azul_noche', 'vino', 'rojo', 'dorado', 'plateado']),
            ],
        ];

        foreach ($categories as $catData) {
            $sizesToAttach = $catData['sizes'];
            $colorsToAttach = $catData['colors'];

            unset($catData['sizes'], $catData['colors']);

            $catData['slug'] = Str::slug($catData['name']);
            $category = Category::create($catData);

            // Relacionamos las tallas y colores permitidos para la categoría
            $category->sizes()->sync($sizesToAttach);
            $category->colors()->sync($colorsToAttach);
        }

        // ----------------------------------------------------
        // 4. MARCAS Y ATRIBUTOS DE FICHA TÉCNICA
        // ----------------------------------------------------
        $brands = ['Confección Propia', 'Barrington', 'Pierre Cardin', 'Zara', 'Importado'];
        foreach ($brands as $brand) {
            Brand::create(['name' => $brand, 'slug' => Str::slug($brand)]);
        }

        // Características que se piden al registrar el producto (solo sugerencias:
        // el producto guarda el texto). Sin categorías = se piden en todas.
        $clothing = ['Ternos y Esmóquines', 'Vestidos de Dama', 'Trajes de Niño', 'Vestidos de Niña', 'Ropa Blanca y Camisas'];
        $shoes = ['Calzado de Varón', 'Calzado de Dama', 'Calzado Infantil'];

        $attributes = [
            [
                'name' => 'Tela',
                'categories' => $clothing,
                'values' => ['100% Lana', 'Lana Poliéster (Barrington)', 'Seda', 'Satín', 'Terciopelo / Velvet', 'Algodón', 'Gasa', 'Tul'],
            ],
            [
                'name' => 'Corte',
                'categories' => $clothing,
                'values' => ['Slim Fit', 'Super Slim Fit', 'Clásico / Regular', 'Sesgo', 'Sirena', 'Princesa'],
            ],
            [
                'name' => 'Cuero',
                'categories' => $shoes,
                'values' => ['Cuero liso', 'Charol', 'Gamuza', 'Sintético'],
            ],
        ];

        foreach ($attributes as $attr) {
            $attribute = Attribute::create(['name' => $attr['name']]);
            $attribute->categories()->sync(Category::whereIn('name', $attr['categories'])->pluck('id'));

            foreach ($attr['values'] as $val) {
                AttributeValue::create([
                    'attribute_id' => $attribute->id,
                    'value' => $val,
                ]);
            }
        }
    }
}
