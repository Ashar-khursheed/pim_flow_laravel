<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('product_suppliers', function (Blueprint $table) {
			$table->integer('min_quantity')->default(1)->after('in_stock');
			$table->boolean('is_fixed')->default(0)->after('min_quantity');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('product_suppliers', function (Blueprint $table) {
			$table->dropColumn(['min_quantity', 'is_fixed']);
		});
	}
};
