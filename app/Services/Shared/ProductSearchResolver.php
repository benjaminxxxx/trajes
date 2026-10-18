<?php

namespace App\Services\Shared;

use App\Models\Product;
use Illuminate\Support\Collection;

class ProductSearchResolver implements SearchResolver
{
    public function search(string $term): Collection
    {
        return Product::query()
            ->where('is_active', true)
            ->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%");
            })
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
