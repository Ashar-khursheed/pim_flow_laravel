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
		Schema::table('orders', function (Blueprint $table) {
			$table->boolean('pay_with_cheque')->default(0)->after('additional_amount_details');
			$table->decimal('cheque_discount_percentage', 10, 2)->default(0)->after('pay_with_cheque');
			$table->decimal('cheque_discount', 10, 2)->default(0)->after('cheque_discount_percentage');
			$table->text('cheque_img')->nullable()->after('cheque_discount');
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::table('orders', function (Blueprint $table) {
			$table->dropColumn([
				'pay_with_cheque',
				'cheque_discount_percentage',
				'cheque_discount',
				'cheque_img'
			]);
		});
	}
};