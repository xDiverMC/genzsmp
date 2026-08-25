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
        Schema::create('invest_limit_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invest_user_id')->nullable()->constrained('invest_users')->onDelete('cascade');
            $table->string('player_name')->index();
            $table->string('asset', 10);
            $table->string('order_type', 10); // BUY, SELL
            $table->decimal('amount', 16, 4);
            $table->decimal('target_price', 16, 2);
            $table->decimal('reserved_cost', 16, 2)->default(0);
            $table->string('status', 20)->default('PENDING')->index(); // PENDING, FILLED, CANCELLED
            $table->decimal('filled_price', 16, 2)->nullable();
            $table->timestamp('filled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('invest_price_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invest_user_id')->nullable()->constrained('invest_users')->onDelete('cascade');
            $table->string('player_name')->index();
            $table->string('asset', 10);
            $table->decimal('target_price', 16, 2);
            $table->string('condition', 10); // ABOVE, BELOW
            $table->decimal('initial_price', 16, 2);
            $table->boolean('is_triggered')->default(false)->index();
            $table->timestamp('triggered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('invest_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('sender_name')->index();
            $table->string('receiver_name')->index();
            $table->string('asset', 10);
            $table->decimal('amount', 16, 4);
            $table->decimal('fee', 16, 4);
            $table->decimal('received_amount', 16, 4);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invest_transfers');
        Schema::dropIfExists('invest_price_alerts');
        Schema::dropIfExists('invest_limit_orders');
    }
};
