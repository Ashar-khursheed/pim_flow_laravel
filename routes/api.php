<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::post('/login', [AuthController::class, 'store']);


Route::prefix('users')->group(function () {
    Route::post('/', [UserController::class, 'store']); // Create User
    Route::get('/', [UserController::class, 'index']); // Get Users
    Route::get('/{id}', [UserController::class, 'show']); // Get Single User
    Route::post('/{id}', [UserController::class, 'update']); // Update User
    Route::delete('/{id}', [UserController::class, 'destroy']); // Delete User
})->middleware('auth:sanctum');




Route::prefix('roles')->group(function () {
    Route::get('/', [RoleController::class, 'index']);
    Route::post('/', [RoleController::class, 'store']);
    Route::get('/{id}', [RoleController::class, 'show']);
    Route::put('/{id}', [RoleController::class, 'update']);
    Route::delete('/{id}', [RoleController::class, 'destroy']);
});
