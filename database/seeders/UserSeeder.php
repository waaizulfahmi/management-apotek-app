<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\User::create([
            'name' => 'Administrator',
            'username' => 'admin',
            'email' => 'admin@apotek.com',
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
            'role' => 'admin',
            'no_hp' => '081234567890',
        ]);

        \App\Models\User::create([
            'name' => 'Kasir 1',
            'username' => 'kasir',
            'email' => 'kasir@apotek.com',
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
            'role' => 'kasir',
            'no_hp' => '081234567891',
        ]);

        \App\Models\User::create([
            'name' => 'Pemilik',
            'username' => 'owner',
            'email' => 'owner@apotek.com',
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
            'role' => 'owner',
            'no_hp' => '081234567892',
        ]);
    }
}
