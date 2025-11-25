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
		Schema::dropIfExists('measurement_unit_translations');
		Schema::create('measurement_unit_translations', function (Blueprint $table) {
			$table->id();
			$table->string("locale", 2);
			$table->integer("measurement_unit_id");
			$table->text("name_tr");
		});

		if (in_array(config('app.website'), ['UAE', 'UAE_T', 'SA'])) {
			/* Direct SQL insert for measurement_units */
			DB::table('measurement_unit_translations')->insertUsing(
				['locale', 'measurement_unit_id', 'name_tr'],
				DB::table('measurement_units')
				->select(
					DB::raw("'en' as locale"),
					'id as measurement_unit_id',
					'name as name_tr'
				)
				->orderBy('id')
			);
		}
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('measurement_unit_translations');
	}
};
