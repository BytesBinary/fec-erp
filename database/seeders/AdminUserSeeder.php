<?php

namespace Database\Seeders;

use App\Enums\RoleKey;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@fec.edu.bd'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('secure@123'),
                'email_verified_at' => now(),
            ]
        );

        $admin->assignRole(Role::findOrCreate(RoleKey::SuperAdmin->value, 'web'));
    }
}
