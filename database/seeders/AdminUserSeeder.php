<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the application's database with a default admin user.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'lucas@zacharias-net.de'],
            [
                'name' => 'Lucas Zacharias',
                'password' => Hash::make('Caludalu1991!'),
                'role' => 'admin',
                'status' => true,
                'profile_photo_path' => '',
                'email_verified_at' => now(),
            ]
        );
    }
}
