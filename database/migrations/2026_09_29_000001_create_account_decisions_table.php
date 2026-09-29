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
        // Historique d'un dossier d'inscription : envoi, décisions sur les documents et le compte, corrections.
        Schema::create('account_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // compte concerné
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete(); // auteur (admin ou l'utilisateur)
            $table->string('action', 30); // App\Enums\AccountDecisionAction
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->string('document_type', 40)->nullable(); // gardé si le document disparaît
            $table->text('note')->nullable(); // motif d'un refus
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_decisions');
    }
};
