<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CryptoController;
use App\Http\Controllers\Api\TradeController;
use App\Http\Controllers\Api\WalletController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
*/

// Public routes
Route::prefix('v1')->group(function () {
    // Authentication
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Public crypto prices (no auth required)
    Route::get('/crypto/prices', [CryptoController::class, 'prices']);
    Route::get('/crypto/prices/{symbol}', [CryptoController::class, 'price']);
    Route::get('/crypto/supported', [TradeController::class, 'supportedCryptos']);
});

// Protected routes
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/profile', [AuthController::class, 'profile']);

    // Wallet
    Route::get('/wallet', [WalletController::class, 'show']);
    Route::post('/wallet/deposit', [WalletController::class, 'deposit']);
    Route::post('/wallet/withdraw', [WalletController::class, 'withdraw']);
    Route::get('/wallet/transactions', [WalletController::class, 'transactions']);

    // Trading
    Route::post('/trade/buy', [TradeController::class, 'buy']);
    Route::post('/trade/sell', [TradeController::class, 'sell']);
    Route::post('/trade/quote', [TradeController::class, 'quote']);
    Route::get('/trade/history', [TradeController::class, 'history']);
    Route::get('/trade/{reference}', [TradeController::class, 'show']);

    // Crypto Portfolio
    Route::get('/crypto/portfolio', [CryptoController::class, 'portfolio']);
    Route::get('/crypto/holdings', [CryptoController::class, 'holdings']);
});
