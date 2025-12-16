<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('miradia_slides', function (Blueprint $table) {
            $table->id();
            // Titre du slide (ex: "MIRA : Égalité & équité")
            $table->string('title');

            // Texte principal (FR + MG si tu veux)
            $table->text('description')->nullable();

            // Petite stat / label (ex: "Justice & équité", "Marche partagée")
            $table->string('stat_label')->nullable();

            // Tag court (ex: "MIRA", "DIA", "MIRADIA")
            $table->string('tag')->nullable();

            // Clé pour l’icône côté React (ex: "scales", "walk", "group")
            $table->string('icon')->nullable();

            // Couleur du badge/icon (hex)
            $table->string('color', 20)->default('#0ea5e9');

            // Chemin de l’image sur le disque (ex: "miradia-slides/mira.jpg")
            $table->string('image_path')->nullable();

            // Ordre d’affichage
            $table->unsignedTinyInteger('position')->default(1);

            // Actif / non
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('miradia_slides');
    }
};
