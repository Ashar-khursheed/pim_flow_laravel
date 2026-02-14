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
		Schema::table('chats', function (Blueprint $table) {
			$table->boolean('is_read')->default(false)->after('created_by_type');
			$table->timestamp('read_at')->nullable()->after('is_read');
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::table('chats', function (Blueprint $table) {
			/* Drop all newly added columns in reverse order */
			$table->dropColumn([
				'is_read',
				'read_at',
			]);
		});
	}
};
