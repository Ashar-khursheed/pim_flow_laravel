<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	/**
	 * Run the migrations.
	 */
	public function up(): void {
		Schema::dropIfExists('support_tickets');
		Schema::create('support_tickets', function (Blueprint $table) {
			$table->id();
			$table->integer('ticket_number');
			$table->integer('customer_id');
			$table->integer('category_id');
			$table->integer('priority_id');
			$table->string('subject');
			$table->text('description');
			$table->string('reference')->nullable();
			$table->string('file_path')->nullable();
			$table->enum('status', ['open', 'in_progress', '', 'closed'])->default('open');
			$table->integer('response_days');
			$table->integer('created_by');
			$table->integer('updated_by')->nullable();
			$table->timestamps();
		});
	}

	public function down(): void {
		Schema::dropIfExists('support_tickets');
	}
};
