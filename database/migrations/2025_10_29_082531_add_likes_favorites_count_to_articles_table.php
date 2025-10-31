<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::table('articles', function (Blueprint $table) {
        $table->unsignedInteger('likes_count')->default(0)->after('some_column');
        $table->unsignedInteger('favorites_count')->default(0)->after('likes_count');
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            //
        });
    }
};
