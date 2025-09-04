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
        $title = ucfirst($this->faker->unique()->sentence(rand(3, 7)));
        $status = $this->faker->randomElement([
            ArticleStatus::DRAFT,
            ArticleStatus::PENDING,
            ArticleStatus::PUBLISHED,
        ]);

        $content = $this->faker->paragraphs(rand(5, 12), true);

        return [
            'uuid'               => (string) Str::uuid(),
            'tenant_id'          => null,
            'title'              => $title,
            'slug'               => Str::slug($title) . '-' . Str::random(6),
            'excerpt'            => $this->faker->optional()->text(160),
            'content'            => $content,
            'featured_image'     => null,
            'featured_image_alt' => null,
            'meta'               => [],
            'seo_data'           => [],
            'status'             => $status,
            'visibility'         => ArticleVisibility::PUBLIC,
            'password'           => null,
            'published_at'       => $status === ArticleStatus::PUBLISHED ? $this->faker->dateTimeBetween('-4 months', 'now') : null,
            'scheduled_at'       => $status === ArticleStatus::PENDING ? $this->faker->dateTimeBetween('now', '+1 month') : null,
            'expires_at'         => null,
            'reading_time'       => null, // recalculé dans events si besoin
            'word_count'         => 0,
            'view_count'         => $this->faker->numberBetween(0, 5000),
            'share_count'        => $this->faker->numberBetween(0, 500),
            'comment_count'      => $this->faker->numberBetween(0, 200),
            'rating_average'     => $this->faker->randomFloat(2, 0, 5),
            'rating_count'       => $this->faker->numberBetween(0, 200),
            'is_featured'        => $this->faker->boolean(15),
            'is_sticky'          => $this->faker->boolean(10),
            'allow_comments'     => true,
            'allow_sharing'      => true,
            'allow_rating'       => true,
            'author_name'        => null,
            'author_bio'         => null,
            'author_avatar'      => null,
            'author_id'          => null, // rempli dans le seeder
            'created_by'         => null,
            'updated_by'         => null,
            'reviewed_by'        => null,
            'reviewed_at'        => null,
            'review_notes'       => null,
        ];
    }
}
