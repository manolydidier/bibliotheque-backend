<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bureaux', function (Blueprint $table) {
            $table->id();

            $table->foreignId('societe_id')
                ->constrained('societes')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('name');              // "Siège SAF/FJKM - Antananarivo"
            $table->string('type')->nullable();  // "siege", "antenne", etc.

            $table->string('city');
            $table->string('country')->default('Madagascar');
            $table->string('address')->nullable();

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('image_url')->nullable();

            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bureaux');
    }
};
