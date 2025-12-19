<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('cms_sections', function (Blueprint $table) {
      $table->id();
      $table->unsignedBigInteger('tenant_id')->index();

      $table->string('category', 80)->index();
      $table->string('title', 180);

      // ⚠️ colonnes indexées -> plus courtes + ASCII (slugs)
      $table->string('template', 80)->collation('ascii_general_ci')->index();
      $table->string('section', 80)->collation('ascii_general_ci')->index();
      $table->string('locale', 10)->default('fr')->collation('ascii_general_ci')->index();

      // contenu GrapesJS / rendu
      $table->longText('gjs_project')->nullable(); // JSON GrapesJS (string)
      $table->longText('html')->nullable();
      $table->longText('css')->nullable();
      $table->longText('js')->nullable();

      // workflow publication
      $table->string('status', 20)->default('draft')->index(); // draft|pending|published
      $table->timestamp('published_at')->nullable()->index();
      $table->timestamp('scheduled_at')->nullable()->index(); // optionnel (publication programmée)

      $table->unsignedInteger('version')->default(1);
      $table->unsignedInteger('sort_order')->default(0);

      // classement / recherche
      $table->json('meta')->nullable();

      // audit
      $table->unsignedBigInteger('created_by')->nullable()->index();
      $table->unsignedBigInteger('updated_by')->nullable()->index();

      $table->timestamps();

      // ✅ Unique slot (corrigé)
      $table->unique(['tenant_id', 'template', 'section', 'locale'], 'cms_sections_slot_unique');

      // Index utiles
      $table->index(['tenant_id', 'status', 'published_at'], 'cms_sections_tenant_status_pub_idx');
      $table->index(['tenant_id', 'template', 'locale'], 'cms_sections_tenant_tpl_locale_idx');
      $table->index(['tenant_id', 'category', 'locale'], 'cms_sections_tenant_cat_locale_idx');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('cms_sections');
  }
};
