<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	/* Run the migrations */
	public function up(): void
	{
		Schema::table('product_suppliers', function (Blueprint $table) {
			$table->unsignedTinyInteger('priority')->default(1)->index()->after('warranty_information');
		});

		/* Update priority based on id asc order per product_id */
		DB::statement('
			UPDATE product_suppliers ps
			JOIN (
				SELECT
					id,
					ROW_NUMBER() OVER (PARTITION BY product_id ORDER BY id ASC) AS row_num
				FROM product_suppliers
			) AS ranked ON ps.id = ranked.id
			SET ps.priority = ranked.row_num
		');
	}

	/* Reverse the migrations */
	public function down(): void
	{
		Schema::table('product_suppliers', function (Blueprint $table) {
			$table->dropColumn('priority');
		});
	}
};