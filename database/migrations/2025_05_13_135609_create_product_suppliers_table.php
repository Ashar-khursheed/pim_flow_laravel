<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create('product_suppliers', function (Blueprint $table) {
			$table->id();
			$table->integer('product_id');
			$table->integer('vendor_id');
			$table->string('vendor_sku');
			$table->decimal('cost_per_item', 10, 2)->nullable();
			$table->decimal('additional_cost', 10, 2)->nullable();
			$table->decimal('price', 10, 2)->nullable();
			$table->decimal('sale_price', 10, 2)->nullable();
			$table->integer('inventory')->nullable();
			$table->tinyInteger('in_stock')->default(0);
			$table->string('delivery_days')->nullable();
			$table->string('warranty_information')->nullable();
			$table->string('refund')->nullable();
			$table->decimal('final_cost_price', 10, 2)->nullable();
			$table->decimal('margin', 10, 2)->nullable();
			$table->integer('created_by');
			$table->integer('updated_by')->nullable();
			$table->timestamps();
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('product_suppliers');
	}
};
