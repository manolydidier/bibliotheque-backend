<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('societes', function (Blueprint $table) {
            // déjà existants : id, name, slug, logo_url, primary_color, contact_email,
            // contact_phone, website_url, is_active, created_at, updated_at

            // ➕ nouveaux champs "Coordonnées & contact"
            $table->string('responsable')->nullable()->after('slug');
            $table->string('adresse')->nullable()->after('responsable');
            $table->string('ville')->nullable()->after('adresse');
            $table->string('pays')->nullable()->after('ville');

            // ➕ description si tu veux la stocker aussi
            $table->text('description')->nullable()->after('pays');
        });
    }

    public function down(): void
    {
        Schema::table('societes', function (Blueprint $table) {
            $table->dropColumn(['responsable', 'adresse', 'ville', 'pays', 'description']);
        });
    }
};
