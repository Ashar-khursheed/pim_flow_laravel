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
		DB::statement('CREATE TABLE users1 LIKE users');
		DB::statement('INSERT INTO users1 SELECT * FROM users');

		Schema::table('users', function (Blueprint $table) {
			$table->dropColumn(['avatar_id', 'super_user', 'manage_supers', 'permissions']);
		});

		/* Modify column order and definition using raw SQL */
		DB::statement("ALTER TABLE `users`
			MODIFY COLUMN `first_name` varchar(255) NULL DEFAULT NULL AFTER `id`,
			MODIFY COLUMN `last_name` varchar(255) NULL DEFAULT NULL AFTER `first_name`,
			MODIFY COLUMN `username` varchar(255) NULL DEFAULT NULL AFTER `last_name`,
			MODIFY COLUMN `password` varchar(255) NULL DEFAULT NULL AFTER `email`
			");

		/* Add new column */
		Schema::table('users', function (Blueprint $table) {
			$table->text('profile_img')->nullable()->after('remember_token');
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		/* Drop the modified users table */
		Schema::dropIfExists('users');

		/* Rename the backup back to users */
		Schema::rename('users1', 'users');
	}
};
