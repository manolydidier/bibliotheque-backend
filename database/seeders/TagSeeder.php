<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    public function run(): void
    {
        if (Tag::count() >= 12) {
            return;
        }

        $tags = [
            'laravel','php','vue','react','devops','docker','kubernetes',
            'seo','ux','ui','cloud','security','ai','ml'
        ];

        foreach ($tags as $t) {
            Tag::factory()->create(['name' => $t]);
        }

        Tag::factory(10)->create();
    }
}
