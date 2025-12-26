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
			$table->string('utm_id')->nullable();
			$table->string('order_number')->unique();
			$table->integer('customer_id');
			$table->integer('customer_address_id');

			$table->decimal('shipping_charge', 10, 2);
			$table->boolean('is_lift_gate')->nullable();
			$table->boolean('is_residential_address')->nullable();
			$table->boolean('is_inside_delivery')->nullable();
			$table->decimal('amount', 10, 2);
			$table->decimal('tax_percentage', 10, 4);
			$table->decimal('tax_amount', 10, 2);
			$table->integer('coupon_id')->nullable();
			$table->decimal('discount', 10, 2)->nullable();
			$table->string('additional_amount_name')->nullable();
			$table->decimal('additional_amount_price', 10, 2)->nullable();
			$table->text('additional_amount_details')->nullable();
			$table->decimal('total_amount', 10, 2);
			$table->integer('total_products');

			$table->boolean('ship_all_at_once')->default(true);
			$table->boolean('separate_deliveries')->default(false);

			$table->boolean('is_paid')->default(false);
			$table->decimal('paid_amount', 10, 2)->default(0);
			$table->decimal('pending_amount', 10, 2)->default(0);

			$table->enum('status', [
				'Pending', 'Confirmed', 'Supplier Delivery', 'International', 'Export', 'On hold', 'Ready to ship', 'Pickups', 'Out for delivery', 'Delivered', 'Partially Delivered', 'Completed', 'Re-Attempt', 'Returned', 'Cancelled'
			])->default('Pending');
			$table->longText('payment_link')->nullable();
			$table->boolean('is_reserved')->default(false);
			$table->boolean('is_payment')->default(false);
			$table->boolean('is_squarePayment')->default(false);
			$table->boolean('is_paymob')->default(false);
			$table->boolean('is_customer_pickup')->default(false);
			$table->boolean('is_cod')->default(false);

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
			$table->decimal('unit_price', 10, 2);
			$table->decimal('amount', 10, 2);
			$table->decimal('shipping_charge', 10, 2);
			$table->decimal('total_amount', 10, 2);
			$table->integer('shipped_quantity')->default(0);
			$table->integer('remaining_quantity');
			$table->enum('status', [
				'Pending', 'Confirmed', 'Supplier Delivery', 'International', 'Export', 'On hold', 'Ready to ship', 'Pickups', 'Partially Pickups', 'Out for delivery', 'Partially Out for delivery', 'Delivered', 'Partially Delivered', 'Completed', 'Re-Attempt', 'Request Return', 'Partial Request Return', 'Partial Returned', 'Returned', 'Cancelled', 'Out of Stock'
			])->default('Pending');
			$table->timestamps();
		});

		Schema::dropIfExists('order_tracking');
		Schema::create('order_tracking', function (Blueprint $table) {
			$table->id();
			$table->integer('order_id');
			$table->integer('shipment_id')->nullable();
			$table->string('status');
			$table->text('description');
			$table->text('location')->nullable();
			$table->text('metadata')->nullable();
			$table->integer('created_by')->nullable();
			$table->timestamps();
		});

		Schema::dropIfExists('shipments');
		Schema::create('shipments', function (Blueprint $table) {
			$table->id();
			$table->integer('order_id');
			$table->string('shipment_number')->unique();
			$table->string('tracking_number')->nullable();
			$table->string('carrier')->nullable();
			$table->enum('status', [
				'Preparing', 'Shipped', 'In Transit', 'Out for Delivery',
				'Delivered', 'Failed Delivery', 'Returned'
			])->default('Preparing');
			$table->decimal('shipping_cost', 10, 2)->default(0);
			$table->date('estimated_delivery_date')->nullable();
			$table->date('actual_delivery_date')->nullable();
			$table->text('notes')->nullable();
			$table->timestamps();
		});

		Schema::dropIfExists('shipment_products');
		Schema::create('shipment_products', function (Blueprint $table) {
			$table->id();
			$table->integer('shipment_id')->nullable();
			$table->integer('order_product_id');
			$table->integer('quantity');
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
		Schema::dropIfExists('order_tracking');
		Schema::dropIfExists('shipments');
		Schema::dropIfExists('shipment_products');
	}
};
