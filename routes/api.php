<?php

use App\Http\Controllers\Api\MinecraftController;
use App\Http\Controllers\Api\TradingApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes for GenzSMP Minecraft Server
|--------------------------------------------------------------------------
*/

Route::prefix('server')->group(function () {
    Route::get('/status', [MinecraftController::class, 'status']);
});

Route::prefix('checkout')->group(function () {
    Route::post('/submit', [MinecraftController::class, 'submitCheckout']);
});

Route::prefix('trading')->group(function () {
    Route::post('/login', [TradingApiController::class, 'login']);
    Route::post('/trade', [TradingApiController::class, 'executeTrade']);
    Route::post('/setpin', [TradingApiController::class, 'setPin']);
    Route::get('/user/{playerName}', [TradingApiController::class, 'getUserState']);
});

Route::prefix('rcon')->group(function () {
    Route::post('/execute', [MinecraftController::class, 'executeRcon']);
    Route::post('/give-rank', [MinecraftController::class, 'giveRank']);
    Route::post('/give-money', [MinecraftController::class, 'giveMoney']);
    Route::post('/give-item', [MinecraftController::class, 'giveItem']);
});
