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
        Schema::create('delivery_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('vehicle_type', 20);
            $table->string('vehicle_brand')->nullable();
            $table->string('plate_number')->nullable();
            $table->string('license_number')->nullable();
            $table->foreignId('base_neighborhood_id')->constrained('neighborhoods')->restrictOnDelete();
            $table->boolean('is_available')->default(false);
            $table->timestamps();

            $table->index(['base_neighborhood_id', 'is_available']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_profiles');
    }
};
