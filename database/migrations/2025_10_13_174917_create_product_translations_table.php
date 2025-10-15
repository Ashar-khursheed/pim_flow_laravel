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
		Schema::dropIfExists('attribute_translations');
		// Schema::create('attribute_translations', function (Blueprint $table) {
		// 	$table->id();
		// 	$table->string("locale", 2);
		// 	$table->integer("attribute_id");
		// 	$table->text("name");
		// });
		// $records = DB::table('attributes')->select('id', 'name')->get();
		// foreach ($records as $record) {
		// 	DB::table('attribute_translations')->insert([
		// 		'locale' => 'en',
		// 		'attribute_id' => $record->id,
		// 		'name' => $record->name,
		// 	]);
		// }


		Schema::dropIfExists('attribute_value_translations');
		// Schema::create('attribute_value_translations', function (Blueprint $table) {
		// 	$table->id();
		// 	$table->string("locale", 2);
		// 	$table->integer("attribute_value_id");
		// 	$table->text("attribute_value");
		// });
		// $records = DB::table('attribute_values')->select('id', 'attribute_value')->get();
		// foreach ($records as $record) {
		// 	DB::table('attribute_value_translations')->insert([
		// 		'locale' => 'en',
		// 		'attribute_value_id' => $record->id,
		// 		'attribute_value' => $record->attribute_value,
		// 	]);
		// }


		Schema::dropIfExists('product_attribute_translations');
		// Schema::create('product_attribute_translations', function (Blueprint $table) {
		// 	$table->id();
		// 	$table->string("locale", 2);
		// 	$table->integer("product_attribute_id");
		// 	$table->text("attribute_value");
		// });
		// $records = DB::table('product_attributes')->select('id', 'attribute_value')->get();
		// foreach ($records as $record) {
		// 	DB::table('product_attribute_translations')->insert([
		// 		'locale' => 'en',
		// 		'product_attribute_id' => $record->id,
		// 		'attribute_value' => $record->attribute_value,
		// 	]);
		// }

		Schema::dropIfExists('product_translations');
		Schema::create('product_translations', function (Blueprint $table) {
			$table->id();
			$table->string('locale', 2);
			$table->integer('product_id');
			$table->text('name');
			$table->longText('description')->nullable();
			$table->longText('benefits_features')->nullable();
			$table->text('images')->nullable();
		});
		$records = DB::table('ec_products')->select('id', 'name', 'description', 'benefits_features', 'images')->get();
		foreach ($records as $record) {
			DB::table('product_translations')->insert([
				'locale' => 'en',
				'product_id' => $record->id,
				'name' => $record->name,
				'description' => $record->description,
				'benefits_features' => $record->benefits_features,
				'images' => $record->images,
			]);
		}

		Schema::dropIfExists('faq_translations');
		Schema::create('faq_translations', function (Blueprint $table) {
			$table->id();
			$table->string('locale', 2);
			$table->integer('faq_id');
			$table->longText('question')->nullable();
			$table->longText('answer')->nullable();
		});
		$records = DB::table('faqs')->select('id', 'question', 'answer')->get();
		foreach ($records as $record) {
			DB::table('faq_translations')->insert([
				'locale' => 'en',
				'faq_id' => $record->id,
				'question' => $record->question,
				'answer' => $record->answer,
			]);
		}
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('attribute_translations');
		Schema::dropIfExists('attribute_value_translations');
		Schema::dropIfExists('product_attribute_translations');
		Schema::dropIfExists('product_translations');
	}
};
