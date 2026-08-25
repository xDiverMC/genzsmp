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

Route::middleware(['throttle:60,1'])->prefix('trading')->group(function () {
    Route::post('/login', [TradingApiController::class, 'login']);
    Route::get('/market-data', [TradingApiController::class, 'getMarketData']);
    Route::post('/trade', [TradingApiController::class, 'executeTrade'])->middleware('throttle:30,1');
    Route::post('/limit-order', [TradingApiController::class, 'createLimitOrder'])->middleware('throttle:30,1');
    Route::get('/limit-orders', [TradingApiController::class, 'getLimitOrders']);
    Route::post('/cancel-limit-order', [TradingApiController::class, 'cancelLimitOrder']);
    Route::post('/transfer', [TradingApiController::class, 'transferAsset'])->middleware('throttle:20,1');
    Route::post('/alert', [TradingApiController::class, 'createPriceAlert']);
    Route::get('/alerts', [TradingApiController::class, 'getPriceAlerts']);
    Route::post('/cancel-alert', [TradingApiController::class, 'cancelPriceAlert']);
    Route::get('/user/{playerName}', [TradingApiController::class, 'getUserState']);
    Route::get('/leaderboard', [TradingApiController::class, 'getLeaderboard']);
});

// Outbound HTTPS Bridge for ArqoInvest Java Plugin (No open ports needed on Minecraft server!)
Route::prefix('invest')->group(function () {
    Route::post('/sync', [\App\Http\Controllers\Api\InvestSyncController::class, 'sync']);
    Route::post('/setpin', [\App\Http\Controllers\Api\InvestSyncController::class, 'setPin']);
    Route::post('/trade', [\App\Http\Controllers\Api\InvestSyncController::class, 'inGameTrade']);
    Route::post('/transfer', [\App\Http\Controllers\Api\InvestSyncController::class, 'inGameTransfer']);
    Route::post('/alert/set', [\App\Http\Controllers\Api\InvestSyncController::class, 'inGameSetAlert']);
    Route::post('/alert/list', [\App\Http\Controllers\Api\InvestSyncController::class, 'inGameListAlerts']);
    Route::post('/alert/remove', [\App\Http\Controllers\Api\InvestSyncController::class, 'inGameRemoveAlert']);
});

