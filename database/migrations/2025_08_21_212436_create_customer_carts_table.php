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
		Schema::create('customer_carts', function (Blueprint $table) {
			$table->id();
			$table->string('reference_number')->unique();
			$table->integer('customer_id');
			$table->integer('customer_address_id');
			$table->decimal('shipping_charge', 10, 2);
			$table->boolean('is_lift_gate')->nullable();
			$table->boolean('is_residential_address')->nullable();
			$table->boolean('is_inside_delivery')->nullable();
			$table->decimal('amount', 10, 2);
			$table->decimal('tax_percentage', 10, 4);
			$table->decimal('tax_amount', 10, 2);
			$table->decimal('total_amount', 10, 2);
			$table->integer('total_products');
			$table->integer('created_by');
			$table->integer('updated_by')->nullable();
			$table->timestamps();
		});

		Schema::create('customer_cart_products', function (Blueprint $table) {
			$table->id();
			$table->integer('customer_cart_id');
			$table->integer('product_id');
			$table->integer('vendor_id');
			$table->integer('quantity');
			$table->decimal('unit_price', 10, 2);
			$table->decimal('amount', 10, 2);
			$table->decimal('shipping_charge', 10, 2);
			$table->decimal('total_amount', 10, 2);
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('customer_carts');
		Schema::dropIfExists('customer_cart_products');
	}
};
