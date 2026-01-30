<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	/**
	 * Run the migrations.
	 */
	public function up()
	{
		Schema::table('quotes', function (Blueprint $table) {
			$table->boolean('is_lift_gate')->nullable()->after('shipping_charge');
			$table->boolean('is_residential_address')->nullable()->after('is_lift_gate');
			$table->boolean('is_inside_delivery')->nullable()->after('is_residential_address');

			$table->string('additional_amount_name')->nullable()->after('discount');
			$table->decimal('additional_amount_price', 10, 2)->nullable()->after('additional_amount_name');
			$table->text('additional_amount_details')->nullable()->after('additional_amount_price');
			$table->string('additional_discount_reason', 255)->nullable()->after('additional_amount_details');
			$table->string('additional_discount_type', 255)->nullable()->after('additional_discount_reason');
			$table->decimal('additional_discount_percentage', 10, 2)->nullable()->after('additional_discount_type');
			$table->decimal('additional_discount_amount', 10, 2)->default(0.00)->after('additional_discount_percentage');
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down()
	{
		Schema::table('quotes', function (Blueprint $table) {
			/* Drop all newly added columns in reverse order */
			$table->dropColumn([
				'additional_discount_amount',
				'additional_discount_percentage',
				'additional_discount_type',
				'additional_discount_reason',
				'additional_amount_details',
				'additional_amount_price',
				'additional_amount_name',
				'is_inside_delivery',
				'is_residential_address',
				'is_lift_gate'
			]);
		});
	}
};