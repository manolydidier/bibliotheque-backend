<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Table agrégée : 1 ligne par (article_id, tenant_id, date)
        Schema::create('article_views', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Si ta table s'appelle autrement, adapte le constrained('articles')
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();

            // Optionnel : multi-tenant
            $table->unsignedBigInteger('tenant_id')->nullable()->index();

            // Jour agrégé (YYYY-MM-DD)
            $table->date('date')->index();

            // Nombre de vues de la journée
            $table->unsignedInteger('count')->default(0);

            $table->timestamps();

            // Unicité par jour (permet l’upsert/incrément)
            $table->unique(['article_id', 'tenant_id', 'date'], 'uq_article_views_article_tenant_date');
        });

        // Indexs utiles pour les dashboards
        DB::statement('CREATE INDEX IF NOT EXISTS idx_article_views_article_id_date ON article_views(article_id, date)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_article_views_tenant_id_date ON article_views(tenant_id, date)');
    }

    public function down(): void
    {
        Schema::dropIfExists('article_views');
    }
};
