<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('subject');
            $table->string('type')->nullable(); // question, project, support, other
            $table->text('message');

            // RGPD / consentement
            $table->boolean('consent')->default(false);

            // 🔒 anti-bot (honeypot) – si rempli, tu peux marquer comme suspect
            $table->string('company')->nullable();

            // Meta
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            // Adresse vers laquelle le mail a été envoyé
            $table->string('sent_to_email')->nullable();

            // Status (pour suivre le traitement si tu veux)
            $table->string('status')->default('new'); // new, read, archived, etc.

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
