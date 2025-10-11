<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->morphs('owner');
            $table->string('slug')->unique(); // wallet-1, savings-account, escrow-order-123
            $table->string('name'); // Checking Account, Savings, Order Escrow
            $table->string('currency')->default('USD');
            $table->boolean('is_active')->default(true);
            $table->decimal('total_balance', 20, 8)->default(0);
            $table->decimal('available_balance', 20, 8)->default(0);
            $table->decimal('held_balance', 20, 8)->default(0);
            $table->decimal('confirmed_balance', 20, 8)->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();
            
            // Composite unique index - one wallet slug per owner
            $table->unique(['owner_type', 'owner_id', 'slug']);
            $table->index(['owner_type', 'owner_id']);
            $table->index(['slug']);
            $table->index(['is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};