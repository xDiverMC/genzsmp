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
            $table->string('gamertag');
            $table->string('edition')->default('Java');
            $table->string('item_name');
            $table->string('item_type')->default('rank');
            $table->string('price');
            $table->string('payment_method')->default('QRIS');
            $table->enum('status', ['pending', 'paid', 'delivered', 'failed', 'cancelled'])->default('pending');
            $table->text('rcon_command')->nullable();
            $table->text('rcon_response')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('rcon_logs', function (Blueprint $table) {
            $table->id();
            $table->text('command');
            $table->text('response')->nullable();
            $table->boolean('success')->default(true);
            $table->string('ip_address', 45)->nullable();
            $table->string('executed_by')->default('system');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rcon_logs');
        Schema::dropIfExists('orders');
    }
};
