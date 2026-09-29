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
        // L'ENUM MySQL (admin, delivery, client) devient une chaîne : les valeurs
        // autorisées sont portées par App\Enums\Role (ajout du rôle business).
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('client')->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('phone');
            $table->index('role');
            $table->string('account_status', 20)->default('pending')->after('role')->index();
            $table->text('rejection_reason')->nullable()->after('account_status');
            $table->foreignId('neighborhood_id')->nullable()->after('rejection_reason')
                ->constrained()->nullOnDelete();
            $table->text('address_landmarks')->nullable()->after('neighborhood_id');
            $table->timestamp('approved_at')->nullable()->after('address_landmarks');
            $table->foreignId('approved_by')->nullable()->after('approved_at')
                ->constrained('users')->nullOnDelete();
        });

        // Les comptes créés avant la validation admin sont considérés comme validés.
        DB::table('users')->update([
            'account_status' => 'approved',
            'approved_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['neighborhood_id']);
            $table->dropUnique(['phone']);
            $table->dropIndex(['role']);
            $table->dropIndex(['account_status']);
            $table->dropColumn([
                'account_status',
                'rejection_reason',
                'neighborhood_id',
                'address_landmarks',
                'approved_at',
                'approved_by',
            ]);
        });

        // Le rôle business n'existe pas dans l'ancien ENUM.
        DB::table('users')->where('role', 'business')->update(['role' => 'client']);

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'delivery', 'client'])->default('client')->change();
        });
    }
};
