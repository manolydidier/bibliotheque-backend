<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Contexte multi-tenant (facultatif)
            $table->unsignedBigInteger('tenant_id')->nullable()->index('activities_tenant_idx');

            // Typage & routage basique
            $table->string('type', 64)->index('activities_type_idx');                 // ex: comment_approved, role_assigned…
            $table->unsignedBigInteger('recipient_id')->index('activities_recipient_idx'); // user qui reçoit la notif
            $table->unsignedBigInteger('actor_id')->nullable()->index('activities_actor_idx'); // qui a provoqué l'action

            // Cibles courantes
            $table->unsignedBigInteger('article_id')->nullable()->index('activities_article_idx');
            $table->string('article_slug')->nullable()->index('activities_article_slug_idx');
            $table->unsignedBigInteger('comment_id')->nullable()->index('activities_comment_idx');

            // Contenu d’affichage
            $table->string('title');           // "Votre commentaire a été approuvé"
            $table->string('subtitle')->nullable();
            $table->string('url')->nullable();  // URL absolue si besoin
            $table->string('link')->nullable(); // lien relatif si tu préfères

            // Données libres (utilisé par le front pour enrichir)
            $table->json('payload')->nullable();

            // Lecture (optionnel)
            $table->timestamp('read_at')->nullable()->index('activities_read_at_idx');

            $table->timestamps();
            $table->softDeletes();

            /* ==================== Clés étrangères ==================== */
            // 🧑 Destinataire : si l'utilisateur est supprimé → on supprime aussi ses activités
            $table->foreign('recipient_id')
                  ->references('id')->on('users')
                  ->cascadeOnDelete();

            // 👤 Acteur : si supprimé → on garde l'activité mais on met actor_id à NULL
            $table->foreign('actor_id')
                  ->references('id')->on('users')
                  ->nullOnDelete();

            // 📰 Article ciblé : si supprimé → on conserve l'activité, article_id devient NULL
            $table->foreign('article_id')
                  ->references('id')->on('articles')
                  ->nullOnDelete();

            // 💬 Commentaire ciblé : si supprimé → on conserve l'activité, comment_id devient NULL
            $table->foreign('comment_id')
                  ->references('id')->on('comments')
                  ->nullOnDelete();
        });

        /* ==================== Index composites utiles ==================== */
        Schema::table('activities', function (Blueprint $table) {
            // Inbox d’un user (tri par récence)
            $table->index(['recipient_id', 'created_at'], 'activities_inbox_idx');

            // Non lus d’un user (première page rapide)
            $table->index(['recipient_id', 'read_at', 'created_at'], 'activities_unread_idx');

            // Filtrer par type pour un user (ex: comment_approved)
            $table->index(['recipient_id', 'type', 'created_at'], 'activities_user_type_idx');

            // Multi-tenant : boîte d’un user dans un tenant
            $table->index(['tenant_id', 'recipient_id', 'created_at'], 'activities_tenant_user_idx');

            // Routage article → liste d’activités associées (affichage, purge…)
            $table->index(['article_id', 'created_at'], 'activities_article_created_idx');
        });
    }

    public function down(): void
    {
        // (Option simple) supprimer la table supprime aussi les contraintes
        Schema::dropIfExists('activities');
    }
};
