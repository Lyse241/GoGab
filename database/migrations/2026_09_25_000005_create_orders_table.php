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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users');
            $table->foreignId('delivery_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('neighborhood_id')->constrained();
            $table->decimal('total_price', 10, 2);
            $table->text('address_landmarks');
            $table->string('payment_method');
            $table->enum('status', ['en_attente', 'acceptee', 'en_livraison', 'livree'])->default('en_attente');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
