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
		Schema::dropIfExists('attribute_group_translations');
		Schema::create('attribute_group_translations', function (Blueprint $table) {
			$table->id();
			$table->string("locale", 2);
			$table->integer("attribute_group_id");
			$table->text("name_tr");
		});

		Schema::dropIfExists('attribute_translations');
		Schema::create('attribute_translations', function (Blueprint $table) {
			$table->id();
			$table->string("locale", 2);
			$table->integer("attribute_id");
			$table->text("name_tr");
		});

		Schema::dropIfExists('attribute_value_translations');
		Schema::create('attribute_value_translations', function (Blueprint $table) {
			$table->id();
			$table->string("locale", 2);
			$table->integer("attribute_value_id");
			$table->text("attribute_value_tr");
		});

		Schema::dropIfExists('product_attribute_translations');
		Schema::create('product_attribute_translations', function (Blueprint $table) {
			$table->id();
			$table->string("locale", 2);
			$table->integer("product_attribute_id");
			$table->text("attribute_value_tr");
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('attribute_group_translations');
		Schema::dropIfExists('attribute_translations');
		Schema::dropIfExists('attribute_value_translations');
		Schema::dropIfExists('product_attribute_translations');
	}
};
