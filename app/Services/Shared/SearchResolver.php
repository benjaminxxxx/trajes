<?php

namespace App\Services\Shared;

use Illuminate\Support\Collection;

/**
 * Contrato que debe cumplir cualquier "buscador" usado por
 * <livewire:shared.entity-search-select>. El componente no sabe nada de
 * Eloquent ni de qué tabla consulta: solo le pide a la clase registrada en
 * config('search.resolvers') una Collection<SearchResult> para un término.
 */
interface SearchResolver
{
    public function search(string $term): Collection;
}