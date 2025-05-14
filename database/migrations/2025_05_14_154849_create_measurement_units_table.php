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
		Schema::create('measurement_types', function (Blueprint $table) {
			$table->id();
			$table->string('name')->unique()->index();
			$table->timestamps();
		});

		Schema::create('measurement_units', function (Blueprint $table) {
			$table->id();
			$table->integer('measurement_type_id')->index();
			$table->string('name')->index();
			$table->string('symbol')->index();
			$table->integer('created_by');
			$table->timestamps();

			$table->unique(['measurement_type_id', 'symbol']);
		});

		Schema::create('attribute_measurements', function (Blueprint $table) {
			$table->id();
			$table->string('attribute_id')->index();
			$table->string('measurement_unit_id')->index();
		});

		Schema::table('product_attributes', function (Blueprint $table) {
			if (!Schema::hasColumn('product_attributes', 'measurement_unit')) {
				$table->string('measurement_unit')->nullable()->after('attribute_value');
			}
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('measurement_types');
		Schema::dropIfExists('measurement_units');
		Schema::dropIfExists('attribute_measurements');

		Schema::table('product_attributes', function (Blueprint $table) {
			if (Schema::hasColumn('product_attributes', 'measurement_unit')) {
				$table->dropColumn('measurement_unit');
			}
		});
	}
};
