<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Database\Eloquent\Relations\Relation;
use App\Models\FrontEnd\QuoteProduct;
use App\Models\FrontEnd\OrderProduct;

use App\Observers\TransactionLogObserver;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\AttributeMeasurement;
use App\Models\AttributeGroup;
use App\Models\CategoryAttributeGroup;
use App\Models\Product;
use App\Models\Category;

// use App\Models\AppKeyword;
// use App\Models\AppKeywordTranslation;
// use App\Models\AttributeRecommendation;
// use App\Models\Blog;
// use App\Models\Brand;
// use App\Models\BrandTemp1;
// use App\Models\BrandTemp2;
// use App\Models\BrandTemp3;
// use App\Models\CategoryPage;
// use App\Models\CcavenueTransaction;
// use App\Models\City;
// use App\Models\Country;
// use App\Models\Currency;
// use App\Models\Faq;
// use App\Models\FaqCategory;
// use App\Models\GradingRule;
// use App\Models\Language;
// use App\Models\MeasurementType;
// use App\Models\MeasurementUnit;
// use App\Models\MediaFile;
// use App\Models\Metabox;
// use App\Models\Newsletter;
// use App\Models\Order;
// use App\Models\OrderAddress;
// use App\Models\OrderHistory;
// use App\Models\OrderReferral;
// use App\Models\OrderReturn;
// use App\Models\OrderReturnItem;
// use App\Models\Permission;
// use App\Models\PreOnboardingVendor;
// use App\Models\ProductAttribute;
// use App\Models\ProductCategory;
// use App\Models\ProductGroup;
// use App\Models\ProductGroupItem;
// use App\Models\ProductSupplier;
// use App\Models\RedirectLink;
// use App\Models\Review;
// use App\Models\Role;
// use App\Models\SeoDetail;
// use App\Models\SeoManagement;
// use App\Models\SeoSecondaryKeyword;
// use App\Models\SimpleSlider;
// use App\Models\SimpleSliderItem;
// use App\Models\Slug;
// use App\Models\Store;
// use App\Models\SubCategory;
// use App\Models\Tag;
// use App\Models\TransactionLog;
// use App\Models\User;
// use App\Models\Vendor;
// use App\Models\VendorContact;
// use App\Models\VendorDocument;
// use App\Models\Website;
// use App\Models\Zipcode;

class AppServiceProvider extends ServiceProvider
{
	/**
	 * Register any application services.
	 */
	public function register(): void
	{
		//
	}

	/**
	 * Bootstrap any application services.
	 */
	public function boot(): void
	{
		foreach (config('units') as $unitClass) {
			new $unitClass(0, $unitClass::getUnitDefinitions()[0]->getName());
		}

		Attribute::observe(TransactionLogObserver::class);
		AttributeValue::observe(TransactionLogObserver::class);
		AttributeMeasurement::observe(TransactionLogObserver::class);
		AttributeGroup::observe(TransactionLogObserver::class);
		CategoryAttributeGroup::observe(TransactionLogObserver::class);
		Product::observe(TransactionLogObserver::class);
		Category::observe(TransactionLogObserver::class);

		Relation::morphMap([
			'quote_product' => QuoteProduct::class,
			'order_product' => OrderProduct::class,
		]);

		if (app()->environment('production')) {
			URL::forceScheme('https');
		}

		RateLimiter::for('emails', function ($job) {
			return Limit::perSecond(2)->by('emails');
		});

		// AppKeyword::observe(TransactionLogObserver::class);
		// AppKeywordTranslation::observe(TransactionLogObserver::class);
		// AttributeRecommendation::observe(TransactionLogObserver::class);
		// Blog::observe(TransactionLogObserver::class);
		// Brand::observe(TransactionLogObserver::class);
		// BrandTemp1::observe(TransactionLogObserver::class);
		// BrandTemp2::observe(TransactionLogObserver::class);
		// BrandTemp3::observe(TransactionLogObserver::class);
		// CategoryPage::observe(TransactionLogObserver::class);
		// CcavenueTransaction::observe(TransactionLogObserver::class);
		// City::observe(TransactionLogObserver::class);
		// Country::observe(TransactionLogObserver::class);
		// Currency::observe(TransactionLogObserver::class);
		// Faq::observe(TransactionLogObserver::class);
		// FaqCategory::observe(TransactionLogObserver::class);
		// GradingRule::observe(TransactionLogObserver::class);
		// Language::observe(TransactionLogObserver::class);
		// MeasurementType::observe(TransactionLogObserver::class);
		// MeasurementUnit::observe(TransactionLogObserver::class);
		// MediaFile::observe(TransactionLogObserver::class);
		// Metabox::observe(TransactionLogObserver::class);
		// Newsletter::observe(TransactionLogObserver::class);
		// Order::observe(TransactionLogObserver::class);
		// OrderAddress::observe(TransactionLogObserver::class);
		// OrderHistory::observe(TransactionLogObserver::class);
		// OrderReferral::observe(TransactionLogObserver::class);
		// OrderReturn::observe(TransactionLogObserver::class);
		// OrderReturnItem::observe(TransactionLogObserver::class);
		// Permission::observe(TransactionLogObserver::class);
		// PreOnboardingVendor::observe(TransactionLogObserver::class);
		// ProductAttribute::observe(TransactionLogObserver::class);
		// ProductCategory::observe(TransactionLogObserver::class);
		// ProductGroup::observe(TransactionLogObserver::class);
		// ProductGroupItem::observe(TransactionLogObserver::class);
		// ProductSupplier::observe(TransactionLogObserver::class);
		// RedirectLink::observe(TransactionLogObserver::class);
		// Review::observe(TransactionLogObserver::class);
		// Role::observe(TransactionLogObserver::class);
		// SeoDetail::observe(TransactionLogObserver::class);
		// SeoManagement::observe(TransactionLogObserver::class);
		// SeoSecondaryKeyword::observe(TransactionLogObserver::class);
		// SimpleSlider::observe(TransactionLogObserver::class);
		// SimpleSliderItem::observe(TransactionLogObserver::class);
		// Slug::observe(TransactionLogObserver::class);
		// Store::observe(TransactionLogObserver::class);
		// SubCategory::observe(TransactionLogObserver::class);
		// Tag::observe(TransactionLogObserver::class);
		// TransactionLog::observe(TransactionLogObserver::class);
		// User::observe(TransactionLogObserver::class);
		// Vendor::observe(TransactionLogObserver::class);
		// VendorContact::observe(TransactionLogObserver::class);
		// VendorDocument::observe(TransactionLogObserver::class);
		// Website::observe(TransactionLogObserver::class);
		// Zipcode::observe(TransactionLogObserver::class);
	}
}
