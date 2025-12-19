<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('cms_section_revisions', function (Blueprint $table) {
      $table->bigIncrements('id');

      $table->unsignedBigInteger('tenant_id')->index();
      $table->unsignedBigInteger('cms_section_id')->index();

      $table->unsignedInteger('version')->index();
      $table->string('status', 20)->default('draft')->index(); // statut au moment du snapshot
      $table->timestamp('published_at')->nullable()->index();

      $table->longText('gjs_project')->nullable();
      $table->longText('html')->nullable();
      $table->longText('css')->nullable();
      $table->longText('js')->nullable();

      $table->string('checksum', 64)->nullable()->index();
      $table->json('meta')->nullable();

      $table->unsignedBigInteger('created_by')->nullable()->index();
      $table->timestamps();

      // 1 section ne peut pas avoir 2 fois la même version
      $table->unique(['cms_section_id', 'version'], 'cms_section_rev_unique');

      // Perf: récupérer dernière version rapidement
      $table->index(['cms_section_id', 'version'], 'cms_section_rev_section_version_idx');

      // Foreign keys (recommandé si possible)
      $table->foreign('cms_section_id')
        ->references('id')->on('cms_sections')
        ->cascadeOnDelete();

      // $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
      $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('cms_section_revisions');
  }
};
