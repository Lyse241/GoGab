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
        // L'ENUM MySQL devient une chaîne : les valeurs sont portées par App\Enums\OrderStatus.
        Schema::table('orders', function (Blueprint $table) {
            $table->string('status', 30)->default('en_attente')->change();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('reference', 20)->nullable()->unique()->after('id'); // ex. GG-000123
            $table->foreignId('store_id')->nullable()->after('reference')->constrained()->restrictOnDelete();
            $table->decimal('subtotal', 10, 2)->default(0)->after('address_landmarks');
            $table->decimal('delivery_fee', 10, 2)->default(0)->after('subtotal');
            // Montant en espèces que le client remettra (paiement cash uniquement).
            $table->decimal('cash_given', 10, 2)->nullable()->after('payment_method');
            $table->text('client_note')->nullable()->after('cash_given');
            $table->text('cancel_reason')->nullable()->after('client_note');
            $table->index('status');
        });

        // Commandes existantes : boutique déduite des lignes, sous-total = total (pas de frais en v1).
        foreach (DB::table('orders')->pluck('id') as $id) {
            DB::table('orders')->where('id', $id)->update([
                'reference' => 'GG-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT),
                'store_id' => DB::table('order_items')
                    ->join('products', 'products.id', '=', 'order_items.product_id')
                    ->where('order_items.order_id', $id)
                    ->value('products.store_id'),
            ]);
        }

        DB::table('orders')->update(['subtotal' => DB::raw('total_price')]);
        DB::table('orders')->where('payment_method', 'cash_on_delivery')->update(['payment_method' => 'cash']);

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('store_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('orders')->where('payment_method', 'cash')->update(['payment_method' => 'cash_on_delivery']);
        DB::table('orders')
            ->whereNotIn('status', ['en_attente', 'acceptee', 'en_livraison', 'livree'])
            ->update(['status' => 'en_attente']);

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['store_id']);
            $table->dropUnique(['reference']);
            $table->dropIndex(['status']);
            $table->dropColumn([
                'reference',
                'store_id',
                'subtotal',
                'delivery_fee',
                'cash_given',
                'client_note',
                'cancel_reason',
            ]);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->enum('status', ['en_attente', 'acceptee', 'en_livraison', 'livree'])->default('en_attente')->change();
        });
    }
};
