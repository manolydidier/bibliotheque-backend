<?php

namespace Database\Factories;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TagFactory extends Factory
{
    protected $model = Tag::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->word();

        return [
            'uuid'        => (string) Str::uuid(),
            'tenant_id'   => null,
            'name'        => strtolower($name),
            'slug'        => Str::slug($name) . '-' . Str::random(6),
            'description' => $this->faker->optional()->sentence(10),
            'color'       => $this->faker->optional()->hexColor(),
            'meta'        => [],
            'usage_count' => $this->faker->numberBetween(0, 500),
            'is_active'   => true,
            'created_by'  => null,
            'updated_by'  => null,
        ];
    }
}
