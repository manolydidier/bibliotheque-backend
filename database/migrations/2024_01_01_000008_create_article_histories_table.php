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
        Schema::create('article_histories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('article_id');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('action', 100)->index(); // create, update, publish, archive, etc.
            $table->json('changes')->nullable(); // What was changed
            $table->json('previous_values')->nullable(); // Previous state
            $table->json('new_values')->nullable(); // New state
            $table->text('notes')->nullable(); // User notes about the change
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->json('meta')->nullable(); // Additional metadata
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['tenant_id', 'article_id']);
            $table->index(['tenant_id', 'action']);
            $table->index(['tenant_id', 'user_id']);
            $table->index(['article_id', 'created_at']);
            $table->index(['action', 'created_at']);
            
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
        Schema::dropIfExists('article_histories');
    }
};
