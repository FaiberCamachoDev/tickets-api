<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\TicketController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:api')->group(function () {

    // — Auth —
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me',      [AuthController::class, 'me']);

        // Tickets
        Route::apiResource('tickets', TicketController::class);

        // Devices
        Route::get('devices', [DeviceController::class, 'index']);
        Route::post('devices/assign', [DeviceController::class, 'assign']);
    });

});
