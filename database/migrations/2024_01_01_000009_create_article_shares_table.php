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
        Schema::create('article_shares', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('article_id');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->enum('method', ['email', 'social', 'link', 'embed', 'print'])->index();
            $table->string('platform', 100)->nullable(); // facebook, twitter, linkedin, etc.
            $table->string('url', 500)->nullable(); // Shared URL
            $table->json('meta')->nullable(); // Platform-specific data
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('referrer', 500)->nullable();
            $table->json('location')->nullable(); // Country, city, etc.
            $table->boolean('is_converted')->default(false)->index(); // Did it lead to engagement?
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['tenant_id', 'article_id']);
            $table->index(['tenant_id', 'method']);
            $table->index(['tenant_id', 'platform']);
            $table->index(['article_id', 'method']);
            $table->index(['article_id', 'created_at']);
            $table->index(['method', 'created_at']);
            
            // Foreign keys
            $table->foreign('article_id')->references('id')->on('articles')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('article_shares');
    }
};
