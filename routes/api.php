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
use App\Http\Controllers\ClaudeAIController;
use App\Http\Controllers\SeoManagementController;
use App\Http\Controllers\SubCategoryController;
use App\Http\Controllers\BrandTemp2Controller;
use App\Http\Controllers\BrandTemp1Controller;
use App\Http\Controllers\BrandTemp3Controller;
use App\Http\Controllers\GradingController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderReturnController;
use App\Http\Controllers\OrderHistoryController;
use App\Http\Controllers\PreOnboardingVendorController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\RedirectLinkController;
use App\Http\Controllers\ProductImageUploadController;
use App\Http\Controllers\DocumentUploadController;
use App\Http\Controllers\SupplierScoreController;
use App\Http\Controllers\VendorController;


Route::get('/transactions', [PaymentController::class, 'getAllTransactions']);
Route::post('/payment/ccavenue/initiate', [PaymentController::class, 'initiatePayment']);
Route::post('/payment/ccavenue/callback', [PaymentController::class, 'paymentCallback']);
// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');
Route::post('/login', [AuthController::class, 'store'])->name('login');

/* Protect routes with authentication */
Route::middleware(['auth:sanctum'])->group(function () {
	Route::post('/calculate-grade', [GradingController::class, 'calculate']);
    Route::get('/grading/view/{product_id}', [GradingController::class, 'viewByProduct']);
    Route::put('/grading/update/{product_id}/{grade}', [GradingController::class, 'updateGradingRule']);



	Route::post('/seo-schema', [SeoSchemaController::class, 'store']); // Create or Update SEO Schema
	Route::get('/seo-schema/{type}/{id}', [SeoSchemaController::class, 'show']); // Get SEO Schema


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
	Route::get('category/getAttributesByCategory/{category_id}', [CategoryAttributeController::class, 'getAttributesByCategory']);

	Route::post('category-attributes/{id}/add-attribute', [CategoryAttributeController::class, 'addAttributes']);
	Route::delete('category-attributes/{id}/remove-attribute', [CategoryAttributeController::class, 'removeAttributes']);
	Route::resource('category-attributes', CategoryAttributeController::class);

	Route::post('/brand-temp-2/{id}', [CategoryAttributeController::class, 'update']);

	Route::apiResource('brand-temp-1', BrandTemp1Controller::class);
	Route::apiResource('brand-temp-2', BrandTemp2Controller::class);
	Route::apiResource('brand-temp-3', BrandTemp3Controller::class);

	Route::apiResource('vendors', VendorController::class);
	Route::apiResource('pre-onboarding-vendors', PreOnboardingVendorController::class);
	Route::get('/countries', [LocationController::class, 'getCountryList']);
	Route::get('/cities/{countryId}', [LocationController::class, 'getCityList']);
	Route::get('/zipcodes/{cityId}', [LocationController::class, 'getZipcodeList']);

	Route::resource('transaction-logs', TransactionLogController::class);

	Route::resource('websites', WebsiteController::class)->only(['index']);

	Route::get('/products/{id}/media/{type}/download', [BrandController::class, 'downloadMediaZip']);
	Route::get('products/{id}/media', [BrandController::class, 'getProductMedia']);
	Route::post('/products/export', [ProductExportController::class, 'export']);
	Route::post('products/import', [ProductController::class, 'import']);
	Route::get('products/product-input', [ProductController::class, 'getProductInputs']);
	Route::get('products/category/{category_id}', [ProductController::class, 'getProductsByCategory']);
	Route::get('products/product-category-attribute-groups', [ProductController::class, 'product']);
	Route::get('products/{id}/product-category-attribute-groups', [ProductController::class, 'productCategoryAttributeGroups']);
	Route::resource('products', ProductController::class);
	Route::get('/products/filtered-category/{category_id}', [ProductController::class, 'getFilteredProductsByCategory']);

	Route::get('getbrandsList', [BrandController::class, 'getBrandsList']);
	Route::get('brands/{brandid}/sku', [BrandController::class, 'getBrandSku']);
	Route::apiResource('brands', BrandController::class);

	Route::get('getStoresList', [StoreController::class, 'getStoresList']);
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

	Route::post('/seo-management/import', [SeoManagementController::class, 'import']);
	Route::post('/seo-management/export', [SeoManagementController::class, 'export']);
	Route::post('seo-management/{id}', [SeoManagementController::class, 'update']);
	Route::resource('seo-management', SeoManagementController::class);

	Route::post('seo-details', [SeoDetailController::class, 'store']);
	Route::put('seo-details/{id}', [SeoDetailController::class, 'update']);



	Route::post('/generate-reviews', [ClaudeAIController::class, 'generateReviews']);
	Route::post('/generate-faqs', [ClaudeAIController::class, 'generateFAQs']);
	Route::post('/generate-benefits-features', [ClaudeAIController::class, 'generateBenefitsFeatures']);
	Route::post('/generate-benefits-features-automation', [ClaudeAIController::class, 'generateFeaturesAndBenefits']);

	Route::get('subcategories', [SubCategoryController::class, 'index']);
	Route::get('subcategories/{id}', [SubCategoryController::class, 'show']);
	Route::post('subcategories', [SubCategoryController::class, 'store']);
	Route::post('subcategories/{id}', [SubCategoryController::class, 'update']);
	Route::delete('subcategories/{id}', [SubCategoryController::class, 'destroy']);

	Route::get('/brands/{id}/categories', [BrandController::class, 'getCategories']);


	Route::get('/allcategories', [CategoryController::class, 'allcategories']);
	Route::resource('categories', CategoryController::class)->only(['index']);
	Route::post('/categories/{id}', [CategoryController::class, 'update']);

	Route::post('/categories/{id}/move-up', [CategoryController::class ,'moveUp']);
	Route::post('/categories/{id}/move-down', [CategoryController::class ,'moveDown']);
	Route::post('/categories/reorder', [CategoryController::class ,'reorder']);
    Route::apiResource('categories', CategoryController::class);


	 // Order routes
	 Route::get('/orders', [OrderController::class, 'index']);
	 Route::post('/orders', [OrderController::class, 'store']);
	 Route::get('/orders/{order}', [OrderController::class, 'show']);
	 Route::put('/orders/{order}', [OrderController::class, 'update']);
	 Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus']);
	 Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);
	 Route::delete('/orders/{order}', [OrderController::class, 'destroy']);

	 // Order history routes
	 Route::get('/orders/{order}/histories', [OrderHistoryController::class, 'index']);
	 Route::post('/orders/{order}/histories', [OrderHistoryController::class, 'store']);
	 Route::get('/orders/{order}/histories/{history}', [OrderHistoryController::class, 'show']);

	 // Order return routes
	 Route::get('/order-returns', [OrderReturnController::class, 'index']);
	 Route::post('/order-returns', [OrderReturnController::class, 'store']);
	 Route::get('/order-returns/{orderReturn}', [OrderReturnController::class, 'show']);
	 Route::put('/order-returns/{orderReturn}', [OrderReturnController::class, 'update']);
	 Route::patch('/order-returns/{orderReturn}/status', [OrderReturnController::class, 'updateStatus']);
	 Route::delete('/order-returns/{orderReturn}', [OrderReturnController::class, 'destroy']);


	 Route::get('/redirect-links', [RedirectLinkController::class, 'index']);
	 Route::post('/redirect-links', [RedirectLinkController::class, 'store']);
	 Route::post('/redirect-links/import', [RedirectLinkController::class, 'import']);


	 Route::post('/product/upload-images', [ProductImageUploadController::class, 'uploadProductImages']);
	 Route::post('/product/upload-documents', [DocumentUploadController::class, 'uploadProductDocuments']);


	 Route::post('/supplier-score', [SupplierScoreController::class, 'store']);

});
Route::get('/category-pages/{category}', [CategoryPageController::class, 'show']);
Route::get('/category-pages', [CategoryPageController::class, 'index']);