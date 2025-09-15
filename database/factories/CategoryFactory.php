<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'uuid'        => (string) Str::uuid(),
            'tenant_id'   => null,
            'parent_id'   => null,
            'name'        => ucfirst($name),
            'slug'        => Str::slug($name) . '-' . Str::random(6),
            'description' => $this->faker->optional()->sentence(12),
            'icon'        => $this->faker->optional()->randomElement(['book', 'code', 'cpu', 'globe']),
            'color'       => $this->faker->optional()->hexColor(),
            'meta'        => [],
            'sort_order'  => $this->faker->numberBetween(0, 50),
            'is_active'   => true,
            'is_featured' => $this->faker->boolean(20),
            'created_by'  => null,
            'updated_by'  => null,
        ];
    }
}
