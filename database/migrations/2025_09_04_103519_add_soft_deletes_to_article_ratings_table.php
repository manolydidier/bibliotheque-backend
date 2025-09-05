<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('article_ratings', function (Blueprint $table) {
            // Ajoute la colonne deleted_at pour activer les SoftDeletes
            $table->softDeletes()->after('updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('article_ratings', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
