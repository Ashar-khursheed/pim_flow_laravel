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
			$table->longText('inner_categories')->nullable();
			$table->string('section_title')->nullable();
			$table->text('section_description')->nullable();
			$table->longText('six_images')->nullable();
			$table->longText('four_banners')->nullable();
			$table->string('extra_heading')->nullable();
			$table->text('extra_description')->nullable();
			$table->longText('twelve_images')->nullable();
			$table->longText('related_products')->nullable();
			$table->timestamps();

			$table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
		});
	}

	public function down()
	{
		Schema::table('category_pages', function (Blueprint $table) {
			if (Schema::hasColumn('category_pages', 'category_id')) {
				$table->dropForeign(['category_id']);
			}
		});

		Schema::dropIfExists('category_pages');
	}
};
