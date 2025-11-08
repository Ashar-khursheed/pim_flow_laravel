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
		Schema::create('attribute_group_translations', function (Blueprint $table) {
			$table->id();
			$table->string("locale", 2);
			$table->integer("attribute_group_id");
			$table->text("name_tr");
		});

		Schema::dropIfExists('attribute_translations');
		Schema::create('attribute_translations', function (Blueprint $table) {
			$table->id();
			$table->string("locale", 2);
			$table->integer("attribute_id");
			$table->text("name_tr");
		});

		Schema::dropIfExists('attribute_value_translations');
		Schema::create('attribute_value_translations', function (Blueprint $table) {
			$table->id();
			$table->string("locale", 2);
			$table->integer("attribute_value_id");
			$table->text("attribute_value_tr");
		});

		Schema::dropIfExists('product_attribute_translations');
		Schema::create('product_attribute_translations', function (Blueprint $table) {
			$table->id();
			$table->string("locale", 2);
			$table->integer("product_attribute_id");
			$table->text("attribute_value_tr");
		});

		if (in_array(config('app.website'), ['UAE', 'UAE_T', 'SA'])) {
			/* Direct SQL insert for attribute groups */
			// DB::table('attribute_group_translations')->insertUsing(
			// 	['locale', 'attribute_group_id', 'name_tr'],
			// 	DB::table('attribute_groups')
			// 	->select(
			// 		DB::raw("'en' as locale"),
			// 		'id as attribute_group_id',
			// 		'name as name_tr'
			// 	)
			// );

			/* Direct SQL insert for attributes */
			DB::table('attribute_translations')->insertUsing(
				['locale', 'attribute_id', 'name_tr'],
				DB::table('attributes')
				->select(
					DB::raw("'en' as locale"),
					'id as attribute_id',
					'name as name_tr'
				)
			);

			/* Direct SQL insert for attribute values */
			DB::table('attribute_value_translations')->insertUsing(
				['locale', 'attribute_value_id', 'attribute_value_tr'],
				DB::table('attribute_values')
				->select(
					DB::raw("'en' as locale"),
					'id as attribute_value_id',
					'attribute_value as attribute_value_tr'
				)
			);

			/* Direct SQL insert for product attributes */
			// DB::table('product_attribute_translations')->insertUsing(
			// 	['locale', 'product_attribute_id', 'attribute_value_tr'],
			// 	DB::table('product_attributes')
			// 	->select(
			// 		DB::raw("'en' as locale"),
			// 		'id as product_attribute_id',
			// 		'attribute_value as attribute_value_tr'
			// 	)
			// );
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
