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
		Schema::dropIfExists('product_attribute_translations');
		Schema::create('product_attribute_translations', function (Blueprint $table) {
			$table->id();
			$table->string("locale", 2);
			$table->integer("product_attribute_id");
			$table->longText("attribute_value_tr");
		});

		if (in_array(config('app.website'), ['UAE', 'UAE_T', 'SA'])) {
			/* Direct SQL insert for product attributes */
			DB::table('product_attribute_translations')->insertUsing(
				['locale', 'product_attribute_id', 'attribute_value_tr'],
				DB::table('product_attributes')
				->select(
					DB::raw("'en' as locale"),
					'id as product_attribute_id',
					'attribute_value as attribute_value_tr'
				)
			);
		}
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('product_attribute_translations');
	}
};
