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
		Schema::dropIfExists('product_translations');
		Schema::create('product_translations', function (Blueprint $table) {
			$table->id();
			$table->string('locale', 2);
			$table->integer('product_id');
			$table->text('name_tr')->nullable();
			$table->longText('description_tr')->nullable();
			$table->longText('benefits_features_tr')->nullable();
			$table->text('images_tr')->nullable();
		});

		Schema::dropIfExists('faq_translations');
		Schema::create('faq_translations', function (Blueprint $table) {
			$table->id();
			$table->string('locale', 2);
			$table->integer('faq_id');
			$table->longText('question_tr')->nullable();
			$table->longText('answer_tr')->nullable();
		});

		if (in_array(config('app.website'), ['UAE', 'UAE_T', 'SA'])) {
			/* Direct SQL insert for products - fastest and most memory efficient */
			DB::table('product_translations')->insertUsing(
				['locale', 'product_id', 'name_tr', 'description_tr', 'benefits_features_tr', 'images_tr'],
				DB::table('ec_products')
				->select(
					DB::raw("'en' as locale"),
					'id as product_id',
					'name as name_tr',
					'description as description_tr',
					'benefits_features as benefits_features_tr',
					'images as images_tr'
				)
			);

			/* Direct SQL insert for FAQs - fastest and most memory efficient */
			DB::table('faq_translations')->insertUsing(
				['locale', 'faq_id', 'question_tr', 'answer_tr'],
				DB::table('faqs')
				->select(
					DB::raw("'en' as locale"),
					'id as faq_id',
					'question as question_tr',
					'answer as answer_tr'
				)
			);
		}
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('product_translations');
		Schema::dropIfExists('faq_translations');
	}
};
