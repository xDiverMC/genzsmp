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
        Schema::create('invest_actions', function (Blueprint $table) {
            $table->id();
            $table->string('player_name')->index();
            $table->string('action_type', 20); // WITHDRAW, DEPOSIT
            $table->decimal('amount', 16, 2);
            $table->string('reason')->nullable();
            $table->string('status', 20)->default('PENDING')->index(); // PENDING, COMPLETED, FAILED
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invest_actions');
    }
};
