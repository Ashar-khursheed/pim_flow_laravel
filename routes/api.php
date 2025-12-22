<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\TemporaryCategoryController;
use App\Http\Controllers\WebsiteController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductReportController;
use App\Http\Controllers\AIAlternateProductController;
use App\Http\Controllers\LLmsSeoMonitoringController;
use App\Http\Controllers\CategoryPageController;
use App\Http\Controllers\ProductXMLFeedWatchController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\AttributeController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\AttributeGroupController;
use App\Http\Controllers\CategoryAttributeController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\FaqCategoryController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\CustomerCartExportController;
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
use App\Http\Controllers\CustomerAddressController;
use App\Http\Controllers\ProductQuestionController;
use App\Http\Controllers\CategoryMeasurementUnitPriorityController;
use App\Http\Controllers\ReturnOrderProductController;
use App\Http\Controllers\ProductTitleFormulaController;
use App\Http\Controllers\UnisourceShipmentController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\MenuBannerController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\NoFraudController;
use App\Http\Controllers\LogDownloadController;
use App\Http\Controllers\AbandonedCartController;
use App\Http\Controllers\Utmcontroller;
use App\Http\Controllers\CustomerEventController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\CustomerCartController;
use App\Http\Controllers\PrePurchaseClaimController;
use App\Http\Controllers\PostPurchaseClaimController;
use App\Http\Controllers\ProductAccessoriesController;
use App\Http\Controllers\PaymentHistoryController;
use App\Http\Controllers\ProductVariantController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\TranslationController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\MadeToOrderController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\TqlQuoteController;
use App\Http\Controllers\TenderController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\GetInTouchController;
use App\Http\Controllers\TrainingDataController;
use App\Http\Controllers\ProductAttributeController;

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
use App\Http\Controllers\FrontEnd\CustomerAddressController as F_CustomerAddressController;
use App\Http\Controllers\FrontEnd\CategoryController as F_CategoryController;
use App\Http\Controllers\FrontEnd\FCategoryController;
use App\Http\Controllers\FrontEnd\CountryController as F_CountryController;
use App\Http\Controllers\FrontEnd\CouponController as F_CouponController;
use App\Http\Controllers\FrontEnd\OrderController as F_OrderController;
use App\Http\Controllers\FrontEnd\RecentlyViewedProductController as F_RecentlyViewedProductController;
use App\Http\Controllers\FrontEnd\ProductController as F_ProductController;
use App\Http\Controllers\FrontEnd\SearchController as F_SearchController;
use App\Http\Controllers\FrontEnd\SliderController as F_SliderController;
use App\Http\Controllers\FrontEnd\SquarePaymentController as F_SquarePaymentController;
use App\Http\Controllers\FrontEnd\LocationController as F_LocationController;
use App\Http\Controllers\FrontEnd\MadeToOrderController as F_MadeToOrderController;
use App\Http\Controllers\FrontEnd\ReturnOrderProductController as F_ReturnOrderProductController;
use App\Http\Controllers\FrontEnd\SaveForLaterController as F_SaveForLaterController;
use App\Http\Controllers\FrontEnd\CcavenueController as F_CcavenueController;
use App\Http\Controllers\FrontEnd\ProductQuestionController as F_ProductQuestionController;
use App\Http\Controllers\FrontEnd\PaymentManagementController as F_PaymentManagementController;
use App\Http\Controllers\FrontEnd\StripeController as F_StripeController;
use App\Http\Controllers\FrontEnd\ProductErrorController as F_ProductErrorController;
use App\Http\Controllers\FrontEnd\TamaraController as F_TamaraController;
use App\Http\Controllers\FrontEnd\GeoController as F_GeoController;
use App\Http\Controllers\FrontEnd\LookupController  as F_LookupController;
use App\Http\Controllers\FrontEnd\TaxController  as F_TaxController;
use App\Http\Controllers\FrontEnd\AlternateProductController  as F_AlternateProductController;
use App\Http\Controllers\FrontEnd\FbtProductController  as F_FbtProductController;
use App\Http\Controllers\FrontEnd\DiffProductController  as F_DiffProductController;
use App\Http\Controllers\FrontEnd\QuoteController as F_QuoteController;
use App\Http\Controllers\FrontEnd\ContactDirectoryController as F_ContactDirectoryController;
use App\Http\Controllers\FrontEnd\CustomerDocumentController as F_CustomerDocumentController;
use App\Http\Controllers\FrontEnd\SupportTicketController as F_SupportTicketController;
use App\Http\Controllers\FrontEnd\SupportMetaController as F_SupportMetaController;
use App\Http\Controllers\FrontEnd\CompanyProfileController as F_CompanyProfileController;
use App\Http\Controllers\FrontEnd\InvoiceController  as F_InvoiceController;
use App\Http\Controllers\FrontEnd\GoogleReviewController as F_GoogleReviewController;
use App\Http\Controllers\FrontEnd\MenuBannerController as F_MenuBannerController ;
use App\Http\Controllers\FrontEnd\GlitchErrorController;
use App\Http\Controllers\FrontEnd\CustomerEventController as F_CustomerEventController;
use App\Http\Controllers\FrontEnd\PrePurchaseClaimController as F_PrePurchaseClaimController;
use App\Http\Controllers\FrontEnd\PostPurchaseClaimController as F_PostPurchaseClaimController;
use App\Http\Controllers\FrontEnd\GetInTouchController as F_GetInTouchController;
use App\Http\Controllers\FrontEnd\InquiryController  as F_InquiryController;
use App\Http\Controllers\FrontEnd\SearchLogController as F_SearchLogController ;
use App\Http\Controllers\FrontEnd\CompareProductController;
use App\Http\Controllers\FrontEnd\SitemapController;
use App\Http\Controllers\FrontEnd\TxtllmsController;
use App\Http\Controllers\FrontEnd\FilterController;
use App\Http\Controllers\FrontEnd\ShippingReportController;
use App\Http\Controllers\FrontEnd\CustomerCartController as F_CustomerCartController;
use App\Http\Controllers\FrontEnd\FnProductAccessoriesController;
use App\Http\Controllers\FrontEnd\FndProductVariantController;
use App\Http\Controllers\FrontEnd\StaxPaymentController as F_StaxPaymentController;
use App\Http\Controllers\FrontEnd\PaymobController as F_PaymobController;
use App\Http\Controllers\FrontEnd\FinanceController as F_FinanceController;
use App\Http\Controllers\FrontEnd\TqlRateController;

use App\Http\Middleware\CaptureUtm;
use App\Models\Lead;
use App\Models\Utm;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
// routes/api.php


Route::middleware([CaptureUtm::class])->group(function () {

	Route::post('/store-lead', function (Request $request) {
		$sessionId = $request->header('X-Session-ID') ?? Str::uuid()->toString();

		$lead = Lead::create([
			'session_id' => $sessionId,
			'name'       => $request->input('name'),
			'email'      => $request->input('email'),
		]);

		return response()->json(['message' => 'Lead stored', 'lead' => $lead]);
	});

	Route::get('/utm-stats', function () {
		$visitors = DB::table('utms')
		->select('utm_source', DB::raw('count(distinct session_id) as total_visitors'))
		->groupBy('utm_source')
		->get();

		$conversionsRaw = DB::table('leads')->select('session_id')->get();

		$conversions = $conversionsRaw->groupBy(function ($item) {
			return Utm::where('session_id', $item->session_id)->value('utm_source') ?? 'unknown';
		})->map->count();

		return response()->json([
			'visitors'    => $visitors,
			'conversions' => $conversions,
		]);
	});

});

Route::post('/ccavenue/webhook', [F_CCavenueController::class, 'successhandleWebhook']);
Route::post('/payment/ccavenue/notify', [F_CCavenueController::class, 'successhandleWebhook']);
Route::get('/ccavenue/thank', [F_CCavenueController::class, 'successhandleWebhook']);
Route::post('/ccavenue/dataEncodeCCavenue', action: [F_CCavenueController::class, 'dataEncodeCCavenue']);
Route::apiResource('frontend/get-in-touch', F_GetInTouchController::class);

// Route::post('frontend/customer-events', [F_CustomerEventController::class, 'store']);
Route::get('/proxy-image', function (Illuminate\Http\Request $request) {
	$url = $request->query('url');

	if (!$url) {
		abort(400, 'URL is required');
	}

	try {
		$response = Http::timeout(10)->get($url);

		if (!$response->successful()) {
			abort(404, 'Image not found');
		}

		return response($response->body(), 200)
		->header('Content-Type', $response->header('Content-Type') ?? 'image/webp')
		->header('Cache-Control', 'public, max-age=86400')
		->header('Access-Control-Allow-Origin', '*')
		->header('Access-Control-Allow-Headers', 'Content-Type');
	} catch (\Exception $e) {
		abort(500, 'Proxy failed: ' . $e->getMessage());
	}
})->name('proxy-image');





// Route::get('/transactions', [PaymentController::class, 'getAllTransactions']);
// Route::post(' /frontend/ccavenue/initiate', [PaymentController::class, 'initiatePayment']);
// Route::post('/payment/ccavenue/callback', [PaymentController::class, 'paymentCallback']);
// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');
Route::post('/login', [AuthController::class, 'store'])->name('login');


Route::get('/countries', [LocationController::class, 'getCountryList']);
Route::get('/states/{countryId}', [LocationController::class, 'getStateList']);
Route::get('/cities/{countryId}', [LocationController::class, 'getCityList']);
Route::get('/zipcodes/{cityId}', [LocationController::class, 'getZipcodeList']);
Route::apiResource('newsletters', NewsletterController::class);

Route::get('/product-info/{slug}', [F_ProductController::class, 'getProductInfoBySlug']);

Route::get('logs/download', [LogDownloadController::class, 'downloadLog']);

// Route::apiResource('frontend/pre-purchase-claims', F_PrePurchaseClaimController::class);
Route::post('frontend/pre-purchase-claims', [F_PrePurchaseClaimController::class, 'store']);

Route::prefix('analytics')->group(function () {
	// Basic Analytics
	Route::get('/overview', [AnalyticsController::class, 'overview']);
	Route::get('/sessions-by-date', [AnalyticsController::class, 'sessionsByDate']);
	Route::get('/realtime', [AnalyticsController::class, 'realTimeAnalytics']);

	// Detailed Analytics
	Route::get('/device', [AnalyticsController::class, 'deviceAnalytics']);
	Route::get('/geographic', [AnalyticsController::class, 'geographicAnalytics']);
	Route::get('/traffic-sources', [AnalyticsController::class, 'trafficSources']);
	Route::get('/pages', [AnalyticsController::class, 'pageAnalytics']);
	Route::get('/events', [AnalyticsController::class, 'eventAnalytics']);

	// E-commerce & Conversions
	Route::get('/conversions', [AnalyticsController::class, 'conversionAnalytics']);
	Route::get('/abandoned-cart', [AnalyticsController::class, 'abandonedCartAnalytics']);
	Route::get('/ecommerce-funnel', [AnalyticsController::class, 'ecommerceFunnel']);
	Route::get('/goal-completions', [AnalyticsController::class, 'goalCompletions']);

	// Audience Analytics
	Route::get('/demographics', [AnalyticsController::class, 'audienceDemographics']);
	Route::get('/cohort-analysis', [AnalyticsController::class, 'cohortAnalysis']);
	Route::get('/user-journey', [AnalyticsController::class, 'userJourney']);

	// Advanced Features
	Route::get('/time-based', [AnalyticsController::class, 'timeBasedAnalytics']);
	Route::get('/complete-dashboard', [AnalyticsController::class, 'completeDashboard']);
	Route::post('/custom-report', [AnalyticsController::class, 'customReport']);
});
Route::get('/analytics/landing-pages', [AnalyticsController::class, 'landingPageAnalytics']);
Route::get('/analytics/page-performance', [AnalyticsController::class, 'pagePerformance']);

// Route::prefix('analytics')->group(function () {
//     Route::get('/sessions', [AnalyticsController::class, 'sessions']);
//     Route::get('/users', [AnalyticsController::class, 'users']);
//     Route::get('/engagement', [AnalyticsController::class, 'engagement']);
//     Route::get('/conversions', [AnalyticsController::class, 'conversions']);
//     Route::get('/device-sessions', [AnalyticsController::class, 'deviceSessions']);
//     Route::get('/geo', [AnalyticsController::class, 'geo']);
// });

/* Protect routes with authentication */
Route::middleware(['auth:back-end-api', 'user.guard'])->group(function () {
	Route::apiResource('/inquiries',  InquiryController::class);
	Route::apiResource('training-data',TrainingDataController::class);


	Route::get('/auth/send-customers-reset-link', [AuthController::class, 'sendAllCustomersResetLinkEmail']);
	Route::apiResource('pre-purchase-claims', PrePurchaseClaimController::class);
	Route::apiResource('post-purchase-claims', PostPurchaseClaimController::class);

	Route::apiResource('/coupons', F_CouponController::class)	;
	Route::post('/coupons/{coupon}/approve', [F_CouponController::class, 'approve']);
	Route::post('/coupons/{coupon}/reject', [F_CouponController::class, 'reject']);
	Route::post('/coupons/validate', [F_CouponController::class, 'validate']);

	Route::get('/coupons/{coupon}/usage-report', [F_CouponController::class, 'usageReport']);

	Route::apiResource('customer-events', CustomerEventController::class);

	Route::get('/utms', [Utmcontroller::class, 'index']);
	Route::get('/analytics/stats', [Utmcontroller::class, 'stats']);
	Route::get('/analytics/utm-sources', [Utmcontroller::class, 'utmSources']);

	Route::get('/abandoned-carts', [AbandonedCartController::class, 'index']);
	Route::get('/abandoned-carts/{id}', [AbandonedCartController::class, 'show']);
	Route::get('/customers-by-date-range', [AbandonedCartController::class, 'getCustomersByDateRange']);

	Route::prefix('menu-banners')->group(function () {
	// Create banner
		Route::post('/', [MenuBannerController::class, 'store']);
		Route::get('/', [MenuBannerController::class, 'index']);
		Route::get('/{id}', [MenuBannerController::class, 'show']);
		Route::post('/{id}', [MenuBannerController::class, 'update']);
		Route::delete('/{id}', [MenuBannerController::class, 'destroy']);
	});

	Route::prefix('category-banners')->group(function () {
		Route::get('/', [CategoryBannerController::class, 'index']);
		Route::post('/', [CategoryBannerController::class, 'store']);
		Route::get('/show/{category_id}', [CategoryBannerController::class, 'show']);
		Route::put('/{id}', [CategoryBannerController::class, 'update']);
		Route::delete('/{id}', [CategoryBannerController::class, 'destroy']);
	});

	Route::apiResource('payments', PaymentController::class);
	Route::get('report/orders', [ReportController::class, 'index']);
	Route::get('report/stats/reserved', [ReportController::class, 'reservedOrders']);
	Route::get('report/utms', [ReportController::class, 'indexUtms']);
	Route::get('report/customer-utms', [ReportController::class, 'indexCustomerUtms']);

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

	Route::get('/product-questions/{product_id}', [ProductQuestionController::class, 'index']);

	Route::post('/calculate-grade', [GradingController::class, 'calculate']);
	Route::get('/grading/view/{product_id}', [GradingController::class, 'viewByProduct']);
	Route::put('/update-grade', [GradingController::class, 'update']);

	Route::post('/seo-schema', [SeoSchemaController::class, 'store']); // Create or Update SEO Schema
	Route::get('/seo-schema/{type}/{id}', [SeoSchemaController::class, 'show']); // Get SEO Schema

	Route::get('/customers/{customer_id}/addresses', [CustomerAddressController::class, 'indexByCustomer']);
	Route::apiResource('customers', CustomerController::class);
	Route::get('/customers/filter-by-date', [CustomerController::class, 'filterByDate']);
	Route::apiResource('customer-address', CustomerAddressController::class);

	Route::post('/product-suppliers/export', [ProductSupplierController::class, 'export']);
	Route::post('/product-suppliers/import', [ProductSupplierController::class, 'import']);
	Route::get('/product-suppliers/template', [ProductSupplierController::class, 'downloadTemplate']);
	Route::put('/product-supplier/{id}/update-price', [ProductSupplierController::class, 'updatePrice']);
	Route::put('/product-supplier/update-price-by-sku/{sku}', [ProductSupplierController::class, 'updatePriceBySku']);


	Route::apiResource('product-suppliers', ProductSupplierController::class);

	// Bulk operations
	// Route::post('/product-suppliers/bulk-delete', [ProductSupplierController::class, 'bulkDelete']);
	// Route::post('/product-suppliers/batch/export', [ProductSupplierController::class, 'batchExport']);
	// Route::get('/product-suppliers/import/status/{batch_id}', [ProductSupplierController::class, 'importStatus']);

	Route::apiResource('users', UserController::class);

	Route::post('/attributes/import', [ProductAttributeController::class, 'import']);
	Route::post('/attributes/export', [ProductAttributeController::class, 'export']);
	Route::post('/product-attributes/save-translation', [ProductAttributeController::class, 'saveTranslation']);
	Route::get('/product-attributes/{productId}', [ProductAttributeController::class, 'getProductCategoryAttributes']);
	Route::get('products/{id}/product-category-attribute-groups', [ProductAttributeController::class, 'productCategoryAttributeGroups']);

	Route::post('/attributes/generate-translation', [AttributeController::class, 'generateTranslation']);
	Route::resource('attributes', AttributeController::class);
	Route::delete('attribute-groups/{id}/remove-attribute/{attribute_id}', [AttributeGroupController::class, 'removeAttribute']);
	Route::resource('attribute-groups', AttributeGroupController::class);
	Route::get('category/getAttributesByCategory/{category_id}', [CategoryAttributeController::class, 'getAttributesByCategory']);

	Route::get('/measurement-types', [MeasurementController::class, 'getMeasurementTypes']);

	Route::post('/measurement-units/save-translation', [MeasurementController::class, 'saveTranslation']);
	Route::get('/measurement-units', [MeasurementController::class, 'getMeasurementUnitsByType']);
	Route::get('/measurement-type-categories', [MeasurementController::class, 'getCategoriesByMeasurementType']);

	Route::post('/measurement-unit-priorities/import', [CategoryMeasurementUnitPriorityController::class, 'import']);
	Route::resource('measurement-unit-priorities', CategoryMeasurementUnitPriorityController::class);

	Route::delete('category-attributes/{id}/remove-attribute-group/{attribute_group_id}', [CategoryAttributeController::class, 'removeAttributeGroup']);
	Route::resource('category-attributes', CategoryAttributeController::class);

	Route::post('/brand-temp-2/{id}', [CategoryAttributeController::class, 'update']);

	Route::apiResource('brand-temp-1', BrandTemp1Controller::class);
	Route::apiResource('brand-temp-2', BrandTemp2Controller::class);
	Route::apiResource('brand-temp-3', BrandTemp3Controller::class);

	Route::post('/keywords/import', [AppKeywordController::class, 'import']);
	Route::post('/keywords/export', [AppKeywordController::class, 'export']);

    Route::post('/translations/generate-translate', [TranslationController::class, 'generateTranslate']);
    Route::post('/translations/export', [TranslationController::class, 'export']);
    Route::post('/translations/import', [TranslationController::class, 'import']);


	Route::get('/vendors/{vendor_id}/documents/download', [VendorDocumentController::class, 'downloadMediaZip']);
	Route::get('/vendors/{vendor_id}/documents', [VendorDocumentController::class, 'show']);
	Route::post('/vendors/{vendor_id}/documents', [VendorDocumentController::class, 'store']);
	Route::post('/vendors/import', [VendorController::class, 'import']);
	Route::post('/vendors/export', [VendorController::class, 'export']);
	Route::apiResource('vendors', VendorController::class);
	Route::apiResource('pre-onboarding-vendors', PreOnboardingVendorController::class);

	Route::resource('transaction-logs', TransactionLogController::class);

	Route::resource('websites', WebsiteController::class)->only(['index']);
	Route::resource('/delivery/payment-history', PaymentHistoryController::class);

	Route::get('/delivery/get-price-ordernumber', [PaymentHistoryController::class,'getPriceOrderNumber']);
	Route::resource('product-accessories', ProductAccessoriesController::class);
	Route::post('/product-accessories/status/{id}', [ProductAccessoriesController::class, 'updateStatus']);
	Route::post('/product-accessories/isRequired/{id}', [ProductAccessoriesController::class, 'updateIsRequired']);
	Route::delete('/product-accessories/item/{item_id}', [ProductAccessoriesController::class, 'deleteItem']);
	Route::get('/get-product-list', [ProductAccessoriesController::class, 'getProductList']);

	Route::apiResource('product-variants', ProductVariantController::class);
	Route::post('product-variants/getProductAttibute', [ProductVariantController::class,'getProductAttibute']);
	Route::post('product-variants/show', [ProductVariantController::class, 'show']);
	Route::apiResource('made-to-orders', MadeToOrderController::class);

	Route::apiResource('finances', FinanceController::class);
	// Route::get('finances/{id}', [FinanceController::class, 'show']);
	 Route::post('finances/{id}', [FinanceController::class, 'update']);

	Route::post('finances/{id}/account-status', [FinanceController::class, 'updateStatus']);

	Route::get('finances/{id}/due', [FinanceController::class, 'getDueDetails']);
	Route::post('finances/pay/{id}', [FinanceController::class, 'payAmount']);
	Route::get('finances/{id}/payment-history', [FinanceController::class, 'getPaymentHistory']);
	Route::get('finances/get-full-due/{id}/{customer_id}', [FinanceController::class, 'getFullNetTermDue']);
	Route::post('finances/pay-full-payment/{id}', [FinanceController::class, 'payfullNetTerm']);

	Route::post('/ltl/quotes', [TqlQuoteController::class, 'create']);
    Route::get('/ltl/quotes/{id}', [TqlQuoteController::class, 'get']);
    Route::post('/ltl/quotes/tender', [TenderController::class, 'tender']);
    Route::get('/tracking/{poNumber}', [TrackingController::class, 'track']);
    Route::post('/ltl/loads/tender', [TenderController::class, 'tenderByScac']);

	Route::get('/products/{id}/media/{type}/download', [BrandController::class, 'downloadMediaZip']);
	Route::get('products/{id}/media', [BrandController::class, 'getProductMedia']);
	Route::post('/products/export', [ProductExportController::class, 'export']);
	Route::post('products/import', [ProductController::class, 'import']);
	Route::get('products/product-input', [ProductController::class, 'getProductInputs']);
	Route::get('products/category/{category_id}', [ProductController::class, 'getProductsByCategory']);
	Route::get('products/product-category-attribute-groups', [ProductController::class, 'product']);
	Route::post('/product/full-url', [ProductController::class, 'getStoreUrl']);
	Route::resource('products', ProductController::class);
	Route::get('/products/filtered-category/{category_id}', [ProductController::class, 'getFilteredProductsByCategory']);
	Route::get('/products/filtered-category-bd3/{category_id}', [ProductController::class, 'getFilteredProductsByCategorybd3']);
	Route::get('/products/filtered-category-bd1/{category_id}', [ProductController::class, 'getFilteredProductsByCategorybd1']);

	Route::post('products/duplicate', [ProductController::class, 'productDuplicate']);

	Route::post('products/delete-product-document', [ProductController::class, 'deleteProductDocument']);
	Route::post('/product-report-export', [ProductReportController::class, 'index']);
	Route::post('/product-benefit-report', [ProductReportController::class, 'exportBenefitReport']);
	Route::post('/vendor-brand-product-export', [ProductReportController::class, 'vendorBrandProductExport']);

	Route::get('/get-llms-seo-monitoring', [LLmsSeoMonitoringController::class, 'index']);
	Route::post('/save-llms-seo-monitoring', [LLmsSeoMonitoringController::class, 'store']);
	Route::get('/live-show-llms-seo-monitoring', [LLmsSeoMonitoringController::class, 'show']);

	Route::get('/ai-products-alternates', [AIAlternateProductController::class, 'index']);
	Route::get('/get-ai-alternates', [AIAlternateProductController::class, 'getAiAlternateProducts']);
	Route::post('/get-product-alternative-comparison', [AIAlternateProductController::class, 'getProdutAlternativeComparison']);
	Route::post('python/create-alternate-recommendation', [AIAlternateProductController::class, 'createAlternateProductsByPthon']);
	Route::post('/python/create-one-product-alternate', [AIAlternateProductController::class, 'createOneAlternateProductsByPthon']);
	Route::post('ai-alternate-status', [AIAlternateProductController::class, 'alternateStatus']);
	Route::post('ai-alternate-priority', [AIAlternateProductController::class, 'alternatePriority']);

	Route::post('/brands/generate-translation', [BrandController::class, 'generateTranslation']);
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
	Route::post('reviews/import', [ReviewController::class,'import']);
	Route::post('reviews/export', [ReviewController::class,'export']);
	Route::post('reviews/exportReview', [ReviewController::class,'exportReview']);
	Route::post('reviews/fekerEmailUpdate', [ReviewController::class,'fekerEmailUpdate']);
	Route::apiResource('sliders', SliderController::class);

	Route::post('customerCartExport/export', [CustomerCartExportController::class,'export']);

	// Discount API Routes
	Route::apiResource('discounts', DiscountController::class);

	// Flash Sale API Routes
	Route::apiResource('flash-sales', FlashSaleController::class);


	Route::get('/seo-management/check-url', [SeoManagementController::class, 'checkURL']);
	Route::post('/seo-management/import', [SeoManagementController::class, 'import']);
	Route::post('/seo-management/export', [SeoManagementController::class, 'export']);
	Route::post('/seo-management/{relational_type}/{id}', [SeoManagementController::class, 'update']);
	Route::post('/seoManagement/schema-update/{seo_id}', [SeoManagementController::class, 'schemaUpdate']);

	Route::post('/seo-management/save-translation', [SeoManagementController::class, 'saveTranslation']);
	Route::resource('seo-management', SeoManagementController::class);
	Route::post('seo-management/{id}', [SeoManagementController::class,'update']);


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

	Route::post('/categories/generate-translation', [CategoryController::class, 'generateTranslation']);
	Route::resource('categories', CategoryController::class)->only(['index']);
	Route::post('/categories/{id}', [CategoryController::class, 'update']);

	Route::post('/categories/{id}/move-up', [CategoryController::class ,'moveUp']);
	Route::post('/categories/{id}/move-down', [CategoryController::class ,'moveDown']);
	Route::post('/reorder', [CategoryController::class ,'reorder']);
	Route::apiResource('categories', CategoryController::class);
	Route::get('/allLastChild', [CategoryController::class ,'allLastChildCategories']);
	Route::apiResource('temporaryCategories', TemporaryCategoryController::class);

    Route::get('/allTemporaryCategories', [TemporaryCategoryController::class, 'allTemporaryCategories']);
	Route::post('/temporaryCategories/{id}', [TemporaryCategoryController::class, 'update']);


	Route::put('return-products/{id}/inspect', [ReturnOrderProductController::class, 'inspectReturn']);
	Route::put('return-products/{id}/refund', [ReturnOrderProductController::class, 'refundReturn']);

	Route::post('/webhook/square', [OrderController::class, 'handleSquareWebhook']);
	Route::post('/webhook/thanks', [OrderController::class, 'thanks']);
	Route::get('/payment/{orderId}', [OrderController::class, 'markOrderPaid']);

	Route::post('orders/{id}/resend-mail', [OrderController::class, 'resendOrderPlaceMail']);

	Route::put('orders/{id}/status', [OrderController::class, 'updateStatus']);
	Route::put('orders/{orderId}/products/{productId}/status', [OrderController::class, 'updateProductStatus']);
	Route::post('orders/{id}/shipments', [OrderController::class, 'createShipment']);
	Route::apiResource('orders', OrderController::class);

	Route::put('support-tickets/{id}/status', [SupportTicketController::class, 'updateStatus']);
	Route::apiResource('support-tickets', SupportTicketController::class);

	Route::apiResource('quotes', QuoteController::class);

	Route::get('/redirect-links/template', [RedirectLinkController::class, 'downloadTemplate']);
	Route::get('/redirect-links', [RedirectLinkController::class, 'index']);
	Route::get('/redirect-links/{id}', [RedirectLinkController::class, 'show']);
	Route::put('/redirect-links/{id}', [RedirectLinkController::class, 'update']);
	Route::post('/redirect-links', [RedirectLinkController::class, 'store']);
	Route::post('/redirect-links/import', [RedirectLinkController::class, 'import']);


	Route::post('/product/upload-images', [ProductImageUploadController::class, 'uploadProductImages']);
	Route::post('/product/upload-documents', [DocumentUploadController::class, 'uploadProductDocuments']);


	Route::post('/supplier-score', [SupplierScoreController::class, 'store']);

	Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
	Route::post('/logout', [AuthController::class, 'logout']);

	Route::get('/categories/{category_id}/finalize-product-titles', [ProductTitleFormulaController::class, 'finalizeProductTitles']);
	Route::get('/categories/{category_id}/sample-product-titles', [ProductTitleFormulaController::class, 'generateSampleProductTitles']);
	Route::apiResource('product-title-formula', ProductTitleFormulaController::class);
	Route::post('product-title-formula/delete-multiple', [ProductTitleFormulaController::class, 'destroyMultiple']);

	Route::post('/unisource/create-shipment', [UnisourceShipmentController::class, 'createShipment']);
	Route::post('/unisource/authenticate', [UnisourceShipmentController::class, 'authenticateWithUnisource']);

	Route::get('/carts/fetch/{id}', [CustomerCartController::class, 'fetchByID']);
	Route::apiResource('carts', CustomerCartController::class);

	Route::post('/nofraud/process/{order_id}', [NoFraudController::class, 'processNoFraud']);

	Route::apiResource('/get-in-touch', GetInTouchController::class);
	Route::get('/get-in-touch/{id}', [GetInTouchController::class, 'show']);



});


Route::apiResource('/frontend/glitch-errors', GlitchErrorController::class);

Route::post('frontend/login', [F_AuthController::class, 'store'])->name('f_login');
Route::post('/apple-login', [F_AuthController::class, 'appleLogin']);



Route::post('frontend/register', [F_CustomerController::class, 'register']);
Route::post('frontend/coupon-register', [F_CustomerController::class, 'couponRegister']);
Route::post('/auth/forgot-password', [AuthController::class, 'sendResetLinkEmail']);
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
Route::post('frontend/auth/google', [F_CustomerController::class, 'googleLogin']);

Route::get('/frontend/support-categories', [F_SupportMetaController::class, 'getCategories']);
Route::get('/frontend/support-priorities', [F_SupportMetaController::class, 'getPriorities']);
Route::post('frontend/compare-table-product', [CompareProductController::class, 'getCompareTableProduct']);

Route::get('frontend/product-accessories', [FnProductAccessoriesController::class, 'index']);
Route::Post('frontend/product-variants', [FndProductVariantController::class, 'index']);
Route::Post('frontend/attribute-product-variants', [FndProductVariantController::class, 'getAttributeByProduct']);
Route::Post('frontend/product-variants-by-attribute', [FndProductVariantController::class, 'getAttributeByProductVariant']);

Route::middleware(['auth:front-end-api', 'customer.guard'])->group(function () {

Route::post('frontend/finances', [F_FinanceController::class, 'store']);
Route::get('/frontend/finances', [F_FinanceController::class, 'index']);
Route::put('/frontend/finances/{id}/updateCreditAmount', [F_FinanceController::class, 'updateCreditAmount']);

Route::get('frontend/finances/apply', [F_FinanceController::class, 'getFinance']);
Route::post('frontend/finances/order', [F_FinanceController::class, 'financeOrder']);
Route::get('frontend/finances/check', [F_FinanceController::class, 'financeCheck']);
Route::get('frontend/finances/get-customer-details', [F_FinanceController::class, 'getCustomerDetails']);
Route::get('frontend/finances/payment-history', [F_FinanceController::class, 'getPaymentHistory']);
Route::get('frontend/finances/{id}/due', [F_FinanceController::class, 'getDueDetails']);
Route::post('frontend/finances/pay/{id}', [F_FinanceController::class, 'payAmount']);
Route::get('frontend/finances/get-full-due/{id}', [F_FinanceController::class, 'getFullNetTermDue']);
Route::get('frontend/finances/payment-order-history', [F_FinanceController::class, 'getPaymentOrderHistory']);
Route::get('frontend/finances/payment-paid-invoice', [F_FinanceController::class, 'getPaymentPaidInvoice']);
Route::post('frontend/finances/pay-full-payment/{id}', [F_FinanceController::class, 'payfullNetTerm']);



	Route::delete('frontend/carts', [F_CustomerCartController::class, 'destroyAll']);
	Route::apiResource('frontend/carts', F_CustomerCartController::class)->names('frontend.carts');;


	Route::post('/coupons/apply', [F_CouponController::class, 'apply']);
	Route::prefix('customer')->group(function () {

		Route::post('/frontend/coupons/apply', [F_CouponController::class, 'applyCoupon']);

		// Customer coupon application
		Route::post('apply-coupon', [F_CouponController::class, 'applyCustomerCoupon']);

		Route::get('check-coupon', [F_CouponController::class, 'checkCustomerCoupon']);

		Route::get('available-coupons', [F_CouponController::class, 'getAvailableCoupons']);

		// Customer coupon history
		Route::get('coupon-history', [F_CouponController::class, 'getCustomerCouponHistory']);

		// Check if coupon code exists (without applying)
		Route::post('check-coupon', [F_CouponController::class, 'checkCouponCode']);
	});
	Route::get('frontend/pre-purchase-claims', [F_PrePurchaseClaimController::class, 'index']);
	Route::get('frontend/pre-purchase-claims/{id}', [F_PrePurchaseClaimController::class, 'show']);
	Route::apiResource('frontend/post-purchase-claims', F_PostPurchaseClaimController::class)
	->names('frontend.post-purchase-claims');

	Route::post('/screen-transaction', [NoFraudController::class, 'screenTransaction']);

	Route::get('/frontend/invoices', [F_InvoiceController::class, 'index']);
	Route::get('/frontend/invoices/{id}', [F_InvoiceController::class, 'show']);
	Route::post('/frontend/invoices', [F_InvoiceController::class, 'store']);

	Route::post('frontend/customers/change-password', [F_CustomerController::class, 'changePassword']);

	Route::post('/frontend/support-tickets', [F_SupportTicketController::class, 'store']);
	Route::get('/frontend/support-tickets', [F_SupportTicketController::class, 'index']);
	Route::get('/frontend/support-tickets/{id}', [F_SupportTicketController::class, 'show']);
	Route::get('/frontend/customers/{customer_id}/support-tickets', [F_SupportTicketController::class, 'getTicketsByCustomer']);

	Route::get('/frontend/customer-documents', [F_CustomerDocumentController::class, 'index']);
	Route::post('/frontend/customer-documents', [F_CustomerDocumentController::class, 'store']);
	Route::post('/frontend/customer-documents/{id}', [F_CustomerDocumentController::class, 'update']);
	Route::delete('/frontend/customer-documents/{id}', [F_CustomerDocumentController::class, 'destroy']);
	Route::get('/frontend/customer-documents/{customer_id}', [F_CustomerDocumentController::class, 'customerDocuments']);

	Route::prefix('frontend')->group(function () {
		Route::get('/company-profiles', [CompanyProfileController::class, 'index']);
		Route::post('/company-profiles', [CompanyProfileController::class, 'store']);
		Route::get('/company-profiles/{id}', [CompanyProfileController::class, 'show']);
		Route::put('/company-profiles/{id}', [CompanyProfileController::class, 'update']);
		Route::delete('/company-profiles/{id}', [CompanyProfileController::class, 'destroy']);
	});

	Route::apiResource('/frontend/contact-directories', F_ContactDirectoryController::class);

	Route::get('/frontend/products/{id}/alternates', [F_AlternateProductController::class, 'getAlternateProducts']);
	Route::get('/frontend/products/{id}/fbt', [F_FbtProductController::class, 'getFbtProducts']);
	Route::get('/frontend/products/{id}/dif', [F_DiffProductController::class, 'getDiffProducts']);
	Route::post('/frontend/customer-address/default', [F_CustomerAddressController::class, 'updateDefaultAddress']);
	// Route::apiResource('frontend/customer-address', F_CustomerAddressController::class);
	Route::apiResource('frontend/customer-address', F_CustomerAddressController::class)
	->names('frontend.customer-address');

	Route::get('/frontend/quotes/{id}/email-pdf', [F_QuoteController::class, 'emailPdf']);
	Route::get('/frontend/quotes/{id}/download-pdf', [F_QuoteController::class, 'downloadPdf']);
	Route::apiResource('frontend/quotes', F_QuoteController::class)->names('frontend.quotes');


	Route::get('frontend/orders/tracking', [F_OrderController::class, 'orderTracking']);
	Route::post('frontend/order-products/multiple-return', [F_ReturnOrderProductController::class, 'multipleReturn']);
	Route::post('frontend/order-products/{id}/return', [F_ReturnOrderProductController::class, 'store']);
	Route::get('frontend/orders/buy-it-again', [F_OrderController::class, 'buyItAgain']);
	Route::put('frontend/orders/{id}/status', [F_OrderController::class, 'updateStatus']);
	Route::apiResource('frontend/orders', F_OrderController::class)	->names('frontend.orders');

	Route::post('/frontend/compress-image-check', [F_OrderController::class, 'compressImage']);

	Route::get('/frontend/user-stats', [F_OrderController::class, 'userStats']);
	



	Route::post('/frontend/logout', [F_AuthController::class, 'logout']);

	Route::post('/frontend/wishlist/add', [F_WishlistController::class, 'addToWishlist']);
	Route::get('/frontend/wishlist', [F_WishlistController::class, 'getWishlist']);
	Route::delete('/frontend/wishlist/remove', [F_WishlistController::class, 'removeFromWishlist']);

	// Additional wishlist routes
	Route::get('/frontend/wishlist/check/{product_id}', [F_WishlistController::class, 'checkWishlist']);
	Route::get('/frontend/wishlist/count', [F_WishlistController::class, 'getWishlistCount']);
	Route::Post('/frontend/wishlist/remove-multiple', [F_WishlistController::class, 'removeMultipleFromWishlist']);
	Route::Post('/frontend/wishlist/add-multiple', [F_WishlistController::class, 'addMultipleToWishlist']);

	Route::get('/customer-reviews', [F_UserReviewController::class, 'getCustomerReviews']);
	Route::post('/add-customer-reviews', [F_UserReviewController::class, 'createReview']);
	Route::post('/customer-reviews-update/{id}', [F_UserReviewController::class, 'updateReview']);
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
	Route::post('/frontend/cart/product/{productId}', [F_CartController::class, 'clearProductFromCarts']);
	Route::post('/frontend/cart/update-quantity', [F_CartController::class, 'updateCartQuantity']);
	Route::post('/frontend/cart/decrease-quantity', [F_CartController::class, 'decreaseQuantity']);
	Route::post('/frontend/cart/add-multiple', [F_CartController::class, 'addMultipleToCart']);
	Route::get('/frontend/cart-summary', [F_CartController::class, 'cartSummary']);
	Route::get('/frontend/cart/total-products', [F_CartController::class, 'totalProductsInCart']);
	Route::delete('/frontend/cart/delete', [F_CartController::class, 'deleteFromCart']);




	Route::get('/frontend/categoryproducts', [F_CategoryController::class, 'getAllFeaturedProductsByCategory']);

	Route::get('/frontend/coupons/customer', [F_CustomerController::class, 'getCustomerCoupons']);
	Route::get('/frontend/coupons/search', [F_CustomerController::class, 'searchCustomerCoupons']);
	Route::post('/frontend/update-profile', [F_CustomerController::class, 'updateProfile']);
	Route::get('/frontend/user/get-profile', [F_CustomerController::class, 'getProfile']);


	Route::get('/frontend/products', [F_ProductController::class, 'getAllProducts']);
	Route::get('/frontend/products/{id}/related', [F_ProductController::class, 'relatedProducts']);
	Route::get('/frontend/brands/{id}/products', [F_ProductController::class, 'productsByBrand']);
	Route::get('/frontend/brands/{id}/sale-products', [F_ProductController::class, 'saleProductsByBrand']);
	Route::get('/frontend/products/random/{category_id}', [F_ProductController::class, 'getRandomProducts']);
	Route::get('/auth/category-random-products/{categoryId}', [F_ProductController::class, 'getCategoryWiseRandomProductsForUser']);

	Route::post('/frontend/save-for-later', [F_SaveForLaterController::class, 'saveForLater']);
	Route::get('/frontend/save-for-later', [F_SaveForLaterController::class, 'showSaveForLater']);
	Route::delete('/frontend/remove-from-save-for-later/{product_id}', [F_SaveForLaterController::class, 'removeFromSaveForLater']);

	Route::apiResource('/frontend/payments',  F_PaymentManagementController::class)->names('frontend.payments');
	Route::post('/frontend/payments/cash-delivery',  [F_PaymentManagementController::class,'paymentCashDelivery']);


	Route::prefix('/frontend/blogs')->group(function () {
		Route::post('/{id}/comments', [F_BlogController::class, 'postComment']);
	});

	Route::get('/frontend-categories/user-featured-products', [FCategoryController::class, 'getUserFeaturedCategoryProducts']);

});

Route::get('/frontend/guest/products/{id}/alternates', [F_AlternateProductController::class, 'getAlternateGuestProducts']);
Route::get('/frontend/guest/products/{id}/fbt', [F_FbtProductController::class, 'getFbtGuestProducts']);
Route::get('/frontend/guest/products/{id}/dif', [F_DiffProductController::class, 'getDiffGuestProducts']);

Route::post('/frontend/product-questions', [F_ProductQuestionController::class, 'store']);


Route::get('/category-random-products/{categoryId}', [F_ProductController::class, 'getCategoryWiseRandomProducts']);

// Route::get('/frontend/sale-categories/{id}', [F_ProductController::class, 'saleProductsByCategory']);
Route::get('/frontend/sale-categories/{id?}', [F_ProductController::class, 'saleProductsByCategory']);



Route::post('/frontend/guest/view-product', [F_RecentlyViewedProductController::class, 'saveGuestProductView']);
Route::get('/frontend/guest/recent-products', [F_RecentlyViewedProductController::class, 'getGuestRecentProducts']);

Route::get('/frontend-categories/featured-products', [FCategoryController::class, 'getFeaturedCategoryProducts']);
Route::get('/frontend-categories/with-parents', [FCategoryController::class, 'fetchCategoriesWithParents']);
Route::get('/frontend-categories', [FCategoryController::class, 'index']);


Route::get('/frontend/home-categories', [F_CategoryController::class, 'fetchCategories']);
Route::get('/frontend/categories/sale', [F_CategoryController::class, 'saleCategories']);
Route::get('/frontend/all-categories', [F_CategoryController::class, 'fetchAllCategories']);
Route::get('/frontend/categoryguestproducts', [F_CategoryController::class, 'getAllGuestFeaturedProductsByCategory']);
Route::get('/frontend/categories', [F_CategoryController::class, 'index']);
Route::get('/frontend/categories/{slug}', [F_CategoryController::class, 'categoryslug']);
Route::get('/frontend/categories/{id}', [F_CategoryController::class, 'show']);
Route::get('/frontend/categories/{categoryId}/products', [F_CategoryController::class, 'getProductsByCategory']);
Route::get('/frontend/products/specification-filters', [F_CategoryController::class, 'getSpecificationFilters']);
Route::get('/frontend/products/specification-filters1', [F_CategoryController::class, 'getSpecificationFilters1']);

Route::post('/frontend/products/filters', [FilterController::class, 'index']);


Route::get('/frontend/category-with-slug/{slug}', [F_CategoryMenuController::class, 'showCategoryBySlug']);
Route::get('/frontend/menu-categories', [F_CategoryMenuController::class, 'menuCategories']);
Route::get('/frontend/categories-with-children', [F_CategoryMenuController::class, 'getCategoriesWithChildren']);

Route::get('/frontend/cart/total-products-guest', [F_CartController::class, 'totalProductsInCartGuest']);
Route::get('/frontend/cart/guest', [F_CartController::class, 'viewCartGuest']);
Route::delete('/frontend/cart/guest/clear', [F_CartController::class, 'clearCartGuest']);

Route::get('/frontend/faqs/product/{product_id}', [F_FaqController::class, 'getFaqsByProduct']);

Route::get('/frontend/product-reviews', [F_UserReviewController::class, 'getProductReviews']);

Route::get('/frontend/products/{product_id}/you-may-like-guest', [F_ProductYouMayLikeController::class, 'getProductsYouMayLikeGuest']);

Route::get('/frontend/product/{productId}/attributes', [F_ProductAttributeController::class, 'getAttributesByProduct']);
Route::get('frontend/product/{id}/nutrition-facts', [F_ProductAttributeController::class, 'getNutritionFactsByProduct']);
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
Route::get('/frontend/country-phonecodes', [F_CountryController::class, 'getPhoneCodes']);




Route::prefix('/frontend/blogs')->group(function () {
	Route::get('/', [F_BlogController::class, 'index']);
	Route::get('/{slug}', [F_BlogController::class, 'show']);
	Route::post('/{id}/like', [F_BlogController::class, 'like']);
	Route::post('/{id}/share', [F_BlogController::class, 'share']);
	Route::post('/{id}/view', [F_BlogController::class, 'view']);
	// Route::put('/{id}/comment', [F_BlogController::class, 'postComment']);
	Route::get('/{postId}/comments', [F_BlogController::class, 'viewComments']);

});
Route::get('/frontend/blog-categories', [F_BlogController::class, 'categories']);
Route::get('/frontend/category/{slug}/blog', [F_BlogController::class, 'blogsByCategorySlug']);
Route::get('/frontend/categories-with-blogs', [F_BlogController::class, 'categoryWiseBlogs']);

Route::get('/frontend/sliders', [F_SliderController::class, 'index']);
Route::get('/frontend/sliders/{id}', [F_SliderController::class, 'show']);

Route::get('/frontend/products-guest', [F_ProductController::class, 'getAllPublicProducts']);
Route::get('/frontend/brands/{id}/summary', [F_ProductController::class, 'brandSummaryStats']);

Route::get('/frontend/search', [F_SearchController::class, 'search']);
Route::get('/frontend/search-categories', [F_SearchController::class, 'searchCategories']);
Route::get('/frontend/search/products', [F_SearchController::class, 'getProductsOnly']);
Route::get('/frontend/nlp-search', [F_SearchController::class, 'searchnlp']);

Route::post('/frontend/payment-square', [F_SquarePaymentController::class, 'createPayment']);

Route::get('/frontend/location', [F_LocationController::class, 'getLocation']);
Route::get('/frontend/get-coordinates', [F_LocationController::class, 'getCoordinates']);
Route::post('/frontend/get-location', [F_LocationController::class, 'getAddress']);

Route::post('/frontend/made-to-orders', [F_MadeToOrderController::class, 'store']);

Route::post('/find-shipping-charges', [ShippingReportController::class, 'findShippingCharges']);
Route::get('/frontend/llms.txt', [TxtllmsController::class, 'getAllPageTxt']);
Route::get('/frontend/llms-1.txt', [TxtllmsController::class, 'getProductsTxt1']);
Route::get('/frontend/llms-2.txt', [TxtllmsController::class, 'getProductsTxt2']);
Route::get('/frontend/llms-3.txt', [TxtllmsController::class, 'getProductsTxt3']);
Route::get('/frontend/llms-4.txt', [TxtllmsController::class, 'getProductsTxt4']);
Route::get('/frontend/llms-5.txt', [TxtllmsController::class, 'getProductsTxt5']);
Route::get('/frontend/llms-6.txt', [TxtllmsController::class, 'getProductsTxt6']);
Route::get('/frontend/llms-7.txt', [TxtllmsController::class, 'getProductsTxt7']);
Route::get('/frontend/llms-8.txt', [TxtllmsController::class, 'getProductsTxt8']);
Route::get('/frontend/llms-9.txt', [TxtllmsController::class, 'getProductsTxt9']);
Route::get('/frontend/sitemap.xml', [SitemapController::class, 'getSitemap']);
Route::get('/frontend/categories.xml', [SitemapController::class, 'getCategoriesSitemap']);
Route::get('/frontend/products.xml', [SitemapController::class, 'getProductsSitemap']);

Route::get('/frontend/products-1.xml', [SitemapController::class, 'getProductsSitemap1']);
Route::get('/frontend/products-2.xml', [SitemapController::class, 'getProductsSitemap2']);
Route::get('/frontend/products-3.xml', [SitemapController::class, 'getProductsSitemap3']);
Route::get('/frontend/products-4.xml', [SitemapController::class, 'getProductsSitemap4']);
Route::get('/frontend/products-5.xml', [SitemapController::class, 'getProductsSitemap5']);
Route::get('/frontend/products-6.xml', [SitemapController::class, 'getProductsSitemap6']);
Route::get('/frontend/products-7.xml', [SitemapController::class, 'getProductsSitemap7']);
Route::get('/frontend/products-8.xml', [SitemapController::class, 'getProductsSitemap8']);
Route::get('/frontend/products-9.xml', [SitemapController::class, 'getProductsSitemap9']);
Route::get('/frontend/products-10.xml', [SitemapController::class, 'getProductsSitemap10']);

Route::get('/frontend/blog.xml', [SitemapController::class, 'getBlogSitemap']);
Route::get('/frontend/brand.xml', [SitemapController::class, 'getBrandSitemap']);
Route::get('/frontend/image.xml', [SitemapController::class, 'getImageSitemap']);
Route::get('/category-pages/{category}', [CategoryPageController::class, 'show']);
Route::get('/category-pages', [CategoryPageController::class, 'index']);

Route::get('/feed/products.xml', [ProductXMLFeedWatchController::class, 'generateProductFeed']);
Route::get('/feed/one-products.xml', [ProductXMLFeedWatchController::class, 'generateOneProductFeed']);
Route::get('/feed/products-1.xml', [ProductXMLFeedWatchController::class, 'getProductFeed1']);
Route::get('/feed/products-2.xml', [ProductXMLFeedWatchController::class, 'getProductFeed2']);
Route::get('/feed/products-3.xml', [ProductXMLFeedWatchController::class, 'getProductFeed3']);
Route::get('/feed/products-4.xml', [ProductXMLFeedWatchController::class, 'getProductFeed4']);
Route::get('/feed/products-5.xml', [ProductXMLFeedWatchController::class, 'getProductFeed5']);
Route::get('/feed/one-products.xml', [ProductXMLFeedWatchController::class, 'generateOneProductFeed']);
Route::prefix('/frontend/ccavenue')->group(function () {
	Route::post('/initiate-payment', [F_CCavenueController::class, 'initiatePayment']);
	Route::post('/handle-response', [F_CCavenueController::class, 'handleResponse']);
	Route::post('/failed', [F_CCavenueController::class, 'failed']);
	Route::get('/payment-status/{orderId}', [F_CCavenueController::class, 'getPaymentStatus']);
});


Route::post('frontend/tql-token', [TqlRateController::class, 'tqltoken']);
Route::post('frontend/tql-rate', [TqlRateController::class, 'tqlRates']);
Route::post('frontend/tql-createQuote', [TqlRateController::class, 'createQuote']);
Route::post('frontend/tql-tenderShipment', [TqlRateController::class, 'tenderShipment']);
Route::get('frontend/tql-getQuote/{quoteId}', [TqlRateController::class, 'getQuote']);
Route::get('frontend/tql-tracking/{poNumber}', [TqlRateController::class, 'getTracking']);

Route::post('/payment/ccavenue/notify', [F_CCavenueController::class, 'paymentSuccess']);
Route::post('/ccavenue/failed', [F_CCavenueController::class, 'paymentFailed']);

Route::post('/stripe/create-stripe-payment-link', [F_StripeController::class, 'createStripePaymentLink']);
Route::post('/stripe/webhook', [F_StripeController::class, 'handleWebhook']);
Route::get('/stripe/thanks', [F_StripeController::class, 'success']);
Route::get('/stripe/failed', [F_StripeController::class, 'paymentFailed']);
Route::post('/stripe/create-payment-intent', [F_StripeController::class, 'createPaymentIntent']);
Route::prefix('stripe')->group(function () {
	Route::post('/create-payment-intent', [F_StripeController::class, 'createPaymentIntent']);
	Route::post('/confirm-payment-intent', [F_StripeController::class, 'confirmPaymentIntent']);
});
Route::post('frontend/product-errors', [F_ProductErrorController::class, 'store']);
Route::get('frontend/product-errors', [F_ProductErrorController::class, 'index']);
Route::get('frontend/product-errors/{product_id}', [F_ProductErrorController::class, 'show']);

Route::post('frontend/tamara/checkout', [F_TamaraController::class, 'createCheckout']);
Route::post('frontend/tamara/webhook', [F_TamaraController::class, 'handleWebhook']);

Route::get('frontend/location-info', [F_GeoController::class, 'getLocationInfo']);
Route::get('/address-autocomplete', [F_GeoController::class, 'addressAutocomplete']);
Route::get('/addressgcc-autocomplete', [F_GeoController::class, 'addressAutocompleteGCC']);

Route::get('frontend/lookup', [F_LookupController::class, 'lookup']);
Route::get('frontend/tax/rate', [F_TaxController::class, 'getRate']);
Route::post('frontend/tax/calculate', [F_TaxController::class, 'calculateTax']);

Route::get('/frontend/google-reviews', [F_GoogleReviewController::class, 'getReviews']);
Route::apiResource('/frontend/inquiries',  F_InquiryController::class)->names('frontend.inquiries');

// POST when user searches or clicks
Route::post('/frontend/search-logs', [F_SearchLogController::class, 'store']);

// GET logs for analytics
Route::get('/frontend/search-logs', [F_SearchLogController::class, 'index']);

// Create banner
Route::get('/frontend/menu-banners', [F_MenuBannerController::class, 'index']);
Route::get('/frontend/menu-banners/{id}', [F_MenuBannerController::class, 'show']);
Route::get('/frontend/menu-banners/category/{category_id}', [F_MenuBannerController::class, 'showCategory']);
Route::post('/frontend/auth/Stax', [F_StaxPaymentController::class, 'checkout']);
Route::post('/webhook/stax', [F_StaxPaymentController::class, 'handleWebhook']);
Route::any('/stax/thanks', [F_StaxPaymentController::class, 'thanks']);


	// Route::get('/redirects/from/{from}', [RedirectLinkController::class, 'getByFrom']);
Route::get('redirects/from/{from}', [RedirectLinkController::class, 'getByFrom'])
->where('from', '.*');

Route::prefix('frontend/auth')->group(function () {

	//Route::post('finances/post', [F_FinanceController::class, 'store']); // get payment_token

	// Stax Payment Routes
	Route::post('/Stax', [F_StaxPaymentController::class, 'checkout'])
	->name('stax.checkout');

	Route::get('/Stax/transaction/{id}', [F_StaxPaymentController::class, 'getTransaction'])
	->name('stax.transaction');

	Route::post('/Stax/refund/{id}', [F_StaxPaymentController::class, 'refund'])
	->name('stax.refund');

	Route::post('/Stax/void/{id}', [F_StaxPaymentController::class, 'void'])
	->name('stax.void');

	Route::post('/Stax/tokenize', [F_StaxPaymentController::class, 'tokenizeCard'])
	->name('stax.card-charge');

});
Route::any('/stax/thanks', [F_StaxPaymentController::class, 'thanks']);

Route::post('frontend/paymob/initiate', [F_PaymobController::class, 'initiate']); // get payment_token
Route::post('frontend/paymob/pay', [F_PaymobController::class, 'pay']);
Route::get('frontend/paymob/thank', [F_PaymobController::class, 'pay']);


Route::post('paymob/webhook', [F_PaymobController::class, 'webhook']);
Route::get('paymob/webhook', [F_PaymobController::class, 'webhook']);
Route::get('paymob/thanks', [F_PaymobController::class, 'response']);


	Route::post('/frontend/save-cheque-upload', [ F_OrderController::class, 'saveChequeUpload']);
	Route::get('/frontend/get-cheque-uploads', [ F_OrderController::class, 'getChequeUploadsBySession']);

// test change
