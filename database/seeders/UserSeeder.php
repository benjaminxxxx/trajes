<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\User;
use App\Services\Core\AccountService;
use App\Services\Core\CurrentAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Dueño y vendedor de ejemplo, con el negocio de ejemplo del que son miembros.
 * Deja ese negocio como el actual para los seeders que vienen después.
 */
class UserSeeder extends Seeder
{
    public function run(AccountService $accounts): void
    {
        $owner = User::firstOrCreate(
            ['email' => 'benjamin_unitek@hotmail.com'],
            [
                'name' => 'Benjamin',
                'password' => Hash::make('masnaki18'),
                'email_verified_at' => now(),
            ]
        );

        $account = Account::where('owner_id', $owner->id)->first()
            ?? $accounts->createAccount($owner, [
                'name' => config('business.name'),
                'ruc' => config('business.ruc'),
                'address' => config('business.address'),
                'phone' => config('business.phone'),
            ], Account::STATUS_ACTIVE);

        $seller = User::factory()->create(['name' => 'Vendedor']);
        $accounts->addMember($account, $seller, 'vendedor');
        $seller->forceFill(['current_account_id' => $account->id])->save();

        app(CurrentAccount::class)->set($account);
    }
}
