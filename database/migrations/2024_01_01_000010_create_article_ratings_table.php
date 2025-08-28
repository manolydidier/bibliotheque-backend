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
        Schema::create('article_ratings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('article_id');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('guest_email', 255)->nullable();
            $table->string('guest_name', 255)->nullable();
            $table->integer('rating')->comment('Rating from 1 to 5');
            $table->text('review')->nullable();
            $table->json('criteria_ratings')->nullable(); // Detailed ratings for different aspects
            $table->boolean('is_verified')->default(false)->index(); // Email verification
            $table->boolean('is_helpful')->default(false)->index();
            $table->integer('helpful_count')->default(0)->index();
            $table->integer('not_helpful_count')->default(0)->index();
            $table->enum('status', ['pending', 'approved', 'rejected', 'spam'])->default('pending')->index();
            $table->unsignedBigInteger('moderated_by')->nullable()->index();
            $table->timestamp('moderated_at')->nullable();
            $table->text('moderation_notes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();
            
            // Constraints
            $table->check('rating >= 1 AND rating <= 5');
            
            // Unique constraint - one rating per user/article combination
            $table->unique(['article_id', 'user_id'], 'unique_user_article_rating');
            $table->unique(['article_id', 'guest_email'], 'unique_guest_article_rating');
            
            // Indexes for performance
            $table->index(['tenant_id', 'article_id']);
            $table->index(['tenant_id', 'rating']);
            $table->index(['tenant_id', 'status']);
            $table->index(['article_id', 'rating']);
            $table->index(['article_id', 'status']);
            $table->index(['rating', 'created_at']);
            
            // Foreign keys
            $table->foreign('article_id')->references('id')->on('articles')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('moderated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('article_ratings');
    }
};
