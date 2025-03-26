<?php
// database/migrations/2025_03_24_create_seo_details_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSeoDetailsTable extends Migration
{
	public function up()
	{
		Schema::create('seo_details', function (Blueprint $table) {
			$table->id();
			$table->foreignId('product_id')->nullable()->constrained('ec_products')->onDelete('cascade');
			$table->enum('type', ['product', 'category', 'page', 'blog']);
			$table->string('primary_keyword');
			$table->integer('primary_keyword_search_volume');
			$table->json('secondary_keywords');
			$table->json('secondary_keywords_search_volume');
			$table->string('url');
			$table->string('title_tag');
			$table->string('meta_title');
			$table->string('meta_description');
			$table->string('og_title');
			$table->string('og_description');
			$table->string('og_image')->nullable();
			$table->string('canonical_tag')->nullable();
			$table->text('schema');
			$table->text('internal_links')->nullable();
			$table->boolean('indexing')->default(true);
			$table->timestamps();
		});
	}

	public function down()
	{
		Schema::dropIfExists('seo_details');
	}
}
