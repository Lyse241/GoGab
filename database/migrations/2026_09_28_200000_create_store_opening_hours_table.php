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
        // Une ligne par jour et par commerce (1 = lundi … 7 = dimanche, ISO-8601).
        // closes_at < opens_at : le créneau finit le lendemain (ex. 18:00 → 02:00).
        // opens_at = closes_at : ouvert 24 h/24 ce jour-là.
        Schema::create('store_opening_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->boolean('is_closed')->default(false); // jour de repos
            $table->timestamps();

            $table->unique(['store_id', 'day_of_week']);
        });

        // Anciennes colonnes d'horaires uniques (si présentes) : recopiées sur les 7 jours.
        if (Schema::hasColumns('stores', ['opens_at', 'closes_at'])) {
            foreach (DB::table('stores')->get(['id', 'opens_at', 'closes_at']) as $store) {
                if ($store->opens_at === null || $store->closes_at === null) {
                    continue;
                }

                DB::table('store_opening_hours')->insert(array_map(fn (int $day) => [
                    'store_id' => $store->id,
                    'day_of_week' => $day,
                    'opens_at' => $store->opens_at,
                    'closes_at' => $store->closes_at,
                    'is_closed' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], range(1, 7)));
            }

            Schema::table('stores', function (Blueprint $table) {
                $table->dropColumn(['opens_at', 'closes_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_opening_hours');
    }
};
