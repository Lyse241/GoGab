<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Suivi de la livraison : centre approximatif de chaque quartier (position du commerce et de
     * l'adresse sur la carte) et dernière position connue du livreur pendant une course.
     */
    public function up(): void
    {
        Schema::table('neighborhoods', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
        });

        Schema::table('delivery_profiles', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedSmallInteger('heading')->nullable();
            $table->unsignedInteger('accuracy')->nullable();
            $table->timestamp('located_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_profiles', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'heading', 'accuracy', 'located_at']);
        });

        Schema::table('neighborhoods', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
