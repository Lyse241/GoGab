<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // L'ancienne image unique de la boutique devient sa photo de couverture.
        Schema::table('stores', function (Blueprint $table) {
            $table->renameColumn('image', 'cover_image');
        });

        Schema::table('stores', function (Blueprint $table) {
            // Nullable : les commerces seedés n'ont pas de compte entreprise.
            $table->foreignId('owner_id')->nullable()->unique()->after('id')
                ->constrained('users')->nullOnDelete();
            $table->text('description')->nullable()->after('category_id');
            $table->string('phone', 20)->nullable()->after('description');
            $table->foreignId('neighborhood_id')->nullable()->after('phone')
                ->constrained()->nullOnDelete();
            $table->text('address_landmarks')->nullable()->after('neighborhood_id');
            $table->string('logo')->nullable()->after('address_landmarks');
            // Fermeture temporaire manuelle (les horaires jour par jour viendront plus tard).
            $table->boolean('is_open')->default(true)->after('cover_image');
            // Activé quand l'admin valide le compte de l'entreprise.
            $table->boolean('is_active')->default(false)->after('is_open')->index();
        });

        // Les boutiques déjà en ligne restent visibles.
        DB::table('stores')->update(['is_active' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->dropForeign(['neighborhood_id']);
            $table->dropUnique(['owner_id']);
            $table->dropIndex(['is_active']);
            $table->dropColumn([
                'owner_id',
                'description',
                'phone',
                'neighborhood_id',
                'address_landmarks',
                'logo',
                'is_open',
                'is_active',
            ]);
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->renameColumn('cover_image', 'image');
        });
    }
};
