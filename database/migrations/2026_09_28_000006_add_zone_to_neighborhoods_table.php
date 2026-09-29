<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Zone de Libreville (Nord, Centre, Est, Sud) : définit « les livreurs autour »
        // d'un commerce, sans GPS. Renseignée par NeighborhoodSeeder.
        Schema::table('neighborhoods', function (Blueprint $table) {
            $table->string('zone', 20)->nullable()->after('name')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('neighborhoods', function (Blueprint $table) {
            $table->dropIndex(['zone']);
            $table->dropColumn('zone');
        });
    }
};
