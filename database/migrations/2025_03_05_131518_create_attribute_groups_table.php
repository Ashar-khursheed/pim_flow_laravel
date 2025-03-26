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
		Schema::create('attribute_groups', function (Blueprint $table) {
			$table->id();
			$table->string('name')->index();
			$table->timestamps();
		});

		Schema::create('attribute_group_attributes', function (Blueprint $table) {
			$table->id();
			$table->integer('attribute_group_id');
			$table->integer('attribute_id');
			$table->timestamps();
		});

		Schema::create('attribute_group_categories', function (Blueprint $table) {
			$table->id();
			$table->integer('category_id');
			$table->integer('relational_id')->index();
			$table->string('relational_type')->index();
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('attribute_groups');
		Schema::dropIfExists('attribute_group_attributes');
		Schema::dropIfExists('attribute_group_categories');
	}
};
