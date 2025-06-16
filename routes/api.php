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
use App\Http\Controllers\VendorDocumentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProductGroupController;
use App\Http\Controllers\AttributeRecommendationController;
use App\Http\Controllers\ProductSupplierController;
use App\Http\Controllers\AppKeywordController;
use App\Http\Controllers\MeasurementController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\BlogCategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CategoryMeasurementUnitPriorityController;

use App\Http\Controllers\FrontEnd\AuthController as F_AuthController;
use App\Http\Controllers\FrontEnd\CustomerController as F_CustomerController;
use App\Http\Controllers\FrontEnd\WishlistController as F_WishlistController;
use App\Http\Controllers\FrontEnd\UserReviewController as F_UserReviewController;
use App\Http\Controllers\FrontEnd\SeoManagementController as F_SeoManagementController;
use App\Http\Controllers\FrontEnd\CategoryMenuController as F_CategoryMenuController;
use App\Http\Controllers\FrontEnd\FaqController as F_FaqController;
use App\Http\Controllers\FrontEnd\ProductYouMayLikeController as F_ProductYouMayLikeController;
use App\Http\Controllers\FrontEnd\ProductAttributeController as F_ProductAttributeController;
use App\Http\Controllers\FrontEnd\BrandPageController as F_BrandPageController;
use App\Http\Controllers\FrontEnd\BlogController as F_BlogController;
use App\Http\Controllers\FrontEnd\DiscountController as F_DiscountController;
use App\Http\Controllers\FrontEnd\BrandController as F_BrandController;
use App\Http\Controllers\FrontEnd\CartController as F_CartController;
use App\Http\Controllers\FrontEnd\AddressController as F_AddressController;
use App\Http\Controllers\FrontEnd\CategoryController as F_CategoryController;
use App\Http\Controllers\FrontEnd\CountryController as F_CountryController;
use App\Http\Controllers\FrontEnd\CouponController as F_CouponController;
use App\Http\Controllers\FrontEnd\OrderController as F_OrderController;
use App\Http\Controllers\FrontEnd\RecentlyViewedProductController as F_RecentlyViewedProductController;
use App\Http\Controllers\FrontEnd\ProductController as F_ProductController;
use App\Http\Controllers\FrontEnd\SearchController as F_SearchController;
use App\Http\Controllers\FrontEnd\SliderController as F_SliderController;
use App\Http\Controllers\FrontEnd\SquarePaymentController as F_SquarePaymentController;
use App\Http\Controllers\FrontEnd\LocationController as F_LocationController;



Route::get('/transactions', [PaymentController::class, 'getAllTransactions']);
Route::post('/payment/ccavenue/initiate', [PaymentController::class, 'initiatePayment']);
Route::post('/payment/ccavenue/callback', [PaymentController::class, 'paymentCallback']);
// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');
Route::post('/login', [AuthController::class, 'store'])->name('login');

/* Protect routes with authentication */
Route::middleware(['auth:back-end-api', 'user.guard'])->group(function () {
	Route::post('/generate-groups', [ProductGroupController::class, 'generateGroups']);
	Route::get('/product-groups', [ProductGroupController::class, 'getGroupedProductDetails']);
	Route::put('/product-groups/{group_id}/items/{item_id}/parent', [ProductGroupController::class, 'updateProductGroupItemParent']);
	// Route::get('/brands/{brand_id}/categories', [ProductGroupController::class, 'getBrandCategories']);
	Route::get('/product-groups/brands-with-categories', [ProductGroupController::class, 'getBrandsWithCategories']);
	Route::get('/product-groups-listing', [ProductGroupController::class, 'index']);

	Route::put('/products/{id}/categories', [ProductCategoryController::class, 'updateCategories']);
	Route::get('/products/{id}/categories', [ProductCategoryController::class, 'getCategories']);

	Route::get('auth/permissions', [AuthController::class, 'getAllPermissions']);
	Route::get('auth/has-permission', [AuthController::class, 'hasPermission']);

	Route::post('/generate-recommendations', [AttributeRecommendationController::class, 'generate']);
	Route::apiResource('recommendations', AttributeRecommendationController::class);


	Route::apiResource('blog-categories', BlogCategoryController::class);
	Route::post('/blogs/{id}', [BlogController::class, 'update']);
	Route::apiResource('blogs', BlogController::class);



	Route::post('/calculate-grade', [GradingController::class, 'calculate']);
	Route::get('/grading/view/{product_id}', [GradingController::class, 'viewByProduct']);
	Route::put('/update-grade', [GradingController::class, 'update']);

	Route::post('/seo-schema', [SeoSchemaController::class, 'store']); // Create or Update SEO Schema
	Route::get('/seo-schema/{type}/{id}', [SeoSchemaController::class, 'show']); // Get SEO Schema

	Route::get('/customers/list-names', [CustomerController::class, 'listNames']);
	Route::apiResource('customers', CustomerController::class);

	Route::post('/product-suppliers/export', [ProductSupplierController::class, 'export']);
	Route::get('product-suppliers/{product_id}/{vendor_id}', [ProductSupplierController::class, 'getproductvendor']);
	Route::put('product-suppliers/{product_id}/{vendor_id}', [ProductSupplierController::class, 'update']);

	Route::delete('product-suppliers/{product_id}/{vendor_id}', [ProductSupplierController::class, 'destroy']);
	// Bulk operations
	Route::post('/product-suppliers/bulk-delete', [ProductSupplierController::class, 'bulkDelete']);
	Route::post('/product-suppliers/batch/export', [ProductSupplierController::class, 'batchExport']);

	// Import/Export operations
	Route::post('/product-suppliers/import', [ProductSupplierController::class, 'import']);
	Route::get('/product-suppliers/import/status/{batch_id}', [ProductSupplierController::class, 'importStatus']);
	Route::get('/product-suppliers/template', [ProductSupplierController::class, 'downloadTemplate']);
	Route::apiResource('product-suppliers', ProductSupplierController::class);

	Route::apiResource('users', UserController::class);

	Route::post('/attributes/import', [AttributeController::class, 'import']);
	Route::post('/attributes/export', [AttributeController::class, 'export']);
	Route::resource('attributes', AttributeController::class);
	Route::delete('attribute-groups/{id}/remove-attribute/{attribute_id}', [AttributeGroupController::class, 'removeAttribute']);
	Route::resource('attribute-groups', AttributeGroupController::class);
	Route::get('category/getAttributesByCategory/{category_id}', [CategoryAttributeController::class, 'getAttributesByCategory']);

	Route::get('/measurement-types', [MeasurementController::class, 'getMeasurementTypes']);
	Route::get('/measurement-units', [MeasurementController::class, 'getMeasurementUnitsByType']);
	Route::get('/measurement-type-categories', [MeasurementController::class, 'getCategoriesByMeasurementType']);

	Route::resource('measurement-unit-priorities', CategoryMeasurementUnitPriorityController::class);

	Route::delete('category-attributes/{id}/remove-attribute-group/{attribute_group_id}', [CategoryAttributeController::class, 'removeAttributeGroup']);
	Route::resource('category-attributes', CategoryAttributeController::class);

	Route::post('/brand-temp-2/{id}', [CategoryAttributeController::class, 'update']);

	Route::apiResource('brand-temp-1', BrandTemp1Controller::class);
	Route::apiResource('brand-temp-2', BrandTemp2Controller::class);
	Route::apiResource('brand-temp-3', BrandTemp3Controller::class);

	Route::post('/keywords/import', [AppKeywordController::class, 'import']);
	Route::post('/keywords/export', [AppKeywordController::class, 'export']);


	Route::put('product-suppliers/{product_id}/{vendor_id}', [ProductSupplierController::class, 'update']);
	Route::delete('product-suppliers/{product_id}/{vendor_id}', [ProductSupplierController::class, 'destroy']);
	Route::apiResource('product-suppliers', ProductSupplierController::class);


	Route::get('/vendors/{vendor_id}/documents/download', [VendorDocumentController::class, 'downloadMediaZip']);
	Route::get('/vendors/{vendor_id}/documents', [VendorDocumentController::class, 'show']);
	Route::post('/vendors/{vendor_id}/documents', [VendorDocumentController::class, 'store']);
	Route::post('/vendors/import', [VendorController::class, 'import']);
	Route::post('/vendors/export', [VendorController::class, 'export']);
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
	Route::get('/products/filtered-category-bd3/{category_id}', [ProductController::class, 'getFilteredProductsByCategorybd3']);
	Route::get('/products/filtered-category-bd1/{category_id}', [ProductController::class, 'getFilteredProductsByCategorybd1']);

	Route::get('getbrandsList', [BrandController::class, 'getBrandsList']);
	Route::get('brands/{brandid}/sku', [BrandController::class, 'getBrandSku']);
	Route::apiResource('brands', BrandController::class);

	Route::get('getStoresList', [StoreController::class, 'getStoresList']);
	Route::get('/stores/list', [StoreController::class, 'storeList']);
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
	Route::get('permissions', [RoleController::class, 'getAllPermissions']);
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
	Route::post('/reorder', [CategoryController::class ,'reorder']);
	Route::apiResource('categories', CategoryController::class);


	Route::prefix('orders')->group(function () {
		Route::get('/', [OrderController::class, 'index']);
		Route::post('/', [OrderController::class, 'store']);
		Route::get('/statistics', [OrderController::class, 'statistics']);
		Route::get('/{id}', [OrderController::class, 'show']);
		Route::put('/{id}/status', [OrderController::class, 'updateStatus']);
		Route::put('/{id}/payment', [OrderController::class, 'updatePayment']);
		Route::put('/{orderId}/products/{productId}/status', [OrderController::class, 'updateProductStatus']);
		Route::post('/{id}/shipments', [OrderController::class, 'createShipment']);
	});


	Route::get('/redirect-links', [RedirectLinkController::class, 'index']);
	Route::post('/redirect-links', [RedirectLinkController::class, 'store']);
	Route::post('/redirect-links/import', [RedirectLinkController::class, 'import']);


	Route::post('/product/upload-images', [ProductImageUploadController::class, 'uploadProductImages']);
	Route::post('/product/upload-documents', [DocumentUploadController::class, 'uploadProductDocuments']);


	Route::post('/supplier-score', [SupplierScoreController::class, 'store']);


	Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

	Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
	Route::post('/logout', [AuthController::class, 'logout']);
});


Route::post('frontend/login', [F_AuthController::class, 'store'])->name('f_login');
Route::post('frontend/register', [F_CustomerController::class, 'register']);
Route::post('/auth/forgot-password', [AuthController::class, 'sendResetLinkEmail']);
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);


Route::post('frontend/orders', [F_OrderController::class, 'store']);

Route::middleware(['auth:front-end-api', 'customer.guard'])->group(function () {
	Route::post('/frontend/logout', [F_AuthController::class, 'logout']);

	Route::post('/frontend/wishlist/add', [F_WishlistController::class, 'addToWishlist']);
    Route::get('/frontend/wishlist', [F_WishlistController::class, 'getWishlist']);
    Route::delete('/frontend/wishlist/remove', [F_WishlistController::class, 'removeFromWishlist']);

    // Additional wishlist routes
    Route::get('/frontend/wishlist/check/{product_id}', [F_WishlistController::class, 'checkWishlist']);
    Route::get('/frontend/wishlist/count', [F_WishlistController::class, 'getWishlistCount']);


	Route::get('/customer-reviews', [F_UserReviewController::class, 'getCustomerReviews']);
	Route::post('/add-customer-reviews', [F_UserReviewController::class, 'createReview']);
	Route::put('/customer-reviews-update/{id}', [F_UserReviewController::class, 'updateReview']);
	Route::delete('/customer-reviews-delete/{id}', [F_UserReviewController::class, 'deleteReview']);

	Route::get('/frontend/products/products-you-may-like', [F_ProductYouMayLikeController::class, 'getProductsYouMayLike']);
    Route::get('/frontend/products/{product_id}/you-may-like', [F_ProductYouMayLikeController::class, 'getProductsYouMayLike']);

	Route::post('/frontend/recent-products/add', [F_RecentlyViewedProductController::class, 'addToRecent']);
    Route::get('/frontend/recent-products', [F_RecentlyViewedProductController::class, 'getRecentProducts']);



	Route::get('/frontend/discounts', [F_DiscountController::class, 'getDiscountsForProduct']);

	Route::get('/frontend/brandproducts', [F_BrandController::class, 'getAllBrandProducts']);
	Route::get('/frontend/homebrandproducts', [F_BrandController::class, 'getAllHomeBrandProducts']);

	Route::post('/frontend/cart/add', [F_CartController::class, 'addToCart']);
	Route::get('/frontend/cart', [F_CartController::class, 'viewCart']);
	Route::delete('/frontend/cart/clear', [F_CartController::class, 'clearCart']);
    Route::delete('/frontend/cart/product/{productId}', [F_CartController::class, 'clearProductFromCart']);
	Route::put('/frontend/cart/update-quantity', [F_CartController::class, 'updateCartQuantity']);
	Route::post('/frontend/cart/decrease-quantity', [F_CartController::class, 'decreaseQuantity']);
	Route::post('/frontend/cart/add-multiple', [F_CartController::class, 'addMultipleToCart']);
	Route::get('/frontend/cart-summary', [F_CartController::class, 'cartSummary']);
	Route::get('/frontend/cart/total-products', [F_CartController::class, 'totalProductsInCart']);


	Route::get('/frontend/addresses', [F_AddressController::class, 'index']);
    Route::post('/frontend/addresses', [F_AddressController::class, 'store']);
    Route::put('/frontend/addresses/{id}', [F_AddressController::class, 'update']);
    Route::delete('/frontend/addresses/{id}', [F_AddressController::class, 'destroy']);
    Route::post('/frontend/addresses/default', [F_AddressController::class, 'updateDefaultAddress']);

	Route::get('/frontend/categoryproducts', [F_CategoryController::class, 'getAllFeaturedProductsByCategory']);

	Route::get('/frontend/coupons/customer', [F_CustomerController::class, 'getCustomerCoupons']);
	Route::get('/frontend/coupons/search', [F_CustomerController::class, 'searchCustomerCoupons']);

	Route::get('/frontend/products', [F_ProductController::class, 'getAllProducts']);
	Route::get('/frontend/products/{id}/related', [F_ProductController::class, 'relatedProducts']);
	Route::get('/frontend/brands/{id}/products', [F_ProductController::class, 'productsByBrand']);
	Route::get('/frontend/brands/{id}/sale-products', [F_ProductController::class, 'saleProductsByBrand']);
	Route::get('/frontend/products/random/{category_id}', [F_ProductController::class, 'getRandomProducts']);
	Route::get('/auth/category-random-products/{categoryId}', [F_ProductController::class, 'getCategoryWiseRandomProductsForUser']);


});

Route::get('/category-random-products/{categoryId}', [F_ProductController::class, 'getCategoryWiseRandomProducts']);



Route::get('/frontend/home-categories', [F_CategoryController::class, 'fetchCategories']);
Route::get('/frontend/all-categories', [F_CategoryController::class, 'fetchAllCategories']);
Route::get('/frontend/categoryguestproducts', [F_CategoryController::class, 'getAllGuestFeaturedProductsByCategory']);
Route::get('/frontend/categories', [F_CategoryController::class, 'index']);
Route::get('/frontend/categories/{slug}', [F_CategoryController::class, 'categoryslug']);
Route::get('/frontend/categories/{id}', [F_CategoryController::class, 'show']);
Route::get('/frontend/categories/{categoryId}/products', [F_CategoryController::class, 'getProductsByCategory']);
Route::get('/frontend/products/specification-filters', [F_CategoryController::class, 'getSpecificationFilters']);


Route::get('/frontend/category-with-slug/{slug}', [F_CategoryMenuController::class, 'showCategoryBySlug']);
Route::get('/frontend/categories-with-children', [F_CategoryMenuController::class, 'getCategoriesWithChildren']);

Route::get('/frontend/cart/total-products-guest', [F_CartController::class, 'totalProductsInCartGuest']);
Route::get('/frontend/cart/guest', [F_CartController::class, 'viewCartGuest']);
Route::delete('/frontend/cart/guest/clear', [F_CartController::class, 'clearCartGuest']);

Route::get('/frontend/faqs/product/{product_id}', [F_FaqController::class, 'getFaqsByProduct']);

Route::get('/frontend/product-reviews', [F_UserReviewController::class, 'getProductReviews']);

Route::get('/frontend/products/{product_id}/you-may-like-guest', [F_ProductYouMayLikeController::class, 'getProductsYouMayLikeGuest']);

Route::get('/frontend/product/{productId}/attributes', [F_ProductAttributeController::class, 'getAttributesByProduct']);
Route::get('/product/{id}/nutrition-facts', [F_ProductAttributeController::class, 'getNutritionFactsByProduct']);
Route::get('/frontend/product/{productId}/nutrition-facts1', [F_ProductAttributeController::class, 'getNutritionFactsByProduct1']);
Route::get('/frontend/product-group/{productId}/attributes', [F_ProductAttributeController::class, 'getAttributesByProductWithGroup']);

Route::get('/frontend/seo-management', [F_SeoManagementController::class, 'index']);
Route::get('/frontend/seo-management/relational/{relational_id}', [F_SeoManagementController::class, 'getByRelationalId']);
Route::get('/frontend/seo/paragraphs/{relational_id}', [F_SeoManagementController::class, 'getParagraphData']);


Route::get('/frontend/brand-page/{id}', [F_BrandPageController::class, 'show']);

Route::get('/frontend/brandguestproducts', [F_BrandController::class, 'getAllBrandGuestProducts']);
Route::get('/frontend/products/brand/{brandId}/category/{categoryId?}', [F_BrandController::class, 'getProductsByBrandAndCategory']);
Route::get('/frontend/brand/{id}/categories', [F_BrandController::class, 'getCategories']);
Route::get('/frontend/brands-by-category/{id}', [F_BrandController::class, 'brandsByCategory']);
Route::get('/frontend/brands/alphabetical', [F_BrandController::class, 'getAllBrandsAlphabetically']);

Route::get('/frontend/countries', [F_CountryController::class, 'index']);
Route::get('/frontend/countries/{id}', [F_CountryController::class, 'show']);

Route::get('/frontend/coupons/apply', [F_CouponController::class, 'applyCoupon']);

Route::prefix('/frontend/blogs')->group(function () {
	Route::get('/', [F_BlogController::class, 'index']);
	Route::get('/{slug}', [F_BlogController::class, 'show']);
	Route::post('/{id}/like', [F_BlogController::class, 'like']);
	Route::post('/{id}/share', [F_BlogController::class, 'share']);
	Route::post('/{id}/view', [F_BlogController::class, 'view']);
});
Route::get('/frontend/blog-categories', [F_BlogController::class, 'categories']);
Route::get('/frontend/category/{slug}/blogs', [F_BlogController::class, 'blogsByCategorySlug']);
Route::get('/frontend/categories-with-blogs', [F_BlogController::class, 'categoryWiseBlogs']);

Route::get('/frontend/sliders', [F_SliderController::class, 'index']);
Route::get('/frontend/sliders/{id}', [F_SliderController::class, 'show']);

Route::get('/frontend/products-guest', [F_ProductController::class, 'getAllPublicProducts']);
Route::get('/frontend/brands/{id}/summary', [F_ProductController::class, 'brandSummaryStats']);

Route::get('/frontend/search', [F_SearchController::class, 'search']);
Route::get('/frontend/search-categories', [F_SearchController::class, 'searchCategories']);

Route::post('/frontend/payment-square', [F_SquarePaymentController::class, 'createPayment']);

Route::get('/frontend/location', [F_LocationController::class, 'getLocation']);
Route::get('/frontend/get-coordinates', [F_LocationController::class, 'getCoordinates']);
Route::post('/frontend/get-location', [F_LocationController::class, 'getAddress']);

Route::get('/category-pages/{category}', [CategoryPageController::class, 'show']);
Route::get('/category-pages', [CategoryPageController::class, 'index']);