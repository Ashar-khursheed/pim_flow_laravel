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
		Schema::dropIfExists('quotes');
		Schema::create('quotes', function (Blueprint $table) {
			$table->id();
			$table->string('quote_number')->unique();
			$table->string('quote_name');
			$table->integer('customer_id');
			$table->integer('customer_address_id');
			$table->decimal('shipping_charge', 10, 2);
			$table->decimal('amount', 10, 2);
			$table->decimal('tax_percentage', 10, 4);
			$table->decimal('tax_amount', 10, 2);
			$table->decimal('total_amount', 10, 2);

			$table->decimal('discount_percentage', 10, 4)->nullable();
			$table->decimal('discount_amount', 10, 2)->nullable();
			$table->decimal('amount_after_discount', 10, 2)->nullable();

			$table->integer('total_products');
			$table->string('payment_terms')->nullable();
			$table->text('customer_notes')->nullable();
			$table->text('internal_notes')->nullable();
			$table->string('status')->nullable();//pending
			$table->date('expired_at')->nullable();//7

			$table->integer('created_by');
			$table->integer('updated_by')->nullable();

			$table->timestamps();
		});

		Schema::dropIfExists('quote_products');
		Schema::create('quote_products', function (Blueprint $table) {
			$table->id();
			$table->integer('quote_id');
			$table->integer('product_id');
			$table->integer('vendor_id');
			$table->integer('quantity');
			$table->decimal('unit_price', 10, 2);
			$table->decimal('amount', 10, 2);
			$table->decimal('shipping_charge', 10, 2);
			$table->decimal('total_amount', 10, 2);
			$table->timestamps();
		});

		Schema::dropIfExists('quote_emails');
		Schema::create('quote_emails', function (Blueprint $table) {
			$table->id();
			$table->integer('quote_id');
			$table->string('email');
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('quotes');
		Schema::dropIfExists('quote_products');
		Schema::dropIfExists('quote_emails');
	}
};
