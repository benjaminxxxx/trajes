<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {

        // 2. Crear o buscar el usuario asignando person_id
        $admin = User::firstOrCreate(
            ['email' => 'benjamin_unitek@hotmail.com'],
            [
                'name' => 'Benjamin',
                'password' => Hash::make('masnaki18'),
                'email_verified_at' => now(),
            ]
        );

        $admin->assignRole('admin');

        $vendedor = User::factory()->create([
            'name' => 'Vendedor',
        ]);
        $vendedor->assignRole('vendedor');
    }
}