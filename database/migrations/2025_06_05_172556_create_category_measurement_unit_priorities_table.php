<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	public function up(): void
	{
		Schema::create('category_measurement_unit_priorities', function (Blueprint $table) {
			$table->id();

			$table->integer('measurement_type_id');
			$table->integer('category_id');

			$table->integer('measurement_unit_primary_id');
			$table->integer('measurement_unit_secondary_id')->nullable();

			$table->integer('created_by');
			$table->integer('updated_by')->nullable();

			$table->timestamps();
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('category_measurement_unit_priorities');
	}
};
