<?php

use App\Services\Shared\ClientSearchResolver;
use App\Services\Shared\ProductSearchResolver;
use App\Services\Shared\SupplierSearchResolver;

return [

    /*
    |--------------------------------------------------------------------------
    | Resolvers de búsqueda
    |--------------------------------------------------------------------------
    |
    | Cada entrada mapea el `entityType` que le pasas a
    | <livewire:shared.entity-search-select> a una clase que implementa
    | App\Services\Shared\SearchResolver. Para agregar una entidad nueva
    | (ej. "employee", "mining_labor") solo crea su resolver en
    | App\Services\Shared y agrégalo aquí — no toques el componente.
    |
    */
    'resolvers' => [
        'supplier' => SupplierSearchResolver::class,
        'product' => ProductSearchResolver::class,
        'client' => ClientSearchResolver::class
    ],

];