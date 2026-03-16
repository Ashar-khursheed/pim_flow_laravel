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
		Schema::table('customer_carts', function (Blueprint $table) {
			$table->string('additional_amount_name', 255)->nullable()->after('customer_address_id')->change();
			$table->decimal('additional_amount_price', 10, 2)->nullable()->after('additional_amount_name')->change();
			$table->decimal('amount', 10, 2)->after('additional_amount_price')->change();
			$table->boolean('pay_with_cheque')->default(0)->after('amount')->change();
			$table->boolean('is_lift_gate')->nullable()->after('pay_with_cheque')->change();
			$table->boolean('is_residential_address')->nullable()->after('is_lift_gate')->change();
			$table->boolean('is_inside_delivery')->nullable()->after('is_residential_address')->change();
			$table->decimal('tax_percentage', 10, 4)->after('is_inside_delivery')->change();
			$table->decimal('tax_amount', 10, 2)->after('tax_percentage')->change();
		});
	}
	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
	}
};