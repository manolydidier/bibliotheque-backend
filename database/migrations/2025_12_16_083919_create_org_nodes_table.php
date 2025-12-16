<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('org_nodes', function (Blueprint $table) {
            $table->id();

            // Personne (optionnel)
            $table->foreignId('user_id')->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Hiérarchie
            $table->foreignId('parent_id')->nullable()
                ->constrained('org_nodes')
                ->nullOnDelete();

            // Données “poste”
            $table->string('title', 120);
            $table->string('department', 120)->nullable();
            $table->string('badge', 80)->nullable();
            $table->string('subtitle', 180)->nullable();

            // Bio
            $table->longText('bio')->nullable();

            // ✅ Avatar (chemin relatif dans storage/app/public)
            // ex: "orgnodes/xxxxx.jpg"
            $table->string('avatar_path')->nullable();

            // Style & ordre
            // NOTE: dans ton front tu envoies un number pour level -> je recommande integer
            // mais je respecte ton schéma actuel.
            $table->string('level', 20)->default('white');
            $table->string('accent', 20)->nullable();
            $table->unsignedInteger('sort_order')->default(0);

            // Position
            $table->decimal('pos_x', 5, 2)->nullable();
            $table->decimal('pos_y', 5, 2)->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['parent_id', 'sort_order']);
            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_nodes');
    }
};
