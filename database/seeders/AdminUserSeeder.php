<?php
namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@budgetra.com'],
            [
                'full_name'     => 'Budgetra Admin',
                'password'      => Hash::make('password'),
                'role'          => 'admin',
                'currency_code' => 'PHP',
                'currency_symbol' => '₱',
            ]
        );

        User::updateOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'full_name'     => 'Super Admin',
                'password'      => Hash::make('testadmin123'),
                'role'          => 'admin',
                'currency_code' => 'PHP',
                'currency_symbol' => '₱',
            ]
        );
    }
}
