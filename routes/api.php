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
use App\Http\Controllers\SliderController;
use App\Http\Controllers\DiscountController;
use App\Http\Controllers\FlashSaleController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\SeoSchemaController;


use App\Http\Controllers\TransactionLogController;


// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');
Route::post('/login', [AuthController::class, 'store'])->name('login');

/* Protect routes with authentication */
Route::middleware(['auth:sanctum'])->group(function () {

	Route::post('/seo-schema', [SeoSchemaController::class, 'store']); // Create or Update SEO Schema
	Route::get('/seo-schema/{type}/{id}', [SeoSchemaController::class, 'show']); // Get SEO Schema

	Route::get('/allcategories', [CategoryController::class, 'allcategories']);

	Route::prefix('users')->group(function () {
		Route::post('/', [UserController::class, 'store']);
		Route::get('/', [UserController::class, 'index']);
		Route::get('/{id}', [UserController::class, 'show']);
		Route::post('/{id}', [UserController::class, 'update'])->name('users.update');
		Route::delete('/{id}', [UserController::class, 'destroy']);
		Route::put('/{id}', [UserController::class, 'update']);

	});

	Route::post('/attributes/import', [AttributeController::class, 'import']);
	Route::post('/attributes/export', [AttributeController::class, 'export']);
	Route::resource('attributes', AttributeController::class);
	Route::resource('attribute-groups', AttributeGroupController::class);


	Route::post('category-attributes/{id}/add-attribute', [CategoryAttributeController::class, 'addAttributes']);
	Route::delete('category-attributes/{id}/remove-attribute', [CategoryAttributeController::class, 'removeAttributes']);
	Route::resource('category-attributes', CategoryAttributeController::class);


	Route::resource('transaction-logs', TransactionLogController::class)->only(['index']);
	Route::resource('categories', CategoryController::class)->only(['index']);
	Route::resource('websites', WebsiteController::class)->only(['index']);
	Route::get('/products/export', [ProductExportController::class, 'export']);
	Route::get('products/product-input', [ProductController::class, 'getProductInputs']);

	Route::get('products/product-category-attribute-groups', [ProductController::class, 'product']);
	Route::resource('products', ProductController::class);
	Route::apiResource('brands', BrandController::class);
	Route::apiResource('stores', StoreController::class);



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
	Route::apiResource('sliders', SliderController::class);

	// Discount API Routes
	Route::apiResource('discounts', DiscountController::class);

	// Flash Sale API Routes
	Route::apiResource('flash-sales', FlashSaleController::class);

	Route::apiResource('newsletters', NewsletterController::class);


});
Route::get('/category-pages/{category}', [CategoryPageController::class, 'show']);

