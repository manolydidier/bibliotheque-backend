<?php

namespace Database\Factories;

use App\Models\ArticleHistory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ArticleHistoryFactory extends Factory
{
    protected $model = ArticleHistory::class;

    public function definition(): array
    {
        return [
            'uuid'            => (string) Str::uuid(),
            'tenant_id'       => null,
            'article_id'      => null,
            'user_id'         => null,
            'action'          => $this->faker->randomElement(['create','update','publish','archive']),
            'changes'         => null,
            'previous_values' => null,
            'new_values'      => null,
            'notes'           => $this->faker->optional()->sentence(),
            'ip_address'      => $this->faker->ipv4(),
            'user_agent'      => $this->faker->userAgent(),
            'meta'            => [],
        ];
    }
}
