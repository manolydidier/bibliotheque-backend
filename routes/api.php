<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserCreationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserRolePermissionController;
use Illuminate\Support\Facades\Log;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
use Illuminate\Support\Facades\DB;


Route::get('/check-db', function () {
    return DB::connection()->getDatabaseName();
});




Route::post('/register', [AuthController::class, 'register']);
Route::get('/user/{id}/roles-permissions', [UserRolePermissionController::class, 'show']);
Route::get('/users-roles-permissions', [UserRolePermissionController::class, 'index']);
Route::post('/users', [UserCreationController::class, 'store']);

Route::post('/login', [AuthController::class, 'login']);



    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/user/{id}/avatar', [AuthController::class, 'updateAvatar']);
    Route::post('/auth/{id}/updatepassword', [AuthController::class, 'updatePassword']);


    // Testez ceci dans routes/web.php
Route::get('/test-log', function() {
    \Log::info('Ceci est un test de log');
    return response()->json(['message' => 'Check storage/logs/laravel.log']);
});

