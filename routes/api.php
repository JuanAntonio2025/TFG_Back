<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\LibraryController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Auth pública
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Catálogo público
    Route::get('/books', [BookController::class, 'index']);
    Route::get('/books/{bookId}', [BookController::class, 'show']);
    Route::get('/categories', [CategoryController::class, 'index']);
});

Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // Auth protegida
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Cart
    Route::get('/cart', [CartController::class, 'show']);
    Route::post('/cart/items', [CartController::class, 'storeItem']);
    Route::put('/cart/items/{bookId}', [CartController::class, 'updateItem']);
    Route::delete('/cart/items/{bookId}', [CartController::class, 'deleteItem']);

    // Orders
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{orderId}', [OrderController::class, 'show']);

    // Library
    Route::get('/library', [LibraryController::class, 'index']);
});
