<?php

use App\Http\Controllers\Api\V1\AdminArtworkController;
use App\Http\Controllers\Api\V1\AdminDashboardController;
use App\Http\Controllers\Api\V1\ArtworkController;
use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    // Public catalog & upload endpoints
    Route::get('/artworks', [ArtworkController::class, 'index']);
    Route::get('/artworks/{artwork}', [ArtworkController::class, 'show']);
    Route::post('/artworks', [ArtworkController::class, 'store'])
        ->middleware('throttle:uploads');
    Route::post('/artworks/{artwork}/like', [ArtworkController::class, 'like'])
        ->middleware('throttle:likes');
    Route::get('/artworks/{artwork}/like-status', [ArtworkController::class, 'likeStatus']);

    // Authentication endpoints
    Route::prefix('auth')->group(function (): void {
        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:login');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
        });
    });

    // Administrator endpoints
    Route::prefix('admin')->middleware('auth:sanctum')->group(function (): void {
        Route::get('/dashboard', [AdminDashboardController::class, 'index']);
        Route::get('/artworks', [AdminArtworkController::class, 'index']);
        Route::get('/artworks/{artwork}', [AdminArtworkController::class, 'show']);
        Route::put('/artworks/{artwork}', [AdminArtworkController::class, 'update']);
        Route::delete('/artworks/{artwork}', [AdminArtworkController::class, 'destroy']);
    });
});
