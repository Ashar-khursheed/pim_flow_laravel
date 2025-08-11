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
		Schema::create('customer_events', function (Blueprint $table) {
			$table->id();
			$table->string('event_type');
			$table->timestamp('event_time');
			$table->text('page');
			$table->string('element')->nullable();
			$table->foreignId('customer_id')->nullable();
			$table->string('session_id')->nullable();
			$table->ipAddress('ip_address')->nullable();
			$table->string('user_agent')->nullable();
			$table->longText('extra_data')->nullable();
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('customer_events');
	}
};
