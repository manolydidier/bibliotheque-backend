<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UsersTableSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('users')->insert([
            [
                'email' => 'superadmin@example.com',
                'username' => 'superadmin',
                'password' => bcrypt('supersecure'),
                'first_name' => 'Alice',
                'last_name' => 'Rakoto',
                'phone' => '0341234567',
                'address' => 'Antananarivo, Madagascar',
                'date_of_birth' => '1990-01-01',
                'avatar_url' => null,
                'is_active' => true,
                'email_verified' => true,
                'last_login' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'email' => 'biblio@example.com',
                'username' => 'biblio',
                'password' => bcrypt('password'),
                'first_name' => 'Jean',
                'last_name' => 'Andriamampionona',
                'phone' => '0331234567',
                'address' => 'Fianarantsoa',
                'date_of_birth' => '1985-06-15',
                'avatar_url' => null,
                'is_active' => true,
                'email_verified' => false,
                'last_login' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'email' => 'membre@example.com',
                'username' => 'membre',
                'password' => bcrypt('123456'),
                'first_name' => 'Lova',
                'last_name' => 'Ramanantsoa',
                'phone' => '0321234567',
                'address' => 'Mahajanga',
                'date_of_birth' => '2000-12-20',
                'avatar_url' => null,
                'is_active' => true,
                'email_verified' => true,
                'last_login' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }
}