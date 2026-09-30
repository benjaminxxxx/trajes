<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Services\Core\CurrentAccount;

/**
 * Los datos del negocio (productos, clientes, bienes...) siempre pertenecen a
 * un negocio. Si el seeder se corre solo (db:seed --class=...) y nadie fijó
 * uno, se usa el primer negocio registrado.
 */
trait RunsInAccount
{
    protected function ensureAccount(): void
    {
        $currentAccount = app(CurrentAccount::class);

        if ($currentAccount->id()) {
            return;
        }

        $account = Account::orderBy('id')->first()
            ?? throw new \RuntimeException('No hay ningún negocio registrado: corre primero UserSeeder.');

        $currentAccount->set($account);
    }
}
