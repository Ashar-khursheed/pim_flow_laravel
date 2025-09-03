<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductSuppliersTable extends Migration
{
	public function up(): void
	{
		Schema::dropIfExists('product_suppliers');
		Schema::create('product_suppliers', function (Blueprint $table) {
			$table->id();
			$table->integer('product_id')->index();
			$table->integer('vendor_id')->index();
			$table->string('vendor_sku')->index();
			$table->decimal('list_price', 10, 2)->nullable();
			$table->decimal('multiple', 10, 4)->nullable();
			$table->decimal('cost_per_item', 10, 2)->index();
			$table->decimal('surcharge', 10, 2)->nullable();
			$table->decimal('additional_cost', 10, 2)->nullable();
			$table->decimal('total_cost_per_item', 10, 2)->index();
			$table->decimal('map', 10, 2)->nullable();
			$table->decimal('sale_price', 10, 2)->nullable();
			$table->decimal('price', 10, 2)->index();
			$table->integer('inventory')->nullable();
			$table->boolean('in_stock')->default(false);
			$table->string('delivery_days');
			$table->string('return_policy');
			$table->boolean('free_shipping')->default(false);
			$table->decimal('shipping_charge', 10, 2)->nullable();
			$table->decimal('margin', 10, 2)->nullable();
			$table->decimal('restocking_fees', 10, 2)->nullable();
			$table->string('warranty_information')->nullable();
			$table->integer('created_by')->index();
			$table->integer('updated_by')->nullable();
			$table->timestamps();
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('product_suppliers');
	}
}
