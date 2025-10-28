<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Si tu es sur PostgreSQL TRÈS gros volumes et que tu veux du CONCURRENTLY,
    // tu peux mettre: protected $withinTransaction = false;

    public function up(): void
    {
        if (Schema::hasTable('comments')) {
            Schema::table('comments', function (Blueprint $table) {
                // commentaires
                $table->index('created_at', 'idx_comments_created_at');
                $table->index(['article_id','created_at'], 'idx_comments_article_id_created_at');
                $table->index(['status','created_at'], 'idx_comments_status_created_at');
            });
        }

        if (Schema::hasTable('article_views')) {
            Schema::table('article_views', function (Blueprint $table) {
                $table->index(['article_id','created_at'], 'idx_article_views_article_id_created_at');
            });
        }

        if (Schema::hasTable('article_shares')) {
            Schema::table('article_shares', function (Blueprint $table) {
                $table->index(['article_id','created_at'], 'idx_article_shares_article_id_created_at');
            });
        }

        if (Schema::hasTable('article_events')) {
            Schema::table('article_events', function (Blueprint $table) {
                $table->index(['type','article_id','created_at'], 'idx_article_events_type_article_id_created_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('comments')) {
            Schema::table('comments', function (Blueprint $table) {
                $table->dropIndex('idx_comments_created_at');
                $table->dropIndex('idx_comments_article_id_created_at');
                $table->dropIndex('idx_comments_status_created_at');
            });
        }

        if (Schema::hasTable('article_views')) {
            Schema::table('article_views', function (Blueprint $table) {
                $table->dropIndex('idx_article_views_article_id_created_at');
            });
        }

        if (Schema::hasTable('article_shares')) {
            Schema::table('article_shares', function (Blueprint $table) {
                $table->dropIndex('idx_article_shares_article_id_created_at');
            });
        }

        if (Schema::hasTable('article_events')) {
            Schema::table('article_events', function (Blueprint $table) {
                $table->dropIndex('idx_article_events_type_article_id_created_at');
            });
        }
    }
};
