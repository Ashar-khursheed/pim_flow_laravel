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
		Schema::create('pre_purchase_claims', function (Blueprint $table) {
			$table->id();
			$table->integer('customer_id');
			$table->integer('customer_address_id')->nullable();
			$table->string('product_id');
			$table->decimal('product_price', 10, 2);
			$table->string('product_quantity');
			$table->text('competitor_product_url');
			$table->decimal('competitor_product_price', 10, 2);
			$table->decimal('competitor_product_shipping_charge', 8, 2)->default(0);
			$table->text('competitor_screenshot_url')->nullable();
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('pre_purchase_claims');
	}
};
