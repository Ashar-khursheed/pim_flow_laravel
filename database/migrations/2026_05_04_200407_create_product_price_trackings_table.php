<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	/* Run the migrations */
	public function up(): void
	{
		Schema::create('product_price_trackings', function (Blueprint $table) {
			$table->increments('id');
			$table->unsignedInteger('product_price_id')->index();
			$table->string('field');
			$table->string('old_value')->nullable();
			$table->string('new_value')->nullable();
			$table->unsignedInteger('created_by');
			$table->timestamps();
		});

		/* Seed inventory tracking from product_suppliers */
		DB::table('product_price_trackings')->insertUsing(
			['product_price_id', 'field', 'old_value', 'new_value', 'created_by', 'created_at', 'updated_at'],
			DB::table('product_suppliers')
			->whereNotNull('inventory_updated_by')
			->whereNotNull('inventory_updated_at')
			->select(
				'id as product_price_id',
				DB::raw("'inventory' as field"),
				DB::raw('NULL as old_value'),
				'inventory as new_value',
				'inventory_updated_by as created_by',
				'inventory_updated_at as created_at',
				'inventory_updated_at as updated_at',
			)
		);

		/* Drop inventory audit columns from product_suppliers */
		Schema::table('product_suppliers', function (Blueprint $table) {
			$table->dropColumn(['inventory_updated_by', 'inventory_updated_at']);
		});
	}

	/* Reverse the migrations */
	public function down(): void
	{
		/* Restore inventory audit columns */
		Schema::table('product_suppliers', function (Blueprint $table) {
			$table->unsignedInteger('inventory_updated_by')->nullable()->after('inventory');
			$table->timestamp('inventory_updated_at')->nullable()->after('inventory_updated_by');
		});

		Schema::dropIfExists('product_price_trackings');
	}
};