<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	public function up()
	{
		Schema::create('category_pages', function (Blueprint $table) {
			$table->id();
			$table->unsignedBigInteger('category_id')->unique();
			$table->string('title');
			$table->text('description')->nullable();
			$table->string('banner_image')->nullable();
			$table->string('banner_link')->nullable();
			$table->json('inner_categories')->nullable(); // Storing positions dynamically
			$table->string('section_title')->nullable();
			$table->text('section_description')->nullable();
			$table->json('six_images')->nullable(); // JSON to store images, alt text, keywords
			$table->json('four_banners')->nullable(); // JSON to store banners
			$table->string('extra_heading')->nullable();
			$table->text('extra_description')->nullable();
			$table->json('twelve_images')->nullable();
			$table->json('related_products')->nullable(); // Store product IDs for "You May Also Like"
			$table->timestamps();

			$table->foreign('category_id')->references('id')->on('ec_product_categories')->onDelete('cascade');
		});
	}

	public function down()
	{
		Schema::table('category_pages', function (Blueprint $table) {
			if (Schema::hasColumn('category_pages', 'category_id')) {
				$table->dropForeign(['category_id']); // Drop foreign key before dropping table
			}
		});

		Schema::dropIfExists('category_pages');
	}
};
