<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\RoleController;
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



    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum')->middleware('auth:sanctum');
    Route::post('/user/{id}/avatar', [AuthController::class, 'updateAvatar'])->middleware('auth:sanctum');
    Route::post('/auth/{id}/updatepassword', [AuthController::class, 'updatePassword'])->middleware('auth:sanctum');
    Route::get('/user/{id}/profile', [AuthController::class, 'showProfile'])->middleware('auth:sanctum');
    Route::post('/user/{id}/edit', [AuthController::class, 'updateProfile'])->middleware('auth:sanctum');
    Route::get('/users', [AuthController::class, 'index'])->middleware('auth:sanctum');
    Route::get('/users', [AuthController::class, 'index'])->middleware('auth:sanctum');
    Route::delete('users/{id}/delete', [AuthController::class, 'delete'])->middleware('auth:sanctum');
    Route::post('users/{id}/activate', [AuthController::class, 'activate']);
    Route::post('users/{id}/deactivate', [AuthController::class, 'deactivate']);

    Route::middleware('auth:sanctum')->prefix('roles')->group(function () {
    Route::get('/', [RoleController::class, 'index']);
    Route::get('/rolesliste', [RoleController::class, 'index2']);
    Route::get('{id}', [RoleController::class, 'show']);
    Route::post('/insert', [RoleController::class, 'store']);
    Route::put('{id}', [RoleController::class, 'update']);
    Route::delete('/{id}/delete', [RoleController::class, 'destroy']);
});

    //  Route::post('{id}/restore', [AuthController::class, 'restore']);       // ♻️ Restaurer un utilisateur soft deleted
    //  Route::delete('{id}/force', [AuthController::class, 'forceDelete']);

    // Testez ceci dans routes/web.php
    Route::get('/test-log', function() {
    Log::info('Ceci est un test de log');
    return response()->json(['message' => 'Check storage/logs/laravel.log']);
});

