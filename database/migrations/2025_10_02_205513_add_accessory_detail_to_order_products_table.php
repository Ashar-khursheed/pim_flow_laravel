<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up()
	{
		Schema::table('order_products', function (Blueprint $table) {
			$table->text('accessory_item_ids')->nullable()->after('amount');
			$table->decimal('accessory_item_charge', 10, 2)->default(0)->after('accessory_item_ids');
		});
		Schema::table('quote_products', function (Blueprint $table) {
			$table->text('accessory_item_ids')->nullable()->after('amount');
			$table->decimal('accessory_item_charge', 10, 2)->default(0)->after('accessory_item_ids');
		});
	}

	public function down()
	{
		Schema::table('order_products', function (Blueprint $table) {
			$table->dropColumn(['accessory_item_ids', 'accessory_item_charge']);
		});
		Schema::table('quote_products', function (Blueprint $table) {
			$table->dropColumn(['accessory_item_ids', 'accessory_item_charge']);
		});
	}
};
