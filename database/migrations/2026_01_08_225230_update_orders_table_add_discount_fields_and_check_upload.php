<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		/* Rename additional_discount column to additional_discount_amount */
		Schema::table('orders', function (Blueprint $table) {
			$table->renameColumn('additional_discount', 'additional_discount_amount');
		});

		Schema::table('orders', function (Blueprint $table) {
			$table->boolean('check_upload_by_customer')->nullable()->after('cheque_img_back');
			$table->string('additional_discount_reason', 255)->nullable()->after('check_upload_by_customer');
			$table->string('additional_discount_type', 255)->nullable()->after('additional_discount_reason');
			$table->decimal('additional_discount_percentage', 10, 2)->nullable()->after('additional_discount_type');
			$table->decimal('additional_discount_amount', 10, 2)->default(0.00)->after('additional_discount_percentage')->change();
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('orders', function (Blueprint $table) {
			/* Drop new columns */
			$table->dropColumn([
				'check_upload_by_customer',
				'additional_discount_reason',
				'additional_discount_type',
				'additional_discount_percentage'
			]);
		});

		/* Rename back to original column name */
		Schema::table('orders', function (Blueprint $table) {
			$table->renameColumn('additional_discount_amount', 'additional_discount');
		});
	}
};