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
		Schema::table('quotes', function (Blueprint $table) {
			$table->dropColumn(['discount_percentage', 'discount_amount', 'amount_after_discount']);

			$table->integer('coupon_id')->nullable()->after('tax_amount');
			$table->decimal('discount', 10, 2)->nullable()->after('coupon_id');
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::table('quotes', function (Blueprint $table) {
			$table->dropColumn(['coupon_id', 'discount']);

			$table->decimal('discount_percentage', 10, 2)->nullable()->after('tax_amount');
			$table->decimal('discount_amount', 10, 4)->nullable()->after('discount_percentage');
			$table->decimal('amount_after_discount', 10, 2)->nullable()->after('discount_amount');
		});
	}
};
