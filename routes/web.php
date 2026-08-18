<?php

use App\Http\Controllers\Admin\RconController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - GenzSMP Portal
|--------------------------------------------------------------------------
*/

Route::get('/', [HomeController::class, 'index'])->name('home');

// Admin Console Routes
Route::prefix('admin/rcon')->name('admin.rcon.')->group(function () {
    Route::get('/', [RconController::class, 'index'])->name('index');
    Route::post('/execute', [RconController::class, 'execute'])->name('execute');
    Route::post('/deliver/{id}', [RconController::class, 'deliverOrder'])->name('deliver');
});
