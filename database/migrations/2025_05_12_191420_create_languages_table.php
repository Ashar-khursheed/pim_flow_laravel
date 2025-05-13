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
		Schema::rename('languages', 'languages1');
		Schema::create('languages', function (Blueprint $table) {
			$table->id();
			$table->string('code')->index();
			$table->string('name')->index();
			$table->boolean('rtl')->default(0);
			$table->boolean('isDefault')->default(0);
			$table->integer('created_by');
			$table->integer('updated_by')->nullable();
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('languages');
		Schema::rename('languages1', 'languages');
	}
};
