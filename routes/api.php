<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\WebsiteController;
use App\Http\Controllers\AttributeFamilyController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryPageController;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');
Route::post('/login', [AuthController::class, 'store'])->name('login');

/* Protect routes with authentication */
Route::middleware(['auth:sanctum'])->group(function () {
	Route::prefix('users')->group(function () {
		Route::post('/', [UserController::class, 'store']);
		Route::get('/', [UserController::class, 'index']);
		Route::get('/{id}', [UserController::class, 'show']);
		Route::post('/{id}', [UserController::class, 'update'])->name('users.update');
		Route::delete('/{id}', [UserController::class, 'destroy']);
		Route::put('/{id}', [UserController::class, 'update']);

	});

	Route::apiResource('attribute-families', AttributeFamilyController::class);
	Route::get('/categories/last-child', [AttributeFamilyController::class, 'lastChildCategories']);

	Route::resource('categories', CategoryController::class)->only(['index']);
	Route::resource('websites', WebsiteController::class)->only(['index']);
	Route::get('products/product-input', [ProductController::class, 'getProductInputs']);
	Route::resource('products', ProductController::class);


	Route::post('/category-pages', [CategoryPageController::class, 'store']);
	Route::put('/category-pages/{category}', [CategoryPageController::class, 'update']);
    Route::delete('/category-pages/{category}', [CategoryPageController::class, 'destroy']);

});

    Route::get('/category-pages/{category}', [CategoryPageController::class, 'show']);
   
Route::prefix('roles')->group(function () {
	Route::get('/', [RoleController::class, 'index']);
	Route::post('/', [RoleController::class, 'store']);
	Route::get('/{id}', [RoleController::class, 'show']);
	Route::put('/{id}', [RoleController::class, 'update']);
	Route::delete('/{id}', [RoleController::class, 'destroy']);
});
