<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\WebsiteController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryPageController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\AttributeController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\AttributeGroupController;
use App\Http\Controllers\CategoryAttributeController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\FaqCategoryController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ProductExportController;


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
	Route::resource('attributes', AttributeController::class);
	Route::resource('attribute-groups', AttributeGroupController::class);
	Route::resource('category-attributes', CategoryAttributeController::class);

	Route::resource('categories', CategoryController::class)->only(['index']);
	Route::resource('websites', WebsiteController::class)->only(['index']);
	Route::get('/products/export', [ProductExportController::class, 'export']);
	Route::get('products/product-input', [ProductController::class, 'getProductInputs']);
	Route::resource('products', ProductController::class);
	Route::resource('brands', BrandController::class);
	Route::resource('stores', StoreController::class);



	Route::post('/category-pages', [CategoryPageController::class, 'store']);
	Route::put('/category-pages/{category}', [CategoryPageController::class, 'update']);
    Route::delete('/category-pages/{category}', [CategoryPageController::class, 'destroy']);


	Route::apiResource('media', MediaController::class)->parameters([
        'media' => 'folder'
    ]);

	Route::apiResource('faqs', FaqController::class);
	Route::apiResource('faq-categories', FaqCategoryController::class);

	Route::get('roles/names', [RoleController::class, 'getRoleNames']);
	Route::get('/roles/{role}/permissions', [RoleController::class, 'getRolePermissions']);
    Route::apiResource('roles', RoleController::class);
	


	Route::apiResource('reviews', ReviewController::class);



});
    Route::get('/category-pages/{category}', [CategoryPageController::class, 'show']);

