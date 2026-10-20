<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\TestController;
use Illuminate\Support\Facades\Route;

Route::prefix('/v1')->group(function () {

    // Route For Debug
    Route::prefix('/test')->controller(TestController::class)->group(function () {
        Route::get('/users', 'getAllUsers');
    });

    // Route for authentication
    Route::prefix('/auth')->controller(AuthController::class)->group(function () {
        Route::post('/register', 'register');
        Route::post('/login', 'login');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/reset-password', 'resetPassword');
        });
    });
});
