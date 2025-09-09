<?php

namespace Database\Factories;

use App\Models\ArticleMedia;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ArticleMediaFactory extends Factory
{
    protected $model = ArticleMedia::class;

    public function definition(): array
    {
        $filename = $this->faker->unique()->slug().'.jpg';

        return [
            'uuid'            => (string) Str::uuid(),
            'tenant_id'       => null,
            'article_id'      => null,
            'name'            => $this->faker->sentence(3),
            'filename'        => $filename,
            'original_filename'=> $filename,
            'path'            => 'uploads/'.$filename,
            'url'             => $this->faker->imageUrl(1200, 800, 'abstract', true),
            'thumbnail_path'  => null,
            'thumbnail_url'   => null,
            'type'            => 'image',
            'mime_type'       => 'image/jpeg',
            'size'            => $this->faker->numberBetween(40_000, 2_000_000),
            'dimensions'      => ['w' => 1200, 'h' => 800],
            'meta'            => [],
            'alt_text'        => $this->faker->optional()->words(4, true),
            'caption'         => $this->faker->optional()->sentence(),
            'sort_order'      => 0,
            'is_featured'     => false,
            'is_active'       => true,
            'created_by'      => null,
            'updated_by'      => null,
        ];
    }
}
