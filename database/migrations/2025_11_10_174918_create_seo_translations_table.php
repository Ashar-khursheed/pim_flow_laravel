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
		Schema::dropIfExists('seo_management_translations');
		Schema::create('seo_management_translations', function (Blueprint $table) {
			$table->id();
			$table->string("locale", 2);
			$table->integer("seo_management_id");$table->string("primary_keyword_tr");
			$table->string("title_tag_tr")->nullable();
			$table->string("meta_title_tr")->nullable();
			$table->string("meta_description_tr")->nullable();
			$table->string("og_title_tr")->nullable();
			$table->string("og_description_tr")->nullable();
			$table->text("og_image_url_tr")->nullable();
			$table->string("og_image_alt_text_tr")->nullable();
			$table->string("og_image_name_tr")->nullable();
			$table->text("paragraph_1_tr")->nullable();
			$table->text("paragraph_2_tr")->nullable();
			$table->text("paragraph_3_tr")->nullable();
			$table->text("paragraph_4_tr")->nullable();
			$table->text("banner_image_file_tr")->nullable();
			$table->string("banner_image_alt_text_tr")->nullable();
		});

		if (in_array(config('app.website'), ['UAE', 'UAE_T', 'SA'])) {
			/* Direct SQL insert for SEO management translations */
			DB::table('seo_management_translations')->insertUsing(
				[
					'locale',
					'seo_management_id',
					'primary_keyword_tr',
					'title_tag_tr',
					'meta_title_tr',
					'meta_description_tr',
					'og_title_tr',
					'og_description_tr',
					'og_image_url_tr',
					'og_image_alt_text_tr',
					'og_image_name_tr',
					'paragraph_1_tr',
					'paragraph_2_tr',
					'paragraph_3_tr',
					'paragraph_4_tr',
					'banner_image_file_tr',
					'banner_image_alt_text_tr'
				],
				DB::table('seo_management')
				->select(
					DB::raw("'en' as locale"),
					'id as seo_management_id',
					'primary_keyword as primary_keyword_tr',
					'title_tag as title_tag_tr',
					'meta_title as meta_title_tr',
					'meta_description as meta_description_tr',
					'og_title as og_title_tr',
					'og_description as og_description_tr',
					'og_image_url as og_image_url_tr',
					'og_image_alt_text as og_image_alt_text_tr',
					'og_image_name as og_image_name_tr',
					'paragraph_1 as paragraph_1_tr',
					'paragraph_2 as paragraph_2_tr',
					'paragraph_3 as paragraph_3_tr',
					'paragraph_4 as paragraph_4_tr',
					'banner_image_file as banner_image_file_tr',
					'banner_image_alt_text as banner_image_alt_text_tr'
				)
			);
		}
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('seo_management_translations');
	}
};
