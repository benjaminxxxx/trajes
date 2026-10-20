<?php

namespace App\Services\Shared;

use App\Models\Product;
use App\Services\Inventory\ProductSearch;
use Illuminate\Support\Collection;

class ProductSearchResolver implements SearchResolver
{
    public function search(string $term): Collection
    {
        return Product::query()
            ->where('is_active', true)
            // Mientras se escribe ("ter...") también vale el nombre o código parcial.
            ->where(fn ($q) => $q->where('name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%")
                ->orWhere(fn ($words) => app(ProductSearch::class)->apply($words, $term)))
            ->limit(6)
            ->get()
            ->map(fn (Product $product) => SearchResult::fromName(
                id: $product->id,
                name: $product->name,
                subtitle: $product->code,
                badgeColor: 'bg-cyan-100 text-cyan-700 dark:bg-cyan-950/60 dark:text-cyan-300',
                meta: [
                    'price' => (float) $product->price,
                    'purchase_price' => (float) $product->purchase_price,
                    'category_id' => $product->category_id,
                ],
            ));
    }
}
