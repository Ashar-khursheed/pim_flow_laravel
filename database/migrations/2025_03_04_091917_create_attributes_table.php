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
		Schema::create('attributes', function (Blueprint $table) {
			$table->id();
			$table->string('name')->index();
			$table->string('code')->index();
			$table->string('type');
			$table->longText('validations')->nullable();
			$table->timestamps();
		});

		Schema::create('attribute_values', function (Blueprint $table) {
			$table->id();
			$table->integer('attribute_id')->index();
			$table->longText('attribute_value');
			$table->timestamps();
		});

		Schema::create('product_attributes', function (Blueprint $table) {
			$table->id();
			$table->integer('product_id')->index();
			$table->integer('attribute_id')->index();
			$table->longText('attribute_value');
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('attributes');
		Schema::dropIfExists('attribute_values');
		Schema::dropIfExists('product_attributes');
	}
};
