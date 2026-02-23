<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
	/**
	 * Run the migrations.
	 */
	public function up(): void
	{
		/* Step 1: Delete records where product_id is NULL */
		DB::table('faqs')->whereNull('product_id')->delete();

		/* Step 2: Rename columns */
		Schema::table('faqs', function (Blueprint $table) {
			$table->renameColumn('category_id', 'relational_type');
			$table->renameColumn('product_id', 'relational_id');
		});

		/* Step 3: Change position and data type */
		Schema::table('faqs', function (Blueprint $table) {
			$table->string('relational_type', 255)->nullable()->after('id')->change();
			$table->integer('relational_id')->nullable()->after('relational_type')->change();
		});

		/* Step 4: Update all records to set relational_type = 'App\Models\Product' */
		DB::table('faqs')->update([
			'relational_type' => 'App\Models\Product'
		]);
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		/* Step 1: Clear relational_type values */
		DB::table('faqs')->update([
			'relational_type' => null
		]);

		/* Step 2: Change relational_type back to integer (position will change during rename) */
		Schema::table('faqs', function (Blueprint $table) {
			$table->integer('relational_type')->nullable()->change();
		});

		/* Step 3: Rename columns back */
		Schema::table('faqs', function (Blueprint $table) {
			$table->renameColumn('relational_type', 'category_id');
			$table->renameColumn('relational_id', 'product_id');
		});
	}
};