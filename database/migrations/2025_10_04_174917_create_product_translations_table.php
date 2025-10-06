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
		Schema::create('product_translations', function (Blueprint $table) {
			$table->id();
			$table->string("locale", 2);
			$table->integer("product_id");
			$table->text("name");
			$table->longText("description")->nullable();
			$table->longText("benefits_features")->nullable();
			$table->text("images")->nullable();
		});
	}
};
