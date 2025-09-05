<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use App\Models\ArticleMedia;
use App\Models\ArticleRating;
use App\Models\ArticleShare;
use App\Models\Comment;
use App\Models\ArticleHistory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Queue;   // ← garde Queue::fake()
use Illuminate\Support\Facades\Event;   // ← peut rester importé si tu veux

class ArticleSeeder extends Seeder
{
    public function run(): void
    {
        // ⚠️ Ne touche pas aux users
        $users = User::query()->pluck('id');
        if ($users->isEmpty()) {
            $this->command->warn('Aucun user trouvé — ArticleSeeder ignoré.');
            return;
        }

        // ❌ NE PAS FAIRE: Event::fake();
        // ✅ Suffit de faker la queue pour éviter les insert dans `jobs`
        Queue::fake();

        $categoryIds = Category::query()->pluck('id')->all();
        $tagIds      = Tag::query()->pluck('id')->all();

        if (empty($categoryIds)) {
            $this->command->warn('Aucune category — lance d’abord CategorySeeder.');
            return;
        }
        if (empty($tagIds)) {
            $this->command->warn('Aucun tag — lance d’abord TagSeeder.');
            return;
        }

        // —— Articles "mis en avant"
        $featuredTitles = [
            "L'avenir de l'Intelligence Artificielle en 2024",
            'Comment construire une startup qui réussit',
            'Les tendances du développement web moderne',
            'Guide complet du marketing digital',
            "L'impact de la technologie sur notre mode de vie",
        ];

        foreach ($featuredTitles as $title) {
            $article = Article::factory()
                ->published()
                ->state([
                    'title'      => $title,
                    'author_id'  => $users->random(),
                    'created_by' => $users->random(),
                    'updated_by' => $users->random(),
                    'is_featured'=> true,
                    'is_sticky'  => fake()->boolean(40),
                ])
                ->create();

            $this->attachRelations($article, $categoryIds, $tagIds, $users->all());
            $this->seedActivity($article, $users->all());
        }

        // —— 20 publiés
        Article::factory()
            ->count(20)
            ->published()
            ->state(fn() => [
                'author_id'  => $users->random(),
                'created_by' => $users->random(),
                'updated_by' => $users->random(),
            ])
            ->create()
            ->each(function (Article $article) use ($categoryIds, $tagIds, $users) {
                $this->attachRelations($article, $categoryIds, $tagIds, $users->all());
                $this->seedActivity($article, $users->all());
            });

        // —— 10 brouillons
        Article::factory()
            ->count(10)
            ->draft()
            ->state(fn() => [
                'author_id'  => $users->random(),
                'created_by' => $users->random(),
                'updated_by' => $users->random(),
            ])
            ->create()
            ->each(function (Article $article) use ($categoryIds, $tagIds, $users) {
                $this->attachRelations($article, $categoryIds, $tagIds, $users->all());
                $this->seedActivity($article, $users->all(), published: false);
            });

        // —— 8 en attente
        Article::factory()
            ->count(8)
            ->pending()
            ->state(fn() => [
                'author_id'  => $users->random(),
                'created_by' => $users->random(),
                'updated_by' => $users->random(),
            ])
            ->create()
            ->each(function (Article $article) use ($categoryIds, $tagIds, $users) {
                $this->attachRelations($article, $categoryIds, $tagIds, $users->all());
                $this->seedActivity($article, $users->all(), published: false);
            });

        // —— 5 planifiés
        Article::factory()
            ->count(5)
            ->scheduled()
            ->state(fn() => [
                'author_id'  => $users->random(),
                'created_by' => $users->random(),
                'updated_by' => $users->random(),
            ])
            ->create()
            ->each(function (Article $article) use ($categoryIds, $tagIds, $users) {
                $this->attachRelations($article, $categoryIds, $tagIds, $users->all());
                $this->seedActivity($article, $users->all(), published: false);
            });

        // —— 5 archivés
        Article::factory()
            ->count(5)
            ->archived()
            ->state(fn() => [
                'author_id'  => $users->random(),
                'created_by' => $users->random(),
                'updated_by' => $users->random(),
            ])
            ->create()
            ->each(function (Article $article) use ($categoryIds, $tagIds, $users) {
                $this->attachRelations($article, $categoryIds, $tagIds, $users->all());
                $this->seedActivity($article, $users->all(), published: false);
            });
    }

    private function attachRelations(Article $article, array $categoryIds, array $tagIds, array $userIds): void
    {
        $primaryId = fake()->randomElement($categoryIds);
        $sync = [$primaryId => ['is_primary' => true, 'sort_order' => 0]];
        foreach (fake()->randomElements(array_diff($categoryIds, [$primaryId]), fake()->numberBetween(0, 2)) as $cid) {
            $sync[$cid] = ['is_primary' => false, 'sort_order' => 0];
        }
        $article->categories()->sync($sync);

        foreach (fake()->randomElements($tagIds, fake()->numberBetween(2, 6)) as $tid) {
            $article->tags()->syncWithoutDetaching([$tid => ['sort_order' => fake()->numberBetween(0, 10)]]);
        }

        ArticleMedia::factory()->count(fake()->numberBetween(1, 3))->create([
            'article_id' => $article->id,
            'created_by' => fake()->randomElement($userIds),
            'updated_by' => fake()->randomElement($userIds),
        ]);
    }

    private function seedActivity(Article $article, array $userIds, bool $published = true): void
    {
        Comment::factory()->count(fake()->numberBetween(0, 6))->create([
            'article_id' => $article->id,
            'user_id'    => fake()->randomElement($userIds),
        ]);

        ArticleShare::factory()->count(fake()->numberBetween(0, 3))->create([
            'article_id' => $article->id,
            'user_id'    => fake()->boolean(50) ? fake()->randomElement($userIds) : null,
        ]);

        $raterIds = collect($userIds)->shuffle()->take(fake()->numberBetween(0, 5));
        foreach ($raterIds as $uid) {
            ArticleRating::factory()->create([
                'article_id' => $article->id,
                'user_id'    => $uid,
                'guest_email'=> null,
            ]);
        }

        ArticleHistory::factory()->create([
            'article_id' => $article->id,
            'user_id'    => fake()->randomElement($userIds),
            'action'     => $published ? 'create' : 'update',
        ]);
    }
}
