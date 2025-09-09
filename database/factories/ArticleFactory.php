<?php
// database/factories/ArticleFactory.php

namespace Database\Factories;

use App\Enums\ArticleStatus;
use App\Enums\ArticleVisibility;
use App\Models\Article;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ArticleFactory extends Factory
{
    protected $model = Article::class;

    public function definition(): array
    {
        $title   = ucfirst($this->faker->unique()->sentence(mt_rand(3, 7)));
        $content = $this->faker->paragraphs(mt_rand(5, 12), true);

        $wordCount   = str_word_count(strip_tags($content));
        $readingTime = max(1, (int) round($wordCount / 200));

        // récupère un id d'utilisateur existant (ou null si aucun)
        $pickUserId = fn () => User::inRandomOrder()->value('id');

        return [
            'uuid'         => (string) Str::uuid(),
            'tenant_id'    => null,
            'title'        => $title,
            'slug'         => Str::slug($title) . '-' . Str::random(6),
            'excerpt'      => $this->faker->optional()->text(160),
            'content'      => $content,
            'meta'         => [],
            'seo_data'     => [],
            'status'       => ArticleStatus::DRAFT,
            'visibility'   => ArticleVisibility::PUBLIC,
            'published_at' => null,
            'scheduled_at' => null,
            'expires_at'   => null,
            'reading_time' => $readingTime,
            'word_count'   => $wordCount,
            'view_count'   => $this->faker->numberBetween(0, 5000),
            'share_count'  => $this->faker->numberBetween(0, 500),
            'comment_count'=> $this->faker->numberBetween(0, 200),
            'rating_average'=> $this->faker->randomFloat(2, 0, 5),
            'rating_count'  => $this->faker->numberBetween(0, 200),
            'is_featured'  => $this->faker->boolean(15),
            'is_sticky'    => $this->faker->boolean(10),
            // 🔽 JAMAIS User::factory() ici
            'author_id'    => $pickUserId(),
            'created_by'   => $pickUserId(),
            'updated_by'   => $pickUserId(),
            'reviewed_by'  => $this->faker->optional(0.4)->randomElement([$pickUserId(), null]),
            'reviewed_at'  => $this->faker->optional(0.4)->dateTimeBetween('-1 month', 'now'),
        ];
    }

    public function published(): self
    {
        return $this->state(fn () => [
            'status'       => ArticleStatus::PUBLISHED,
            'published_at' => $this->faker->dateTimeBetween('-4 months', 'now'),
            'scheduled_at' => null,
        ]);
    }
}
