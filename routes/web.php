<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => config('app.name'),
        'message' => 'Api is running',
        'status' => 'Success'
    ]);
});

Route::get('/dashboard', [DashboardController::class, 'index']);
