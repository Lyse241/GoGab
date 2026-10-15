<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Grand livre en ajout seul : chaque ligne est un mouvement signé en FCFA entiers
     * (positif = Gogab doit ce montant à l'utilisateur, négatif = l'utilisateur le doit à Gogab).
     * Le solde d'un compte est la somme de ses lignes.
     */
    public function up(): void
    {
        Schema::create('wallet_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 30);
            $table->bigInteger('amount');
            $table->string('description');
            $table->timestamp('created_at')->useCurrent();

            // Une commande ne génère qu'une fois chaque type de ligne pour un même compte.
            $table->unique(['order_id', 'user_id', 'type']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_entries');
    }
};
