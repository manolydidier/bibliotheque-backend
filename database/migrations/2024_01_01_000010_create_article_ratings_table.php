<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_ratings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('article_id');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('guest_email', 191)->nullable();
            $table->string('guest_name', 191)->nullable();

            // rating 1..5
            $table->unsignedTinyInteger('rating')->comment('Rating from 1 to 5');

            $table->text('review')->nullable();
            $table->json('criteria_ratings')->nullable();
            $table->boolean('is_verified')->default(false)->index();
            $table->boolean('is_helpful')->default(false)->index();
            $table->integer('helpful_count')->default(0)->index();
            $table->integer('not_helpful_count')->default(0)->index();
            $table->enum('status', ['pending', 'approved', 'rejected', 'spam'])->default('pending')->index();
            $table->unsignedBigInteger('moderated_by')->nullable()->index();
            $table->timestamp('moderated_at')->nullable();
            $table->text('moderation_notes')->nullable();
            $table->string('ip_address', 45)->nullable(); // IPv4/IPv6
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();

            // Uniques
            $table->unique(['article_id', 'user_id'], 'unique_user_article_rating');
            $table->unique(['article_id', 'guest_email'], 'unique_guest_article_rating');

            // Indexes
            $table->index(['tenant_id', 'article_id']);
            $table->index(['tenant_id', 'rating']);
            $table->index(['tenant_id', 'status']);
            $table->index(['article_id', 'rating']);
            $table->index(['article_id', 'status']);
            $table->index(['rating', 'created_at']);

            // FKs
            $table->foreign('article_id')->references('id')->on('articles')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('moderated_by')->references('id')->on('users')->onDelete('set null');
        });

        // Ajout de la contrainte CHECK en SQL brut (pas d'API Laravel native)
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            // Attention: nécessite MySQL 8.0.16+
            DB::statement("
                ALTER TABLE article_ratings
                ADD CONSTRAINT chk_article_ratings_rating
                CHECK (rating BETWEEN 1 AND 5)
            ");
        } elseif ($driver === 'pgsql') {
            DB::statement("
                ALTER TABLE article_ratings
                ADD CONSTRAINT chk_article_ratings_rating
                CHECK (rating >= 1 AND rating <= 5)
            ");
        } elseif ($driver === 'sqlsrv') {
            DB::statement("
                ALTER TABLE article_ratings
                ADD CONSTRAINT chk_article_ratings_rating
                CHECK (rating >= 1 AND rating <= 5)
            ");
        }
        // Note: SQLite ne permet pas d'ajouter un CHECK après coup; pour les devs SQLite,
        // fais la validation côté application ou crée la table en SQL brut si nécessaire.
    }

    public function down(): void
    {
        // Supprimer la table (les contraintes partent avec)
        Schema::dropIfExists('article_ratings');
    }
};
