<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        if (Category::count() >= 8) {
            return; // laisse l'existant si déjà rempli
        }

        $names = [
            'Actualités', 'Technologie', 'Tutoriels', 'Culture',
            'Business', 'Voyage', 'Science', 'Sports',
        ];

        foreach ($names as $i => $name) {
            Category::factory()->create([
                'name'        => $name,
                'sort_order'  => $i,
                'is_active'   => true,
                'is_featured' => $i < 3,
            ]);
        }

        Category::factory(5)->create();
    }
}
