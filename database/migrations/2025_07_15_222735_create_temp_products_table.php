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
		Schema::create('temp_products', function (Blueprint $table) {
			$table->id();
			$table->string('name');
			$table->integer('category_id');
			$table->integer('brand_id');
			$table->integer('vendor_id');
			$table->string('sku')->unique();
			$table->integer('status_id');
			$table->integer('created_by');
			$table->timestamps();
		});

		Schema::create('temp_product_statuses', function (Blueprint $table) {
			$table->id();
			$table->string('name');
			$table->string('code')->unique();
			$table->integer('step_number');
			$table->timestamps();
		});

		Schema::create('temp_product_assignments', function (Blueprint $table) {
			$table->id();
			$table->integer('temp_product_id');
			$table->integer('assigned_to');
			$table->integer('assigned_by');
			$table->date('due_date')->nullable();
			$table->timestamp('created_at')->useCurrent();
			$table->timestamp('completed_at')->nullable();
			$table->enum('status', ['pending', 'in_progress', 'completed', 'rejected'])->default('pending');
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('temp_products');
		Schema::dropIfExists('temp_product_statuses');
		Schema::dropIfExists('temp_product_assignments');
	}
};
