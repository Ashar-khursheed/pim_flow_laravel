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
		Schema::dropIfExists('attribute_group_translations');
		Schema::dropIfExists('attribute_translations');
		Schema::dropIfExists('attribute_value_translations');
		Schema::dropIfExists('product_attribute_translations');

		Schema::create('attribute_group_translations', function (Blueprint $table) {
			$table->id();
			$table->string("locale", 2);
			$table->integer("attribute_group_id");
			$table->text("name_tr");
		});

		Schema::create('attribute_translations', function (Blueprint $table) {
			$table->id();
			$table->string("locale", 2);
			$table->integer("attribute_id");
			$table->text("name_tr");
		});

		Schema::create('attribute_value_translations', function (Blueprint $table) {
			$table->id();
			$table->string("locale", 2);
			$table->integer("attribute_value_id");
			$table->text("attribute_value_tr");
		});

		Schema::create('product_attribute_translations', function (Blueprint $table) {
			$table->id();
			$table->string("locale", 2);
			$table->integer("product_attribute_id");
			$table->text("attribute_value_tr");
		});

		if (in_array(config('app.website'), ['UAE', 'UAE_T', 'SA'])) {
			$records = DB::table('attribute_groups')->select('id', 'name')->get();
			foreach ($records as $record) {
				DB::table('attribute_group_translations')->insert([
					'locale' => 'en',
					'attribute_group_id' => $record->id,
					'name_tr' => $record->name,
				]);
			}
			$records = DB::table('attributes')->select('id', 'name')->get();
			foreach ($records as $record) {
				DB::table('attribute_translations')->insert([
					'locale' => 'en',
					'attribute_id' => $record->id,
					'name_tr' => $record->name,
				]);
			}

			$records = DB::table('attribute_values')->select('id', 'attribute_value')->get();
			foreach ($records as $record) {
				DB::table('attribute_value_translations')->insert([
					'locale' => 'en',
					'attribute_value_id' => $record->id,
					'attribute_value_tr' => $record->attribute_value,
				]);
			}

			$records = DB::table('product_attributes')->select('id', 'attribute_value')->get();
			foreach ($records as $record) {
				DB::table('product_attribute_translations')->insert([
					'locale' => 'en',
					'product_attribute_id' => $record->id,
					'attribute_value_tr' => $record->attribute_value,
				]);
			}
		}
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('attribute_group_translations');
		Schema::dropIfExists('attribute_translations');
		Schema::dropIfExists('attribute_value_translations');
		Schema::dropIfExists('product_attribute_translations');
	}
};
