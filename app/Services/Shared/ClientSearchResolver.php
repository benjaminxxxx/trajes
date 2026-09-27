<?php

namespace App\Services\Shared;

use App\Models\Client;
use Illuminate\Support\Collection;

class ClientSearchResolver implements SearchResolver
{
    public function search(string $term): Collection
    {
        return Client::query()
            ->where(function ($q) use ($term) {
                $q->where('names', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('company_name', 'like', "%{$term}%")
                    ->orWhere('document_number', 'like', "%{$term}%");
            })
            ->limit(6)
            ->get()
            ->map(function (Client $client) {
                $name = $client->type === 'company'
                    ? $client->company_name
                    : trim("{$client->names} {$client->last_name}");

                return SearchResult::fromName(
                    id: $client->id,
                    name: $name ?: "Cliente #{$client->id}",
                    subtitle: strtoupper($client->document_type).' '.$client->document_number,
                    badgeColor: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300',
                );
            });
    }
}