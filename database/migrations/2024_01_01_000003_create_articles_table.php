<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('title', 500);
            $table->string('slug', 191)->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content');
            $table->string('featured_image', 500)->nullable();
            $table->string('featured_image_alt', 255)->nullable();
            $table->json('meta')->nullable(); // SEO meta, custom fields
            $table->json('seo_data')->nullable(); // Schema.org, Open Graph
            $table->enum('status', ['draft', 'pending', 'published', 'archived'])->default('draft')->index();
            $table->enum('visibility', ['public', 'private', 'password_protected'])->default('public')->index();
            $table->string('password')->nullable(); // For password protected articles
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->integer('reading_time')->nullable(); // Estimated reading time in minutes
            $table->integer('word_count')->default(0)->index();
            $table->integer('view_count')->default(0)->index();
            $table->integer('share_count')->default(0)->index();
            $table->integer('comment_count')->default(0)->index();
            $table->decimal('rating_average', 3, 2)->default(0.00)->index();
            $table->integer('rating_count')->default(0)->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->boolean('is_sticky')->default(false)->index();
            $table->boolean('allow_comments')->default(true);
            $table->boolean('allow_sharing')->default(true);
            $table->boolean('allow_rating')->default(true);
            $table->string('author_name', 255)->nullable();
            $table->string('author_bio', 500)->nullable();
            $table->string('author_avatar', 500)->nullable();
            $table->unsignedBigInteger('author_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('reviewed_by')->nullable()->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'visibility']);
            $table->index(['tenant_id', 'published_at']);
            $table->index(['tenant_id', 'is_featured']);
            $table->index(['tenant_id', 'author_id']);
            $table->index(['status', 'published_at']);
            $table->index(['status', 'scheduled_at']);
            $table->fullText(['title', 'excerpt', 'content']);
            
            // Foreign keys
            $table->foreign('author_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
