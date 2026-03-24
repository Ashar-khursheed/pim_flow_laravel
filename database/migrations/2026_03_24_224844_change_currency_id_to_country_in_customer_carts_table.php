<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::table('customer_carts', function (Blueprint $table) {
			$table->renameColumn('currency_id', 'country');
		});

		Schema::table('customer_carts', function (Blueprint $table) {
			$table->string('country')->nullable()->change();
		});
	}

	public function down(): void
	{
		Schema::table('customer_carts', function (Blueprint $table) {
			$table->renameColumn('country', 'currency_id');
		});
	}
};