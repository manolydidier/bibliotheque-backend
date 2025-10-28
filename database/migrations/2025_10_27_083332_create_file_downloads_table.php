<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('file_downloads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('file_id');     // id du média/fichier (ex: article_media.id)
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->date('date');
            $table->unsignedBigInteger('count')->default(0);
            $table->timestamps();

            $table->unique(['file_id', 'tenant_id', 'date']);
            $table->index(['date']);
            $table->index(['file_id']);

            // Si votre table s'appelle article_media:
            // $table->foreign('file_id')->references('id')->on('article_media')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_downloads');
    }
};
