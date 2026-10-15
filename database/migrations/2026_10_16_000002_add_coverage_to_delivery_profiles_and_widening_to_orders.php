<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_profiles', function (Blueprint $table) {
            // Rayon de couverture du livreur (km à vol d'oiseau autour de son quartier de départ).
            $table->unsignedSmallInteger('coverage_km')->default(10)->after('base_neighborhood_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            // Annonce élargie à tous les livreurs du Grand Libreville (restée sans livreur trop longtemps).
            $table->timestamp('widened_at')->nullable()->after('announcement_count');
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('widened_at'));
        Schema::table('delivery_profiles', fn (Blueprint $table) => $table->dropColumn('coverage_km'));
    }
};
