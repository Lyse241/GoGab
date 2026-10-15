<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Avis d'un client sur une commande livrée : une note pour le commerce, une pour le livreur
     * (s'il y en avait un) et un commentaire facultatif. Un seul avis par commande.
     */
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('courier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('store_rating');
            $table->unsignedTinyInteger('courier_rating')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index('store_id');
            $table->index('courier_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
