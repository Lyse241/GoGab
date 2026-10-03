<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jeton unique envoyé par la page de commande : un double clic sur « Commander » (ou un
     * renvoi du formulaire) ne crée jamais deux commandes.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Unique par client : un jeton ne sert qu'à son auteur.
            $table->string('checkout_token', 64)->nullable()->after('reference');
            $table->unique(['client_id', 'checkout_token']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['client_id', 'checkout_token']);
            $table->dropColumn('checkout_token');
        });
    }
};
