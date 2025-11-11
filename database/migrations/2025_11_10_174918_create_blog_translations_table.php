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
		Schema::dropIfExists('blog_translations');
		Schema::create('blog_translations', function (Blueprint $table) {
			$table->id();
			$table->string("locale", 2);
			$table->integer("category_id");
			$table->text("name_tr");
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

			/* Direct SQL insert for categories */
			DB::table('category_translations')->insertUsing(
				['locale', 'category_id', 'name_tr'],
				DB::table('categories')
				->select(
					DB::raw("'en' as locale"),
					'id as category_id',
					'name as name_tr'
				)
			);

			/* Direct SQL insert for categories */
			DB::table('brand_translations')->insertUsing(
				['locale', 'brand_id', 'name_tr', 'description_tr'],
				DB::table('ec_brands')
				->select(
					DB::raw("'en' as locale"),
					'id as brand_id',
					'name as name_tr',
					'description as description_tr',
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
		Schema::dropIfExists('category_translations');
		Schema::dropIfExists('brand_translations');
	}
};
