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
		Schema::create('horeca_pages', function (Blueprint $table) {
			$table->id();
			$table->string('name');
			$table->text('description')->nullable();
			$table->string('link_name')->nullable();
			$table->text('link_url')->nullable();
			$table->text('banner_url');
			$table->longText('left_para_description')->nullable();
			$table->longText('right_para_description')->nullable();
			$table->longText('faqs')->nullable();
			$table->boolean('is_active')->default(true);
			$table->integer('created_by');
			$table->integer('updated_by')->nullable();
			$table->timestamps();

			/* Indexes */
			$table->index('is_active');
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('horeca_pages');
	}
};