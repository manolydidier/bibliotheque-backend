<?php

namespace Database\Factories;

use App\Enums\ArticleStatus;
use App\Enums\ArticleVisibility;
use App\Models\Article;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Article>
 */
class ArticleFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Article::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $title = fake()->sentence(6, true);
        $content = fake()->paragraphs(rand(5, 15), true);
        
        return [
            'uuid' => fake()->uuid(),
            'tenant_id' => null, // Will be set by seeder if needed
            'title' => $title,
            'slug' => Str::slug($title),
            'excerpt' => fake()->paragraph(2),
            'content' => $content,
            'featured_image' => null,
            'featured_image_alt' => null,
            'meta' => [
                'meta_title' => fake()->sentence(8, true),
                'meta_description' => fake()->sentence(15, true),
                'meta_keywords' => implode(', ', fake()->words(5)),
                'custom_field_1' => fake()->word(),
                'custom_field_2' => fake()->word(),
            ],
            'seo_data' => [
                'og_title' => fake()->sentence(8, true),
                'og_description' => fake()->sentence(15, true),
                'og_image' => null,
                'twitter_title' => fake()->sentence(8, true),
                'twitter_description' => fake()->sentence(15, true),
                'twitter_image' => null,
                'schema_org' => [
                    '@type' => 'Article',
                    'headline' => $title,
                    'description' => fake()->sentence(15, true),
                    'author' => [
                        '@type' => 'Person',
                        'name' => fake()->name(),
                    ],
                ],
            ],
            'status' => fake()->randomElement(ArticleStatus::cases()),
            'visibility' => fake()->randomElement(ArticleVisibility::cases()),
            'password' => null,
            'published_at' => fake()->optional(0.8)->dateTimeBetween('-1 year', 'now'),
            'scheduled_at' => fake()->optional(0.1)->dateTimeBetween('now', '+1 month'),
            'expires_at' => fake()->optional(0.05)->dateTimeBetween('now', '+1 year'),
            'reading_time' => null, // Will be calculated automatically
            'word_count' => null, // Will be calculated automatically
            'view_count' => fake()->numberBetween(0, 10000),
            'share_count' => fake()->numberBetween(0, 500),
            'comment_count' => fake()->numberBetween(0, 200),
            'rating_average' => fake()->optional(0.7)->randomFloat(2, 1, 5),
            'rating_count' => fake()->optional(0.7)->numberBetween(1, 100),
            'is_featured' => fake()->boolean(20),
            'is_sticky' => fake()->boolean(10),
            'allow_comments' => fake()->boolean(80),
            'allow_sharing' => fake()->boolean(90),
            'allow_rating' => fake()->boolean(85),
            'author_name' => fake()->optional(0.3)->name(),
            'author_bio' => fake()->optional(0.3)->paragraph(2),
            'author_avatar' => null,
            'author_id' => User::factory(),
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
            'reviewed_by' => fake()->optional(0.6)->randomElement([User::factory(), null]),
            'reviewed_at' => fake()->optional(0.6)->dateTimeBetween('-1 month', 'now'),
            'review_notes' => fake()->optional(0.3)->paragraph(1),
        ];
    }

    /**
     * Indicate that the article is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ArticleStatus::PUBLISHED,
            'published_at' => fake()->dateTimeBetween('-1 year', 'now'),
            'visibility' => ArticleVisibility::PUBLIC,
        ]);
    }

    /**
     * Indicate that the article is a draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ArticleStatus::DRAFT,
            'published_at' => null,
        ]);
    }

    /**
     * Indicate that the article is pending review.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ArticleStatus::PENDING,
            'published_at' => null,
        ]);
    }

    /**
     * Indicate that the article is archived.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ArticleStatus::ARCHIVED,
            'published_at' => fake()->dateTimeBetween('-2 years', '-1 year'),
        ]);
    }

    /**
     * Indicate that the article is featured.
     */
    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }

    /**
     * Indicate that the article is sticky.
     */
    public function sticky(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_sticky' => true,
        ]);
    }

    /**
     * Indicate that the article is password protected.
     */
    public function passwordProtected(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => ArticleVisibility::PASSWORD_PROTECTED,
            'password' => 'password123',
        ]);
    }

    /**
     * Indicate that the article is private.
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => ArticleVisibility::PRIVATE,
        ]);
    }

    /**
     * Indicate that the article is scheduled for future publication.
     */
    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ArticleStatus::PENDING,
            'scheduled_at' => fake()->dateTimeBetween('now', '+1 month'),
            'published_at' => null,
        ]);
    }

    /**
     * Indicate that the article has expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ArticleStatus::PUBLISHED,
            'published_at' => fake()->dateTimeBetween('-2 years', '-1 year'),
            'expires_at' => fake()->dateTimeBetween('-1 year', 'now'),
        ]);
    }

    /**
     * Indicate that the article has high engagement.
     */
    public function highEngagement(): static
    {
        return $this->state(fn (array $attributes) => [
            'view_count' => fake()->numberBetween(5000, 50000),
            'share_count' => fake()->numberBetween(100, 1000),
            'comment_count' => fake()->numberBetween(50, 500),
            'rating_average' => fake()->randomFloat(2, 4, 5),
            'rating_count' => fake()->numberBetween(50, 200),
        ]);
    }

    /**
     * Indicate that the article is trending.
     */
    public function trending(): static
    {
        return $this->state(fn (array $attributes) => [
            'view_count' => fake()->numberBetween(10000, 100000),
            'share_count' => fake()->numberBetween(500, 2000),
            'comment_count' => fake()->numberBetween(100, 1000),
            'rating_average' => fake()->randomFloat(2, 4.5, 5),
            'rating_count' => fake()->numberBetween(100, 500),
            'is_featured' => true,
        ]);
    }

    /**
     * Indicate that the article is long-form content.
     */
    public function longForm(): static
    {
        return $this->state(fn (array $attributes) => [
            'content' => fake()->paragraphs(rand(20, 40), true),
        ]);
    }

    /**
     * Indicate that the article is short-form content.
     */
    public function shortForm(): static
    {
        return $this->state(fn (array $attributes) => [
            'content' => fake()->paragraphs(rand(3, 8), true),
        ]);
    }
}
