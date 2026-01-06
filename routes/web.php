<?php

use Illuminate\Support\Facades\Route;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

use App\Models\Operation;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\SemanticCategoryController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TelegramMiniAppController;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect('/analytics');
    }
    return view('welcome');
})->name('home');

Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware(['auth'])->group(function () {

    Route::get('/analytics', [AnalyticsController::class, 'index']);

    Route::prefix('api/analytics')->group(function () {
        Route::get('/summary', [AnalyticsController::class, 'summary']);
        Route::get('/categories', [AnalyticsController::class, 'categories']);
        Route::get('/by-category', [AnalyticsController::class, 'byCategory']);
        Route::get('/by-semantic-category', [AnalyticsController::class, 'bySemanticCategory']);
        Route::get('/by-date', [AnalyticsController::class, 'byDate']);
        Route::get('/by-user', [AnalyticsController::class, 'byUser']);
        Route::get('/by-bank', [AnalyticsController::class, 'byBank']);
        Route::get('/operations', [AnalyticsController::class, 'operations']);
    });

    Route::prefix('api/semantic')->group(function () {
        Route::post('/populate', [SemanticCategoryController::class, 'populate']);
        Route::get('/status', [SemanticCategoryController::class, 'status']);
    });
});

Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle']);

Route::get('/telegram-mini-app', [TelegramMiniAppController::class, 'index']);

Route::prefix('api/telegram-mini-app')->middleware('telegram.miniapp')->group(function () {
    Route::get('/summary', [TelegramMiniAppController::class, 'summary']);
    Route::get('/by-category', [TelegramMiniAppController::class, 'byCategory']);
    Route::get('/by-semantic-category', [TelegramMiniAppController::class, 'bySemanticCategory']);
    Route::get('/by-date', [TelegramMiniAppController::class, 'byDate']);
    Route::get('/operations', [TelegramMiniAppController::class, 'operations']);
});
