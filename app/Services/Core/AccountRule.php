<?php

namespace App\Services\Core;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

/**
 * Reglas de validación limitadas al negocio actual. Las reglas `exists:` y
 * `unique:` consultan la base directamente (sin Eloquent), así que sin esto un
 * formulario aceptaría el id de un cliente de otro negocio.
 */
class AccountRule
{
    public static function exists(string $table, string $column = 'id'): Exists
    {
        return Rule::exists($table, $column)->where('account_id', app(CurrentAccount::class)->id());
    }

    public static function unique(string $table, string $column): Unique
    {
        return Rule::unique($table, $column)->where('account_id', app(CurrentAccount::class)->id());
    }
}
