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
        // Historique de modération : avertissements, blocages, déblocages, signalements internes.
        Schema::create('moderation_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // compte visé
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete(); // null = automatique (fin de blocage)
            $table->string('type', 20); // App\Enums\ModerationType
            $table->string('reason', 40)->nullable(); // App\Enums\ModerationReason (absent pour un déblocage automatique)
            $table->text('message')->nullable(); // visible par l'utilisateur (avertissement, blocage, déblocage)
            $table->text('internal_note')->nullable(); // visible des admins seulement
            $table->timestamp('ends_at')->nullable(); // fin d'un blocage temporaire
            $table->timestamp('acknowledged_at')->nullable(); // avertissement lu (« J'ai compris »)
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'type', 'created_at']);
            $table->index(['type', 'created_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            // Fin d'un blocage temporaire (null + statut suspended = jusqu'à nouvel ordre).
            $table->timestamp('blocked_until')->nullable()->after('approved_by')->index();
            // Signalement interne en cours (drapeau des listes admin).
            $table->timestamp('flagged_at')->nullable()->after('blocked_until')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['blocked_until']);
            $table->dropIndex(['flagged_at']);
            $table->dropColumn(['blocked_until', 'flagged_at']);
        });

        Schema::dropIfExists('moderation_actions');
    }
};
