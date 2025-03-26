<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	/**
	 * Run the migrations.
	 */
	public function up(): void
	{
		Schema::create('websites', function (Blueprint $table) {
			$table->id();
			$table->string('name')->index();
			$table->integer('currency_id')->nullable();
			$table->timestamps();
		});

		Schema::table('ec_products', function (Blueprint $table) {
			$table->string('website_ids')->nullable()->after('name');
		});

		Schema::table('ec_product_categories', function (Blueprint $table) {
			$table->string('website_ids')->nullable()->after('is_featured');
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::table('ec_product_categories', function (Blueprint $table) {
			$table->dropColumn('website_ids');
		});
		Schema::table('ec_products', function (Blueprint $table) {
			$table->dropColumn('website_ids');
		});
		Schema::dropIfExists('websites');
	}
};
