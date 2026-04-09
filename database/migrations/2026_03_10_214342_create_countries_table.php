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
		Schema::rename('countries', 'countries1');
		Schema::create('countries', function (Blueprint $table) {
			$table->id();
			$table->string('name', 191);
			$table->string('phone_code', 10)->nullable();
			$table->string('icon', 255)->nullable();
			$table->integer('currency_id')->nullable();
			$table->decimal('margin', 10, 2)->default(0);
			$table->integer('created_by');
			$table->integer('updated_by')->nullable();
			$table->timestamps();

			/* Indexes for performance */
			$table->index('name');
			$table->index('phone_code');
			$table->index('currency_id');
			$table->index('created_by');
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('countries');
	}
};