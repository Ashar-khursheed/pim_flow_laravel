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
		Schema::dropIfExists('category_translations');
		Schema::create('category_translations', function (Blueprint $table) {
			$table->id();
			$table->string("locale", 2);
			$table->integer("category_id");
			$table->text("name_tr");
		});

		if (in_array(config('app.website'), ['UAE', 'UAE_T', 'SA'])) {
			/* Direct SQL insert for categories */
			DB::table('category_translations')->insertUsing(
				['locale', 'category_id', 'name_tr'],
				DB::table('categories')
				->select(
					DB::raw("'en' as locale"),
					'id as category_id',
					'name as name_tr'
				)
			);
		}
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('category_translations');
	}
};
