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
        Schema::create('article_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('article_id');
            $table->unsignedBigInteger('category_id');
            $table->boolean('is_primary')->default(false)->index();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            // Unique constraint
            $table->unique(['article_id', 'category_id']);
            
            // Indexes for performance
            $table->index(['tenant_id', 'article_id']);
            $table->index(['tenant_id', 'category_id']);
            $table->index(['category_id', 'is_primary']);
            
            // Foreign keys
            $table->foreign('article_id')->references('id')->on('articles')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('article_categories');
    }
};
