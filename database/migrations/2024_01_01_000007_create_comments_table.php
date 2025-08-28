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
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('article_id');
            $table->unsignedBigInteger('parent_id')->nullable()->index(); // For nested comments
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('guest_name', 255)->nullable();
            $table->string('guest_email', 255)->nullable();
            $table->string('guest_website', 500)->nullable();
            $table->text('content');
            $table->enum('status', ['pending', 'approved', 'rejected', 'spam'])->default('pending')->index();
            $table->json('meta')->nullable(); // User agent, IP, etc.
            $table->integer('like_count')->default(0)->index();
            $table->integer('dislike_count')->default(0)->index();
            $table->integer('reply_count')->default(0)->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->unsignedBigInteger('moderated_by')->nullable()->index();
            $table->timestamp('moderated_at')->nullable();
            $table->text('moderation_notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['tenant_id', 'article_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'user_id']);
            $table->index(['article_id', 'status']);
            $table->index(['article_id', 'parent_id']);
            $table->index(['status', 'created_at']);
            
            // Foreign keys
            $table->foreign('article_id')->references('id')->on('articles')->onDelete('cascade');
            $table->foreign('parent_id')->references('id')->on('comments')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('moderated_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
