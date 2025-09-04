<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $this->call([
        // RolesTableSeeder::class,
        // UsersTableSeeder::class,
        // UserRolesTableSeeder::class,   
        // PermissionsTableSeeder::class, // déjà exécuté mais tu peux le laisser
        // RolePermissionsTableSeeder::class,

          CategorySeeder::class,
            TagSeeder::class,
            ArticleSeeder::class,
    ]);



        User::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'username' => 'testuser',

            'email' => 'test@example.com',
        ]);
    }
}
