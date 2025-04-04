<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Observers\TransactionLogObserver;

use App\Models\Attribute;

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
		// Attribute::observe(TransactionLogObserver::class);
	}
}
