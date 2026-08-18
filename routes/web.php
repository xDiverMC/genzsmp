<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\TradingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - GenzSMP Portal
|--------------------------------------------------------------------------
*/

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/trading', [TradingController::class, 'index'])->name('trading');
