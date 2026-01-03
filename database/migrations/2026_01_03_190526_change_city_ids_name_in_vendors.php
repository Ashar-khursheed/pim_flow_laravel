<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
	public function up()
	{
		/* First, handle the data - keep only the first city_id from comma-separated values */
		DB::statement("
			UPDATE vendors
				SET city_ids = SUBSTRING_INDEX(city_ids, ',', 1)
				WHERE city_ids IS NOT NULL AND city_ids LIKE '%,%'
				");

		/* Rename the column from city_ids to city_id */
		Schema::table('vendors', function (Blueprint $table) {
			$table->renameColumn('city_ids', 'city_id');
		});

		/* Change column type to integer and position it after address */
		Schema::table('vendors', function (Blueprint $table) {
			$table->integer('city_id')->nullable()->after('address')->change();
		});
	}

	public function down()
	{
		/* Rename back to city_ids */
		Schema::table('vendors', function (Blueprint $table) {
			$table->renameColumn('city_id', 'city_ids');
		});

		/* Change back to string type */
		Schema::table('vendors', function (Blueprint $table) {
			$table->string('city_ids')->nullable()->change();
		});
	}
};