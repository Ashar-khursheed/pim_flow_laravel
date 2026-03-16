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
		Schema::table('customer_cart_products', function (Blueprint $table) {
			$table->dropColumn('accessories_options');
			$table->decimal('accessory_item_charge', 10, 2)->default(0)->after('amount');
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::table('customer_cart_products', function (Blueprint $table) {
			$table->dropColumn('accessory_item_charge');
			$table->text('accessories_options')->nullable()->after('amount');
		});
	}
};