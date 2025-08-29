<?php

namespace Tests\Unit;

use App\Enums\ArticleStatus;
use App\Enums\ArticleVisibility;
use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticlePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_article_viewable_by_guest(): void
    {
        $article = Article::factory()->create([
            'status' => ArticleStatus::PUBLISHED,
            'visibility' => ArticleVisibility::PUBLIC,
            'published_at' => now()->subHour(),
        ]);

        $this->assertTrue($article->isPublic());
    }
}


