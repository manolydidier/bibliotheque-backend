<?php

namespace Database\Factories;

use App\Enums\ArticleStatus;
use App\Enums\ArticleVisibility;
use App\Models\Article;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ArticleFactory extends Factory
{
    protected $model = Article::class;

    public function definition(): array
    {
        $title   = ucfirst($this->faker->unique()->sentence(mt_rand(3, 7)));
        $content = $this->faker->paragraphs(mt_rand(5, 12), true);

        // Calculs robustes
        $wordCount   = str_word_count(strip_tags($content));
        $readingTime = max(1, (int) round($wordCount / 200));

        return [
            'uuid'               => (string) Str::uuid(),
            'tenant_id'          => null,

            'title'              => $title,
            'slug'               => Str::slug($title) . '-' . Str::random(6),
            'excerpt'            => $this->faker->optional()->text(160),
            'content'            => $content,

            'featured_image'     => null,
            'featured_image_alt' => null,

            // Laisse vide (array) si tu as des casts ->array dans le modèle
            'meta'               => [],
            'seo_data'           => [],

            'status'             => ArticleStatus::DRAFT,
            'visibility'         => ArticleVisibility::PUBLIC,
            'password'           => null,

            'published_at'       => null,
            'scheduled_at'       => null,
            'expires_at'         => null,

            // ✅ plus de NULL ici
            'reading_time'       => $readingTime,
            'word_count'         => $wordCount,

            'view_count'         => $this->faker->numberBetween(0, 5000),
            'share_count'        => $this->faker->numberBetween(0, 500),
            'comment_count'      => $this->faker->numberBetween(0, 200),
            'rating_average'     => $this->faker->randomFloat(2, 0, 5),
            'rating_count'       => $this->faker->numberBetween(0, 200),

            'is_featured'        => false,
            'is_sticky'          => $this->faker->boolean(10),

            'allow_comments'     => true,
            'allow_sharing'      => true,
            'allow_rating'       => true,

            'author_name'        => null,
            'author_bio'         => null,
            'author_avatar'      => null,

            'author_id'          => null,
            'created_by'         => null,
            'updated_by'         => null,
            'reviewed_by'        => null,
            'reviewed_at'        => null,
            'review_notes'       => null,
        ];
    }

    public function published(): self
    {
        return $this->state(function () {
            return [
                'status'       => ArticleStatus::PUBLISHED,
                'published_at' => $this->faker->dateTimeBetween('-4 months', 'now'),
                'scheduled_at' => null,
                'expires_at'   => null,
            ];
        });
    }

    public function draft(): self
    {
        return $this->state(fn () => [
            'status'       => ArticleStatus::DRAFT,
            'published_at' => null,
            'scheduled_at' => null,
            'expires_at'   => null,
        ]);
    }

    public function pending(): self
    {
        return $this->state(fn () => [
            'status'       => ArticleStatus::PENDING,
            'published_at' => null,
            'scheduled_at' => $this->faker->dateTimeBetween('now', '+1 month'),
            'expires_at'   => null,
        ]);
    }

    // Alias pratique
    public function scheduled(): self
    {
        return $this->pending();
    }

    public function archived(): self
    {
        return $this->state(fn () => [
            'status'       => ArticleStatus::ARCHIVED,
            'published_at' => null,
            'scheduled_at' => null,
            // parfois archivé après expiration
            'expires_at'   => $this->faker->optional()->dateTimeBetween('-2 months', '-1 day'),
        ]);
    }

    public function featured(): self
    {
        return $this->state(fn () => ['is_featured' => true]);
    }

    public function highEngagement(): self
    {
        return $this->state(fn () => [
            'view_count'     => $this->faker->numberBetween(5_000, 50_000),
            'share_count'    => $this->faker->numberBetween(200, 2_000),
            'comment_count'  => $this->faker->numberBetween(50, 500),
            'rating_average' => $this->faker->randomFloat(2, 3.5, 5),
            'rating_count'   => $this->faker->numberBetween(50, 800),
        ]);
    }
}
