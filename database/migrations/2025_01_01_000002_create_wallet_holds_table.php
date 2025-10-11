<?php 

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wallet_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('wallets')->onDelete('cascade');
            $table->decimal('amount', 20, 8);
            $table->enum('status', ['active', 'released', 'captured'])->default('active');
            $table->string('reference')->unique();
            $table->json('meta')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamps();
            $table->index(['wallet_id', 'status']);
            $table->index(['reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_holds');
    }
};