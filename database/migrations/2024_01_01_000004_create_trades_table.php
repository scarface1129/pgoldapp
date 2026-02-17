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
        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('reference')->unique();
            $table->enum('type', ['buy', 'sell']);
            $table->string('crypto_symbol', 10); // BTC, ETH, USDT
            $table->decimal('crypto_amount', 20, 8); // Amount of crypto bought/sold
            $table->decimal('rate', 20, 2); // Exchange rate at time of trade (NGN per 1 crypto)
            $table->decimal('subtotal', 20, 2); // crypto_amount * rate
            $table->decimal('fee_percentage', 5, 2); // Fee percentage applied
            $table->decimal('fee_amount', 20, 2); // Fee in NGN
            $table->decimal('total_amount', 20, 2); // Final amount (subtotal +/- fee)
            $table->enum('status', ['pending', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->json('rate_data')->nullable(); // Store CoinGecko response for audit
            $table->text('failure_reason')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'crypto_symbol']);
            $table->index('status');
            $table->index('reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trades');
    }
};
