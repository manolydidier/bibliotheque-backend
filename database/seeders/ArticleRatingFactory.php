<?php

namespace Database\Factories;

use App\Models\ArticleRating;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ArticleRatingFactory extends Factory
{
    protected $model = ArticleRating::class;

    public function definition(): array
    {
        $rating = $this->faker->numberBetween(1, 5);

        return [
            'uuid'              => (string) Str::uuid(),
            'tenant_id'         => null,
            'article_id'        => null,
            'user_id'           => null,
            'guest_email'       => $this->faker->optional()->safeEmail(),
            'guest_name'        => $this->faker->optional()->name(),
            'rating'            => $rating,
            'review'            => $this->faker->optional()->paragraph(),
            'criteria_ratings'  => null,
            'is_verified'       => $this->faker->boolean(20),
            'is_helpful'        => $this->faker->boolean(40),
            'helpful_count'     => $this->faker->numberBetween(0, 30),
            'not_helpful_count' => $this->faker->numberBetween(0, 10),
            'status'            => 'approved',
            'moderated_by'      => null,
            'moderated_at'      => null,
            'moderation_notes'  => null,
            'ip_address'        => $this->faker->ipv4(),
            'user_agent'        => $this->faker->userAgent(),
        ];
    }
}
