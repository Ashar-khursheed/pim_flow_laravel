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
		Schema::create('horeca_page_products', function (Blueprint $table) {
			$table->id();
			$table->integer('horeca_page_id');
			$table->integer('horeca_page_product_type_id');
			$table->integer('product_id');
			$table->integer('order')->default(0);
			$table->timestamps();

			/* Indexes */
			$table->index('horeca_page_id');
			$table->index('horeca_page_product_type_id');
			$table->index('product_id');
			$table->index('order');
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('horeca_page_products');
	}
};