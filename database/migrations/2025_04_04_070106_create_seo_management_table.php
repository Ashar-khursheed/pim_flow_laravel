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
		Schema::create('seo_management', function (Blueprint $table) {
			$table->id();
			$table->integer('relational_id')->index();
			$table->string('relational_type')->index();
			$table->text('url');
			$table->string('primary_keyword');
			$table->integer('monthly_search_volume');
			$table->string('title_tag');
			$table->string('meta_title');
			$table->string('meta_description');
			$table->longText('internal_links')->nullable();
			$table->boolean('indexing')->default(false);
			$table->string('og_title')->nullable();
			$table->string('og_description')->nullable();
			$table->string('og_image_url')->nullable();
			$table->string('og_image_alt_text')->nullable();
			$table->string('og_image_name')->nullable();
			$table->longText('tags')->nullable();
			$table->longText('schema')->nullable();
			$table->integer('schema_rating')->nullable();
			$table->integer('schema_reviews_count')->nullable();
			$table->integer('created_by');
			$table->integer('updated_by')->nullable();
			$table->timestamps();
		});

		Schema::create('seo_secondary_keywords', function (Blueprint $table) {
			$table->id();
			$table->integer('primary_keyword_id');
			$table->string('secondary_keyword');
			$table->integer('monthly_search_volume');
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('seo_management');
		Schema::dropIfExists('seo_secondary_keywords');
	}
};
