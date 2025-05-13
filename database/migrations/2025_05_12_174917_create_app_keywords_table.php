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
		Schema::create('app_keywords', function (Blueprint $table) {
			$table->id();
			$table->string("code")->nullable();
			$table->integer('created_by');
			$table->integer('updated_by')->nullable();
			$table->timestamps();
		});

		Schema::create('app_keyword_translations', function (Blueprint $table) {
			$table->id();
			$table->string("locale", 2);
			$table->integer("app_keyword_id");
			$table->longText("title");
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('app_keywords');
		Schema::dropIfExists('app_keyword_translations');
	}
};
