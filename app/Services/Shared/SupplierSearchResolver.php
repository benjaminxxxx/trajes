<?php

namespace App\Services\Shared;

use App\Models\Supplier;
use Illuminate\Support\Collection;

class SupplierSearchResolver implements SearchResolver
{
    public function search(string $term): Collection
    {
        return Supplier::query()
            ->where('is_active', true)
            ->where(function ($q) use ($term) {
                $q->where('display_name', 'like', "%{$term}%")
                    ->orWhere('company_name', 'like', "%{$term}%")
                    ->orWhere('names', 'like', "%{$term}%")
                    ->orWhere('paternal_last_name', 'like', "%{$term}%")
                    ->orWhere('document_number', 'like', "%{$term}%");
            })
            ->limit(6)
            ->get()
            ->map(function (Supplier $supplier) {
                $name = $supplier->display_name
                    ?: $supplier->company_name
                    ?: trim("{$supplier->names} {$supplier->paternal_last_name}");

                return SearchResult::fromName(
                    id: $supplier->id,
                    name: $name,
                    subtitle: strtoupper($supplier->document_type).' '.$supplier->document_number,
                );
            });
    }
}