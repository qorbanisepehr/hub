<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (User::exists()) {
            return;
        }

        User::create([
            'name' => 'Administrator',
            'email' => 'admin@local.test',
            'username' => 'admin',
            'phone' => '09371855737',
            'password' => Hash::make('Xx123456'),
        ]);
        User::create([
            'name' => 'Sepehr',
            'email' => 'sepehr@local.test',
            'username' => 'sepehr',
            'phone' => '09371855738',
            'password' => Hash::make('Xx123456'),
        ]);
    }
}
