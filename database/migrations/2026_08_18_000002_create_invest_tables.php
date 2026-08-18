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
        Schema::create('invest_users', function (Blueprint $table) {
            $table->id();
            $table->string('player_name')->unique();
            $table->string('uuid', 36)->nullable()->index();
            $table->boolean('is_bedrock')->default(false);
            $table->string('pin_hash')->nullable();
            $table->decimal('cash_balance', 16, 2)->default(5000.00);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        Schema::create('invest_portfolios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invest_user_id')->constrained('invest_users')->onDelete('cascade');
            $table->string('player_name')->index();
            $table->string('asset', 10); // BTC, ETH, GLD, DIA, EMD
            $table->decimal('amount', 16, 4)->default(0);
            $table->decimal('avg_buy_price', 16, 2)->default(0);
            $table->timestamps();

            $table->unique(['invest_user_id', 'asset']);
        });

        Schema::create('invest_trades', function (Blueprint $table) {
            $table->id();
            $table->string('player_name')->index();
            $table->string('trade_type', 10); // BUY, SELL
            $table->string('asset', 10);
            $table->decimal('amount', 16, 4);
            $table->decimal('price', 16, 2);
            $table->decimal('subtotal', 16, 2);
            $table->decimal('tax', 16, 2);
            $table->decimal('total', 16, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invest_trades');
        Schema::dropIfExists('invest_portfolios');
        Schema::dropIfExists('invest_users');
    }
};
