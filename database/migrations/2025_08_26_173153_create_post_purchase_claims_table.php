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
		Schema::create('post_purchase_claims', function (Blueprint $table) {
			$table->id();
			$table->integer('customer_id');
			$table->string('order_id');
			$table->string('order_product_id');
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
		Schema::dropIfExists('post_purchase_claims');
	}
};
