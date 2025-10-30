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
		Schema::dropIfExists('product_attribute_translations');
		Schema::dropIfExists('attribute_value_translations');
		Schema::dropIfExists('product_translations');
		Schema::dropIfExists('faq_translations');
		if (in_array(config('app.website'), ['UAE', 'UAE_T', 'SA'])) {
			Schema::create('product_translations', function (Blueprint $table) {
				$table->id();
				$table->string('locale', 2);
				$table->integer('product_id');
				$table->text('name_tr')->nullable();
				$table->longText('description_tr')->nullable();
				$table->longText('benefits_features_tr')->nullable();
				$table->text('images_tr')->nullable();
			});
			$records = DB::table('ec_products')->select('id', 'name', 'description', 'benefits_features', 'images')->get();
			foreach ($records as $record) {
				DB::table('product_translations')->insert([
					'locale' => 'en',
					'product_id' => $record->id,
					'name_tr' => $record->name,
					'description_tr' => $record->description,
					'benefits_features_tr' => $record->benefits_features,
					'images_tr' => $record->images,
				]);
			}

			Schema::create('faq_translations', function (Blueprint $table) {
				$table->id();
				$table->string('locale', 2);
				$table->integer('faq_id');
				$table->longText('question_tr')->nullable();
				$table->longText('answer_tr')->nullable();
			});
			$records = DB::table('faqs')->select('id', 'question', 'answer')->get();
			foreach ($records as $record) {
				DB::table('faq_translations')->insert([
					'locale' => 'en',
					'faq_id' => $record->id,
					'question_tr' => $record->question,
					'answer_tr' => $record->answer,
				]);
			}
		}
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('attribute_translations');
		Schema::dropIfExists('product_attribute_translations');
		Schema::dropIfExists('attribute_value_translations');
		Schema::dropIfExists('product_translations');
		Schema::dropIfExists('faq_translations');
	}
};
