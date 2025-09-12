<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('article_tags')) {
            // si le nom est différent (ex: article_tag), adapte ici
            return;
        }

        Schema::table('article_tags', function (Blueprint $table) {
            if (!Schema::hasColumn('article_tags', 'position')) {
                $table->integer('position')->default(0)->index()->after('tag_id');
            }
        });

        // backfill: pour chaque article, positionner par ordre d’existant
        $rows = DB::table('article_tags')
            ->select('article_id')
            ->distinct()
            ->pluck('article_id');

        foreach ($rows as $articleId) {
            $pivotRows = DB::table('article_tags')
                ->where('article_id', $articleId)
                ->orderBy('position') // si déjà présent, garde
                ->orderBy('tag_id')   // tie-breaker
                ->get(['tag_id']);

            $pos = 0;
            foreach ($pivotRows as $r) {
                DB::table('article_tags')
                    ->where('article_id', $articleId)
                    ->where('tag_id', $r->tag_id)
                    ->update(['position' => $pos++]);
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('article_tags')) return;
        Schema::table('article_tags', function (Blueprint $table) {
            if (Schema::hasColumn('article_tags', 'position')) {
                $table->dropColumn('position');
            }
        });
    }
};
