<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@jupiterbooks.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('Admin1234!'),
                'status' => 'active',
                'register_date' => now(),
            ]
        );

        $adminRole = Role::where('name', 'admin')->first();

        if ($adminRole) {
            // Evita duplicados en la tabla pivote
            $admin->roles()->syncWithoutDetaching([$adminRole->role_id]);
        }
    }
}
