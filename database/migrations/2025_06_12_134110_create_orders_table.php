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
		Schema::dropIfExists('orders');
		Schema::create('orders', function (Blueprint $table) {
			$table->id();
			$table->string('order_number')->unique();
			$table->integer('customer_id');
			$table->text('customer_address');
			$table->enum('status', [
				'Pending', 'Confirmed', 'Supplier Delivery', 'International', 'Export', 'On hold', 'Ready to ship', 'Pickups', 'Out for delivery', 'Delivered', 'Re-Attempt', 'Returned', 'Cancelled'
			])->default('Pending');

			$table->decimal('shipping_charge', 10, 2);
			$table->decimal('total_amount', 10, 2);
			$table->integer('total_products');

			$table->boolean('ship_all_at_once')->default(true);
			$table->boolean('separate_deliveries')->default(false);

			// Payment Status
			$table->boolean('is_paid')->default(false);
			$table->decimal('paid_amount', 10, 2)->default(0);
			$table->decimal('pending_amount', 10, 2)->default(0);

			$table->integer('created_by');
			$table->integer('updated_by')->nullable();

			$table->timestamps();
			$table->softDeletes();
		});

		Schema::dropIfExists('order_products');
		Schema::create('order_products', function (Blueprint $table) {
			$table->id();
			$table->integer('order_id');
			$table->integer('product_id');
			$table->integer('vendor_id');
			$table->integer('quantity');
			$table->integer('shipped_quantity')->default(0);
			$table->integer('remaining_quantity');
			$table->decimal('unit_price', 10, 2);
			$table->decimal('total_amount', 10, 2);
			$table->enum('status', [
				'Pending', 'Confirmed', 'Supplier Delivery', 'International', 'Export', 'On hold', 'Ready to ship', 'Pickups', 'Out for delivery', 'Delivered', 'Re-Attempt', 'Returned', 'Cancelled', 'Out of Stock'
			])->default('Pending');
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('orders');
		Schema::dropIfExists('order_products');
	}
};
