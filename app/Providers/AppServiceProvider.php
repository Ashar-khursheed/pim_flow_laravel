<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Observers\TransactionLogObserver;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\AttributeMeasurement;
use App\Models\AttributeGroup;
use App\Models\CategoryAttributeGroup;

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
		Attribute::observe(TransactionLogObserver::class);
		AttributeValue::observe(TransactionLogObserver::class);
		AttributeMeasurement::observe(TransactionLogObserver::class);
		AttributeGroup::observe(TransactionLogObserver::class);
		CategoryAttributeGroup::observe(TransactionLogObserver::class);
	}
}
