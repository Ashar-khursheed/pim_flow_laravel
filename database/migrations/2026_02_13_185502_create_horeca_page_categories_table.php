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
		Schema::create('horeca_page_categories', function (Blueprint $table) {
			$table->id();
			$table->integer('horeca_page_id');
			$table->integer('category_id');
			$table->integer('order')->default(0);
			$table->timestamps();

			/* Indexes */
			$table->index('horeca_page_id');
			$table->index('category_id');
			$table->index('order');
			$table->unique(['horeca_page_id', 'category_id']);
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('horeca_page_categories');
	}
};