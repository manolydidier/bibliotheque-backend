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
        Schema::create('article_media', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('article_id');
            $table->string('name', 255);
            $table->string('filename', 255);
            $table->string('original_filename', 255);
            $table->string('path', 500);
            $table->string('url', 500);
            $table->string('thumbnail_path', 500)->nullable();
            $table->string('thumbnail_url', 500)->nullable();
            $table->enum('type', ['image', 'video', 'audio', 'document', 'embed'])->index();
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size')->default(0); // File size in bytes
            $table->json('dimensions')->nullable(); // Width, height for images/videos
            $table->json('meta')->nullable(); // Duration, bitrate, etc.
            $table->json('alt_text')->nullable(); // Alt text for accessibility
            $table->text('caption')->nullable();
            $table->integer('sort_order')->default(0)->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['tenant_id', 'article_id']);
            $table->index(['tenant_id', 'type']);
            $table->index(['tenant_id', 'is_active']);
            $table->index(['article_id', 'sort_order']);
            $table->index(['article_id', 'is_featured']);
            
            // Foreign keys
            $table->foreign('article_id')->references('id')->on('articles')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('article_media');
    }
};
