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
		Schema::create('accessory_charges', function (Blueprint $table) {
			$table->id();
			$table->morphs('relation');
			$table->integer('accessory_item_id');
			$table->decimal('amount', 10, 2);
			$table->timestamp('created_at')->nullable();
		});

		Schema::table('order_products', function (Blueprint $table) {
			$table->dropColumn(['accessory_item_ids']);
		});

		Schema::table('quote_products', function (Blueprint $table) {
			$table->dropColumn(['accessory_item_ids']);
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('accessory_charges');
	}
};
