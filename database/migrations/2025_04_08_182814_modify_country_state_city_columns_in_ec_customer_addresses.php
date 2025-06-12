<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
	/**
	 * Run the migrations.
	 */
	public function up(): void
	{
		DB::table('ec_customer_addresses')->truncate();
		Schema::table('ec_customer_addresses', function (Blueprint $table) {
			$table->renameColumn('country', 'country_id');
			$table->renameColumn('state', 'state_id');
			$table->renameColumn('city', 'city_id');

			$table->integer('country_id')->change();
			$table->integer('state_id')->change();
			$table->integer('city_id')->change();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::table('ec_customer_addresses', function (Blueprint $table) {
			$table->string('country_id')->change();
			$table->string('state_id')->change();
			$table->string('city_id')->change();

			$table->renameColumn('country_id', 'country');
			$table->renameColumn('state_id', 'state');
			$table->renameColumn('city_id', 'city');
		});
	}
};
