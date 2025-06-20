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
		Schema::create('return_order_products', function (Blueprint $table) {
			$table->id();
			$table->string('refund_number')->unique();
			$table->integer('order_product_id');
			$table->integer('quantity');
			$table->string('reason');
			$table->text('product_images')->nullable();
			$table->text('product_videos')->nullable();
			$table->text('description')->nullable();
			$table->enum('status', ['requested', 'received', 'inspected', 'approved', 'rejected', 'refunded'])->default('requested');
			$table->integer('inspected_by')->nullable();
			$table->text('comment')->nullable();
			$table->enum('refund_status', ['in_finance', 'refunded', 'refund_failed'])->nullable();
			$table->decimal('refund_amount', 10, 2)->nullable();
			$table->string('refund_method')->nullable();
			$table->date('refund_date')->nullable();
			$table->integer('updated_by')->nullable();
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('return_order_products');
	}
};
