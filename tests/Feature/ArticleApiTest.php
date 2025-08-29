<?php

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Enums\ArticleVisibility;
use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_ok(): void
    {
        Article::factory()->count(3)->create([
            'status' => ArticleStatus::PUBLISHED,
            'visibility' => ArticleVisibility::PUBLIC,
            'published_at' => now()->subDay(),
        ]);

        $response = $this->getJson('/api/articles');
        $response->assertOk();
        $response->assertJsonStructure(['data', 'pagination', 'links', 'meta']);
    }

    public function test_show_published_public_article(): void
    {
        $article = Article::factory()->create([
            'status' => ArticleStatus::PUBLISHED,
            'visibility' => ArticleVisibility::PUBLIC,
            'published_at' => now()->subHour(),
        ]);

        $response = $this->getJson('/api/articles/' . $article->slug);
        $response->assertOk();
        $response->assertJsonStructure(['data' => ['id', 'title', 'slug']]);
    }
}


