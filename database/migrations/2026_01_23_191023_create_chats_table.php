<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create('chats', function (Blueprint $table) {
			$table->id();
			$table->integer('chatbot_contact_id');
			$table->longText('message');
			$table->integer('created_by')->default(0);
			$table->enum('created_by_type', ['user', 'customer', 'AI'])->default('customer');
			$table->timestamps();
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('chats');
	}
};