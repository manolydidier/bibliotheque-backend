<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DBController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\UserCreationController;
use App\Http\Controllers\UserRoleController;
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
   
    Route::delete('users/{id}/delete', [AuthController::class, 'delete'])->middleware('auth:sanctum');
    Route::post('users/{id}/activate', [AuthController::class, 'activate']);
    Route::post('users/{id}/deactivate', [AuthController::class, 'deactivate']);

    Route::middleware('auth:sanctum')->prefix('roles')->group(function () {
    Route::get('/', [RoleController::class, 'index']);
    Route::get('/rolesliste', [RoleController::class, 'index2']);
    Route::get('/{id}', [RoleController::class, 'show']);
    Route::post('/insert', [RoleController::class, 'store']);
    Route::put('{id}', [RoleController::class, 'update']);
    Route::delete('/{id}/delete', [RoleController::class, 'destroy']);
        });

        // Lecture accessible à tous les utilisateurs authentifiés
    Route::middleware(['auth:sanctum'])->prefix('userrole')->group(function () {
    Route::get('/', [UserRoleController::class, 'index']);
    Route::get('/{userRole}', [UserRoleController::class, 'show']);
    Route::get('/{userId}/roles', [UserRoleController::class, 'getUserRoles']);
    Route::get('/{roleId}/users', [UserRoleController::class, 'getRoleUsers']);
    Route::post('/user-roles', [UserRoleController::class, 'store']);
    Route::delete('/{roleId}/delete', [UserRoleController::class, 'destroy']);
        });

Route::middleware(['auth:sanctum'])->prefix('permissions')->group(function () {
    Route::get('/', [PermissionController::class, 'index']);           // GET    /api/permissions
    Route::post('/', [PermissionController::class, 'store']);          // POST   /api/permissions
    Route::get('/{id}', [PermissionController::class, 'show']);        // GET    /api/permissions/{id}
    Route::put('/{id}', [PermissionController::class, 'update']);      // PUT    /api/permissions/{id}
    Route::delete('/{id}', [PermissionController::class, 'destroy']);  // DELETE /api/permissions/{id}
});
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('role-permissions', RolePermissionController::class);
    Route::post('/role-permissions', [RolePermissionController::class, 'store']);
    Route::get('roles/{roleId}/permissions', [RolePermissionController::class, 'permissionsByRole']);
    Route::get('permissions/{permissionId}/roles', [RolePermissionController::class, 'rolesByPermission']);
});

    Route::post('/users/{userid}/role', [UserRoleController::class, 'update']);
    // Route::get('/tables', [DBController::class, 'getTables'])->middleware('auth:sanctum');
    Route::get('/tables', [DBController::class, 'getTables']);

    // Testez ceci dans routes/web.php
    Route::get('/test-log', function() {
    Log::info('Ceci est un test de log');
    return response()->json(['message' => 'Check storage/logs/laravel.log']);
});

