<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ArticleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing data
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        Article::truncate();
        Category::truncate();
        Tag::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // Create users if they don't exist
        $users = User::all();
        if ($users->isEmpty()) {
            $users = User::factory(5)->create();
        }

        // Create categories
        $categories = $this->createCategories();

        // Create tags
        $tags = $this->createTags();

        // Create articles with different states
        $this->createArticles($users, $categories, $tags);

        $this->command->info('Articles seeded successfully!');
    }

    /**
     * Create categories with hierarchy.
     */
    private function createCategories(): array
    {
        $categories = [];

        // Main categories
        $mainCategories = [
            'Technologie' => [
                'description' => 'Articles sur les nouvelles technologies et innovations',
                'icon' => 'fas fa-microchip',
                'color' => '#3B82F6',
                'children' => [
                    'Intelligence Artificielle' => [
                        'description' => 'IA, machine learning et automatisation',
                        'icon' => 'fas fa-brain',
                        'color' => '#10B981',
                    ],
                    'Développement Web' => [
                        'description' => 'Frontend, backend et frameworks modernes',
                        'icon' => 'fas fa-code',
                        'color' => '#F59E0B',
                    ],
                    'Mobile' => [
                        'description' => 'Applications mobiles et développement iOS/Android',
                        'icon' => 'fas fa-mobile-alt',
                        'color' => '#8B5CF6',
                    ],
                ],
            ],
            'Business' => [
                'description' => 'Actualités et conseils business',
                'icon' => 'fas fa-briefcase',
                'color' => '#EF4444',
                'children' => [
                    'Startup' => [
                        'description' => 'Écosystème startup et entrepreneuriat',
                        'icon' => 'fas fa-rocket',
                        'color' => '#06B6D4',
                    ],
                    'Marketing' => [
                        'description' => 'Stratégies marketing et croissance',
                        'icon' => 'fas fa-chart-line',
                        'color' => '#84CC16',
                    ],
                ],
            ],
            'Lifestyle' => [
                'description' => 'Mode de vie et bien-être',
                'icon' => 'fas fa-heart',
                'color' => '#EC4899',
                'children' => [
                    'Santé' => [
                        'description' => 'Santé physique et mentale',
                        'icon' => 'fas fa-dumbbell',
                        'color' => '#F97316',
                    ],
                    'Voyage' => [
                        'description' => 'Destinations et conseils voyage',
                        'icon' => 'fas fa-plane',
                        'color' => '#14B8A6',
                    ],
                ],
            ],
        ];

        foreach ($mainCategories as $name => $data) {
            $category = Category::create([
                'name' => $name,
                'description' => $data['description'],
                'icon' => $data['icon'],
                'color' => $data['color'],
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 0,
                'created_by' => User::first()->id,
            ]);

            $categories[$name] = $category;

            // Create subcategories
            if (isset($data['children'])) {
                foreach ($data['children'] as $childName => $childData) {
                    $childCategory = Category::create([
                        'parent_id' => $category->id,
                        'name' => $childName,
                        'description' => $childData['description'],
                        'icon' => $childData['icon'],
                        'color' => $childData['color'],
                        'is_active' => true,
                        'is_featured' => false,
                        'sort_order' => 0,
                        'created_by' => User::first()->id,
                    ]);

                    $categories[$childName] = $childCategory;
                }
            }
        }

        return $categories;
    }

    /**
     * Create tags.
     */
    private function createTags(): array
    {
        $tagNames = [
            'Innovation', 'Digital', 'Startup', 'Tech', 'Business', 'Marketing',
            'Design', 'UX', 'Mobile', 'Web', 'Cloud', 'AI', 'ML', 'Data',
            'Security', 'Blockchain', 'IoT', 'AR', 'VR', 'Sustainability',
            'Remote Work', 'Productivity', 'Leadership', 'Team Building',
            'Customer Experience', 'Growth', 'Analytics', 'SEO', 'Content',
            'Social Media', 'E-commerce', 'Fintech', 'Healthtech', 'Edtech',
        ];

        $tags = [];
        foreach ($tagNames as $name) {
            $tag = Tag::create([
                'name' => $name,
                'description' => fake()->sentence(),
                'color' => fake()->hexColor(),
                'usage_count' => fake()->numberBetween(0, 50),
                'is_active' => true,
                'created_by' => User::first()->id,
            ]);

            $tags[$name] = $tag;
        }

        return $tags;
    }

    /**
     * Create articles with different states and relationships.
     */
    private function createArticles(\Illuminate\Support\Collection $users, array $categories, array $tags): void
    {
        // Featured articles
        $this->createFeaturedArticles($users, $categories, $tags);

        // Regular published articles
        $this->createRegularArticles($users, $categories, $tags);

        // Draft articles
        $this->createDraftArticles($users, $categories, $tags);

        // Pending articles
        $this->createPendingArticles($users, $categories, $tags);

        // Scheduled articles
        $this->createScheduledArticles($users, $categories, $tags);

        // Archived articles
        $this->createArchivedArticles($users, $categories, $tags);
    }

    /**
     * Create featured articles.
     */
    private function createFeaturedArticles(\Illuminate\Support\Collection $users, array $categories, array $tags): void
    {
        $featuredTitles = [
            'L\'avenir de l\'Intelligence Artificielle en 2024',
            'Comment construire une startup qui réussit',
            'Les tendances du développement web moderne',
            'Guide complet du marketing digital',
            'L\'impact de la technologie sur notre mode de vie',
        ];

        foreach ($featuredTitles as $index => $title) {
            $article = Article::factory()
                ->published()
                ->featured()
                ->highEngagement()
                ->create([
                    'title' => $title,
                    'author_id' => $users->random()->id,
                    'created_by' => $users->random()->id,
                    'updated_by' => $users->random()->id,
                ]);

            // Attach categories
            $category = $categories[array_rand($categories)];
            $article->categories()->attach($category->id, ['is_primary' => true, 'sort_order' => 0]);

            // Attach tags
            $selectedTags = array_rand($tags, rand(3, 6));
            foreach ($selectedTags as $tagName) {
                $article->tags()->attach($tags[$tagName]->id, ['sort_order' => rand(0, 10)]);
            }
        }
    }

    /**
     * Create regular published articles.
     */
    private function createRegularArticles(\Illuminate\Support\Collection $users, array $categories, array $tags): void
    {
        Article::factory()
            ->count(20)
            ->published()
            ->create([
                'author_id' => fn() => $users->random()->id,
                'created_by' => fn() => $users->random()->id,
                'updated_by' => fn() => $users->random()->id,
            ])
            ->each(function ($article) use ($categories, $tags) {
                // Attach random categories
                $category = $categories[array_rand($categories)];
                $article->categories()->attach($category->id, ['is_primary' => true, 'sort_order' => 0]);

                // Attach random tags
                $selectedTags = array_rand($tags, rand(2, 5));
                foreach ($selectedTags as $tagName) {
                    $article->tags()->attach($tags[$tagName]->id, ['sort_order' => rand(0, 10)]);
                }
            });
    }

    /**
     * Create draft articles.
     */
    private function createDraftArticles(\Illuminate\Support\Collection $users, array $categories, array $tags): void
    {
        Article::factory()
            ->count(10)
            ->draft()
            ->create([
                'author_id' => fn() => $users->random()->id,
                'created_by' => fn() => $users->random()->id,
                'updated_by' => fn() => $users->random()->id,
            ])
            ->each(function ($article) use ($categories, $tags) {
                // Attach random categories
                $category = $categories[array_rand($categories)];
                $article->categories()->attach($category->id, ['is_primary' => true, 'sort_order' => 0]);

                // Attach random tags
                $selectedTags = array_rand($tags, rand(2, 4));
                foreach ($selectedTags as $tagName) {
                    $article->tags()->attach($tags[$tagName]->id, ['sort_order' => rand(0, 10)]);
                }
            });
    }

    /**
     * Create pending articles.
     */
    private function createPendingArticles(\Illuminate\Support\Collection $users, array $categories, array $tags): void
    {
        Article::factory()
            ->count(8)
            ->pending()
            ->create([
                'author_id' => fn() => $users->random()->id,
                'created_by' => fn() => $users->random()->id,
                'updated_by' => fn() => $users->random()->id,
            ])
            ->each(function ($article) use ($categories, $tags) {
                // Attach random categories
                $category = $categories[array_rand($categories)];
                $article->categories()->attach($category->id, ['is_primary' => true, 'sort_order' => 0]);

                // Attach random tags
                $selectedTags = array_rand($tags, rand(2, 4));
                foreach ($selectedTags as $tagName) {
                    $article->tags()->attach($tags[$tagName]->id, ['sort_order' => rand(0, 10)]);
                }
            });
    }

    /**
     * Create scheduled articles.
     */
    private function createScheduledArticles(\Illuminate\Support\Collection $users, array $categories, array $tags): void
    {
        Article::factory()
            ->count(5)
            ->scheduled()
            ->create([
                'author_id' => fn() => $users->random()->id,
                'created_by' => fn() => $users->random()->id,
                'updated_by' => fn() => $users->random()->id,
            ])
            ->each(function ($article) use ($categories, $tags) {
                // Attach random categories
                $category = $categories[array_rand($categories)];
                $article->categories()->attach($category->id, ['is_primary' => true, 'sort_order' => 0]);

                // Attach random tags
                $selectedTags = array_rand($tags, rand(2, 4));
                foreach ($selectedTags as $tagName) {
                    $article->tags()->attach($tags[$tagName]->id, ['sort_order' => rand(0, 10)]);
                }
            });
    }

    /**
     * Create archived articles.
     */
    private function createArchivedArticles(\Illuminate\Support\Collection $users, array $categories, array $tags): void
    {
        Article::factory()
            ->count(5)
            ->archived()
            ->create([
                'author_id' => fn() => $users->random()->id,
                'created_by' => fn() => $users->random()->id,
                'updated_by' => fn() => $users->random()->id,
            ])
            ->each(function ($article) use ($categories, $tags) {
                // Attach random categories
                $category = $categories[array_rand($categories)];
                $article->categories()->attach($category->id, ['is_primary' => true, 'sort_order' => 0]);

                // Attach random tags
                $selectedTags = array_rand($tags, rand(2, 4));
                foreach ($selectedTags as $tagName) {
                    $article->tags()->attach($tags[$tagName]->id, ['sort_order' => rand(0, 10)]);
                }
            });
    }
}
