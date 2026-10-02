<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Signalements d'un utilisateur par l'autre partie d'une commande, traités par les admins.
     * La personne signalée n'y a jamais accès.
     */
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reported_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason', 40); // App\Enums\ReportReason
            $table->text('description');
            $table->string('status', 20)->default('open'); // App\Enums\ReportStatus
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->text('admin_note')->nullable(); // jamais montrée au signalant ni à la personne signalée
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['reported_user_id', 'status']);
            $table->index(['order_id', 'reporter_id', 'reported_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
