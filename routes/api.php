<?php

use App\Http\Controllers\Api\MinecraftController;
use App\Http\Controllers\Api\TradingApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes for GenzSMP Minecraft Server
|--------------------------------------------------------------------------
*/

Route::middleware(['throttle:60,1'])->group(function () {
    Route::prefix('server')->group(function () {
        Route::get('/status', [MinecraftController::class, 'status']);
    });

    Route::prefix('checkout')->group(function () {
        Route::post('/submit', [MinecraftController::class, 'submitCheckout']);
    });
});

Route::middleware(['throttle:30,1'])->prefix('trading')->group(function () {
    Route::post('/login', [TradingApiController::class, 'login']);
    Route::post('/trade', [TradingApiController::class, 'executeTrade'])->middleware('throttle:15,1');
    Route::get('/user/{playerName}', [TradingApiController::class, 'getUserState']);
    Route::get('/leaderboard', [TradingApiController::class, 'getLeaderboard']);
});

// Outbound HTTPS Bridge for ArqoInvest Java Plugin (No open ports needed on Minecraft server!)
Route::prefix('invest')->group(function () {
    Route::post('/sync', [\App\Http\Controllers\Api\InvestSyncController::class, 'sync']);
    Route::post('/setpin', [\App\Http\Controllers\Api\InvestSyncController::class, 'setPin']);
    Route::post('/trade', [\App\Http\Controllers\Api\InvestSyncController::class, 'inGameTrade']);
});

