<?php

namespace Database\Seeders;

use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\Supplier;
use App\Services\Inventory\ProductImageOptimizer;
use App\Services\Purchasing\PurchaseService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Catálogo de demostración: ternos (adulto y niño), vestidos de dama y niña,
 * calzado y pantalones palazo, con fotos reales de Wikimedia Commons
 * (ver database/seeders/images/credits.json para autor y licencia).
 *
 * El stock no se inventa: cada producto entra con una compra registrada por
 * PurchaseService, así los alquilables quedan con sus prendas físicas y
 * todos con su costo promedio ponderado. Requiere CategoryAndAttributeSeeder.
 */
class ProductCatalogSeeder extends Seeder
{
    use RunsInAccount;

    /**
     * Categoría => tipo de talla que usa (sizes.type).
     */
    protected const SIZE_TYPES = [
        'Ternos y Esmóquines' => 'terno',
        'Trajes de Niño' => 'nino',
        'Vestidos de Dama' => 'estandar',
        'Vestidos de Niña' => 'nino',
        'Calzado de Varón' => 'calzado_hombre',
        'Calzado de Dama' => 'calzado_mujer',
        'Calzado Infantil' => 'calzado_nino',
        'Pantalones Palazo' => 'estandar',
    ];

    public function run(ProductImageOptimizer $optimizer, PurchaseService $purchases): void
    {
        $this->ensureAccount();

        if (Category::where('name', 'Ternos y Esmóquines')->doesntExist()) {
            $this->command?->warn('Ejecuta primero CategoryAndAttributeSeeder.');

            return;
        }

        $this->ensurePalazzoCategory();

        $supplier = Supplier::firstOrCreate(
            ['document_type' => 'RUC', 'document_number' => '20512345678'],
            [
                'code' => 'PROV-GAMARRA',
                'type' => 'company',
                'company_name' => 'Confecciones Gamarra S.A.C.',
                'display_name' => 'Confecciones Gamarra S.A.C.',
                'address' => 'Jr. Gamarra 1024, La Victoria, Lima',
                'is_active' => true,
            ]
        );

        $lines = [];

        foreach ($this->catalog() as $item) {
            if (Product::where('code', $item['code'])->exists()) {
                continue;
            }

            $product = $this->createProduct($item);
            $this->attachImages($product, $item['images'] ?? [], $optimizer);

            foreach ($this->createVariants($product, $item) as $variant) {
                $lines[] = [
                    'product_id' => $product->id,
                    'size_id' => $variant->size_id,
                    'color_id' => $variant->color_id,
                    'quantity' => $item['units_per_variant'],
                    'unit_price' => $item['cost'],
                    'discount_percent' => 0,
                ];
            }
        }

        if ($lines === []) {
            $this->command?->info('El catálogo de demostración ya estaba cargado.');

            return;
        }

        $purchase = $purchases->register([
            'supplier_id' => $supplier->id,
            'document_type' => 'factura',
            'document_number' => 'F001-'.str_pad((string) random_int(1, 99999), 6, '0', STR_PAD_LEFT),
            'issue_date' => now()->subDays(15)->toDateString(),
            'notes' => 'Compra inicial del catálogo de demostración.',
        ], $lines);

        $this->command?->info("Catálogo cargado: {$purchase->details()->count()} variantes en la compra #{$purchase->id}.");
    }

    protected function ensurePalazzoCategory(): void
    {
        $category = Category::firstOrCreate(
            ['slug' => 'pantalones-palazo'],
            [
                'name' => 'Pantalones Palazo',
                'description' => 'Pantalones palazo de vestir, de pierna ancha y caída fluida.',
                'sort_order' => 10,
                'is_active' => true,
            ]
        );

        $category->sizes()->syncWithoutDetaching(
            Size::where('type', 'estandar')->whereIn('name', ['XS', 'S', 'M', 'L', 'XL'])->pluck('id')
        );
        $category->colors()->syncWithoutDetaching(
            Color::whereIn('name', ['Negro', 'Beige / Champán', 'Blanco', 'Vino / Guinda', 'Azul Noche'])->pluck('id')
        );
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function createProduct(array $item): Product
    {
        $product = Product::create([
            'category_id' => Category::where('name', $item['category'])->value('id'),
            'brand_id' => Brand::firstOrCreate(['slug' => Str::slug($item['brand'])], ['name' => $item['brand']])->id,
            'code' => $item['code'],
            'name' => $item['name'],
            'slug' => Str::slug($item['name']).'-'.Str::lower($item['code']),
            'description' => $item['description'],
            'price' => $item['sale_price'],
            'gender' => $item['gender'],
            'allow_sale' => true,
            'allow_rental' => $item['rental_price'] !== null,
            'is_active' => true,
        ]);

        $product->attributeValues()->sync(
            AttributeValue::whereIn('value', $item['attributes'] ?? [])->pluck('id')
        );

        return $product;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<int, ProductVariant>
     */
    protected function createVariants(Product $product, array $item): array
    {
        $sizes = Size::where('type', self::SIZE_TYPES[$item['category']])
            ->whereIn('name', $item['sizes'])
            ->get();
        $colors = Color::whereIn('name', $item['colors'])->get();
        $variants = [];

        foreach ($colors as $color) {
            foreach ($sizes as $size) {
                $variants[] = ProductVariant::create([
                    'product_id' => $product->id,
                    'color_id' => $color->id,
                    'size_id' => $size->id,
                    'sku' => strtoupper("{$product->code}-{$size->name}-C{$color->id}"),
                    'sale_price' => $item['sale_price'],
                    'rental_price' => $item['rental_price'],
                    'stock' => 0,
                    'is_active' => true,
                ]);
            }
        }

        return $variants;
    }

    /**
     * Reduce cada foto con Intervention y la guarda en storage/app/public/products/{id}.
     *
     * @param  array<int, string>  $images
     */
    protected function attachImages(Product $product, array $images, ProductImageOptimizer $optimizer): void
    {
        foreach (array_values($images) as $index => $filename) {
            $source = database_path("seeders/images/{$filename}");

            if (! is_file($source)) {
                $this->command?->warn("Imagen no encontrada: {$filename}");

                continue;
            }

            ProductMedia::create([
                'product_id' => $product->id,
                'file_path' => $optimizer->storeFromPath($source, "accounts/{$product->account_id}/products/{$product->id}", $filename),
                'file_type' => 'image',
                'sort_order' => $index + 1,
                'is_primary' => $index === 0,
            ]);
        }
    }

    /**
     * rental_price = null significa "solo venta" (sin prendas físicas).
     * cost = precio de compra por unidad, sin IGV.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function catalog(): array
    {
        return [
            // ---------------- Ternos de caballero ----------------
            [
                'code' => 'TRN-BAR-001', 'name' => 'Terno Barrington Glen Check Cruzado', 'brand' => 'Barrington',
                'category' => 'Ternos y Esmóquines', 'gender' => 'masculino',
                'description' => 'Terno cruzado de cuadros Príncipe de Gales, corte slim. Ideal para matrimonios de día.',
                'colors' => ['Gris Claro', 'Gris Oscuro'], 'sizes' => ['38', '40', '42'],
                'cost' => 280, 'sale_price' => 699, 'rental_price' => 120, 'units_per_variant' => 2,
                'attributes' => ['Lana Poliéster (Barrington)', 'Slim Fit'],
                'images' => ['terno-glen-check.jpg'],
            ],
            [
                'code' => 'TRN-PC-002', 'name' => 'Terno Pierre Cardin Tres Piezas', 'brand' => 'Pierre Cardin',
                'category' => 'Ternos y Esmóquines', 'gender' => 'masculino',
                'description' => 'Saco, chaleco y pantalón en lana fina. Clásico para graduaciones y galas.',
                'colors' => ['Azul Marino', 'Negro'], 'sizes' => ['40', '42', '44'],
                'cost' => 350, 'sale_price' => 899, 'rental_price' => 150, 'units_per_variant' => 2,
                'attributes' => ['100% Lana', 'Clásico / Regular'],
                'images' => ['terno-tres-piezas.jpg'],
            ],
            [
                'code' => 'TRN-CP-003', 'name' => 'Terno Tweed Tres Piezas', 'brand' => 'Confección Propia',
                'category' => 'Ternos y Esmóquines', 'gender' => 'masculino',
                'description' => 'Terno de tweed a cuadros con chaleco forrado en satén, confeccionado en Gamarra.',
                'colors' => ['Gris Oscuro', 'Beige / Champán'], 'sizes' => ['38', '40', '42'],
                'cost' => 220, 'sale_price' => 549, 'rental_price' => 100, 'units_per_variant' => 2,
                'attributes' => ['100% Lana', 'Clásico / Regular'],
                'images' => ['terno-clasico-lana.jpg'],
            ],
            [
                'code' => 'ESM-IMP-004', 'name' => 'Esmoquin de Terciopelo Vino', 'brand' => 'Importado',
                'category' => 'Ternos y Esmóquines', 'gender' => 'masculino',
                'description' => 'Saco de terciopelo con solapa de raso negro y corbatín. Para fiestas de gala y promociones.',
                'colors' => ['Vino / Guinda', 'Negro'], 'sizes' => ['38', '40', '42'],
                'cost' => 400, 'sale_price' => 1099, 'rental_price' => 180, 'units_per_variant' => 1,
                'attributes' => ['Terciopelo / Velvet', 'Slim Fit'],
                'images' => ['esmoquin-negro.jpg'],
            ],

            // ---------------- Ternos de niño ----------------
            [
                'code' => 'TNN-CP-001', 'name' => 'Terno de Paje con Chaleco', 'brand' => 'Confección Propia',
                'category' => 'Trajes de Niño', 'gender' => 'nino',
                'description' => 'Conjunto de paje con chaleco, camisa y corbatín, para bautizos y matrimonios.',
                'colors' => ['Blanco', 'Beige / Champán'], 'sizes' => ['2', '4', '6'],
                'cost' => 70, 'sale_price' => 189, 'rental_price' => 45, 'units_per_variant' => 2,
                'attributes' => ['Algodón'],
                'images' => ['terno-paje-nino.jpg'],
            ],
            [
                'code' => 'TNN-CP-002', 'name' => 'Terno Niño Frac Negro', 'brand' => 'Confección Propia',
                'category' => 'Trajes de Niño', 'gender' => 'nino',
                'description' => 'Frac infantil negro con camisa blanca, perfecto para el paje de anillos.',
                'colors' => ['Negro'], 'sizes' => ['4', '6', '8'],
                'cost' => 95, 'sale_price' => 249, 'rental_price' => 60, 'units_per_variant' => 2,
                'attributes' => ['Lana Poliéster (Barrington)'],
                'images' => ['terno-nino-anillos.jpg'],
            ],
            [
                'code' => 'TNN-ZAR-003', 'name' => 'Terno Niño Primera Comunión', 'brand' => 'Zara',
                'category' => 'Trajes de Niño', 'gender' => 'nino',
                'description' => 'Terno de dos piezas para primera comunión y graduaciones escolares.',
                'colors' => ['Negro', 'Azul Marino'], 'sizes' => ['8', '10', '12'],
                'cost' => 110, 'sale_price' => 279, 'rental_price' => 65, 'units_per_variant' => 2,
                'attributes' => ['Lana Poliéster (Barrington)', 'Slim Fit'],
                'images' => ['terno-maniqui-corbata.jpg'],
            ],

            // ---------------- Vestidos de dama (nombres de Gamarra) ----------------
            [
                'code' => 'VST-001', 'name' => 'Vestido Fiorela', 'brand' => 'Confección Propia',
                'category' => 'Vestidos de Dama', 'gender' => 'femenino',
                'description' => 'Vestido largo strapless con drapeado en el busto y caída en raso.',
                'colors' => ['Negro', 'Vino / Guinda'], 'sizes' => ['S', 'M', 'L'],
                'cost' => 120, 'sale_price' => 329, 'rental_price' => 90, 'units_per_variant' => 2,
                'attributes' => ['Raso / Satén'],
                'images' => ['vestido-fiorela.jpg'],
            ],
            [
                'code' => 'VST-002', 'name' => 'Vestido de Quinceañera', 'brand' => 'Confección Propia',
                'category' => 'Vestidos de Dama', 'gender' => 'femenino',
                'description' => 'Vestido de quinceañera strapless con falda amplia y bordados en pedrería.',
                'colors' => ['Verde Esmeralda', 'Rosa Palo'], 'sizes' => ['S', 'M'],
                'cost' => 260, 'sale_price' => 699, 'rental_price' => 180, 'units_per_variant' => 2,
                'attributes' => ['Raso / Satén'],
                'images' => ['vestido-quinceanera.jpg'],
            ],
            [
                'code' => 'VST-003', 'name' => 'Vestido Victoria', 'brand' => 'Importado',
                'category' => 'Vestidos de Dama', 'gender' => 'femenino',
                'description' => 'Vestido de novia de manga larga con encaje y cola, velo incluido.',
                'colors' => ['Blanco', 'Marfil / Blanco Humo'], 'sizes' => ['S', 'M', 'L'],
                'cost' => 450, 'sale_price' => 1290, 'rental_price' => 350, 'units_per_variant' => 1,
                'attributes' => ['Seda'],
                'images' => ['vestido-victoria.jpg'],
            ],
            [
                'code' => 'VST-004', 'name' => 'Vestido Isabella', 'brand' => 'Confección Propia',
                'category' => 'Vestidos de Dama', 'gender' => 'femenino',
                'description' => 'Vestido de noche de talle alto, estilo clásico, para cenas de gala.',
                'colors' => ['Azul Noche', 'Verde Esmeralda'], 'sizes' => ['S', 'M', 'L'],
                'cost' => 140, 'sale_price' => 379, 'rental_price' => 100, 'units_per_variant' => 2,
                'attributes' => ['Seda'],
                'images' => ['vestido-isabella.jpg'],
            ],
            [
                'code' => 'VST-005', 'name' => 'Vestido Camila', 'brand' => 'Zara',
                'category' => 'Vestidos de Dama', 'gender' => 'femenino',
                'description' => 'Vestido midi de fiesta con escote en V y abertura lateral.',
                'colors' => ['Rojo', 'Plateado'], 'sizes' => ['XS', 'S', 'M'],
                'cost' => 110, 'sale_price' => 289, 'rental_price' => 80, 'units_per_variant' => 2,
                'attributes' => ['Raso / Satén'],
                'images' => [],
            ],

            // ---------------- Vestidos de niña ----------------
            [
                'code' => 'VNA-001', 'name' => 'Vestido Princesa Niña', 'brand' => 'Confección Propia',
                'category' => 'Vestidos de Niña', 'gender' => 'nino',
                'description' => 'Vestido de fiesta de falda amplia con puños de encaje.',
                'colors' => ['Beige / Champán', 'Rosa Palo'], 'sizes' => ['4', '6', '8'],
                'cost' => 65, 'sale_price' => 179, 'rental_price' => 45, 'units_per_variant' => 2,
                'attributes' => ['Raso / Satén'],
                'images' => ['vestido-nina-princesa.jpg'],
            ],
            [
                'code' => 'VNA-002', 'name' => 'Vestido de Pajecita', 'brand' => 'Confección Propia',
                'category' => 'Vestidos de Niña', 'gender' => 'nino',
                'description' => 'Vestido largo de pajecita con lazo y detalles de encaje.',
                'colors' => ['Marfil / Blanco Humo', 'Blanco'], 'sizes' => ['2', '4', '6'],
                'cost' => 60, 'sale_price' => 159, 'rental_price' => 40, 'units_per_variant' => 2,
                'attributes' => ['Algodón'],
                'images' => ['vestido-nina-pajesita.jpg'],
            ],
            [
                'code' => 'VNA-003', 'name' => 'Vestido de Primera Comunión', 'brand' => 'Confección Propia',
                'category' => 'Vestidos de Niña', 'gender' => 'nino',
                'description' => 'Vestido blanco de primera comunión con tul y corona de flores.',
                'colors' => ['Blanco'], 'sizes' => ['6', '8', '10'],
                'cost' => 80, 'sale_price' => 219, 'rental_price' => 55, 'units_per_variant' => 2,
                'attributes' => ['Raso / Satén'],
                'images' => [],
            ],

            // ---------------- Calzado (solo venta) ----------------
            [
                'code' => 'ZAP-V-001', 'name' => 'Zapato Oxford de Cuero Negro', 'brand' => 'Importado',
                'category' => 'Calzado de Varón', 'gender' => 'masculino',
                'description' => 'Zapato Oxford de cuero liso con puntera, suela de cuero.',
                'colors' => ['Negro'], 'sizes' => ['39', '40', '41', '42', '43'],
                'cost' => 95, 'sale_price' => 229, 'rental_price' => null, 'units_per_variant' => 3,
                'images' => ['zapato-oxford-negro.jpg'],
            ],
            [
                'code' => 'ZAP-V-002', 'name' => 'Zapato Oxford Suela', 'brand' => 'Confección Propia',
                'category' => 'Calzado de Varón', 'gender' => 'masculino',
                'description' => 'Oxford en cuero marrón suela, para ternos beige y grises.',
                'colors' => ['Marrón / Suela'], 'sizes' => ['40', '41', '42'],
                'cost' => 85, 'sale_price' => 199, 'rental_price' => null, 'units_per_variant' => 3,
                'images' => ['zapato-oxford-marron.jpg'],
            ],
            [
                'code' => 'ZAP-D-001', 'name' => 'Zapato de Taco Fiesta', 'brand' => 'Importado',
                'category' => 'Calzado de Dama', 'gender' => 'femenino',
                'description' => 'Zapato de taco aguja de 10 cm, acabado brillante.',
                'colors' => ['Negro', 'Plateado', 'Dorado'], 'sizes' => ['35', '36', '37', '38'],
                'cost' => 70, 'sale_price' => 169, 'rental_price' => null, 'units_per_variant' => 2,
                'images' => ['zapato-taco-dama.jpg'],
            ],
            [
                'code' => 'ZAP-N-001', 'name' => 'Zapato de Vestir Niño', 'brand' => 'Confección Propia',
                'category' => 'Calzado Infantil', 'gender' => 'nino',
                'description' => 'Zapato de vestir con pasador para pajes y primera comunión.',
                'colors' => ['Negro', 'Blanco'], 'sizes' => ['26', '28', '30', '32'],
                'cost' => 45, 'sale_price' => 109, 'rental_price' => null, 'units_per_variant' => 2,
                'images' => ['zapato-nino-vestir.jpg'],
            ],

            // ---------------- Pantalones palazo (solo venta) ----------------
            [
                'code' => 'PLZ-001', 'name' => 'Pantalón Palazo Clásico', 'brand' => 'Confección Propia',
                'category' => 'Pantalones Palazo', 'gender' => 'femenino',
                'description' => 'Palazo de tiro alto con pinzas y pierna ancha, tela crepé.',
                'colors' => ['Negro', 'Beige / Champán'], 'sizes' => ['S', 'M', 'L'],
                'cost' => 38, 'sale_price' => 99, 'rental_price' => null, 'units_per_variant' => 4,
                'images' => ['palazo-clasico.jpg'],
            ],
            [
                'code' => 'PLZ-002', 'name' => 'Pantalón Palazo Fluido', 'brand' => 'Zara',
                'category' => 'Pantalones Palazo', 'gender' => 'femenino',
                'description' => 'Palazo de caída fluida con pretina elástica, ideal para eventos de noche.',
                'colors' => ['Vino / Guinda', 'Azul Noche'], 'sizes' => ['S', 'M', 'L'],
                'cost' => 42, 'sale_price' => 119, 'rental_price' => null, 'units_per_variant' => 3,
                'images' => [],
            ],
        ];
    }
}
