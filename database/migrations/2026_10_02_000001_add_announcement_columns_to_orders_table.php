<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Annonce de livraison : heure de la dernière publication (ou relance) et nombre d'envois.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('announced_at')->nullable()->after('status');
            $table->unsignedSmallInteger('announcement_count')->default(0)->after('announced_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['announced_at', 'announcement_count']);
        });
    }
};
