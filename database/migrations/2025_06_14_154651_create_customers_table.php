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
		Schema::dropIfExists('customers');
		Schema::create('customers', function (Blueprint $table) {
			$table->id();
			$table->string('name');
			$table->string('email')->unique();
			$table->string('password');
			$table->timestamp('email_verified_at')->nullable();
			$table->rememberToken();
			$table->string('type')->nullable();
			$table->date('dob')->nullable();
			$table->string('country_code')->nullable();
			$table->string('mobile_number')->nullable();
			$table->text('profile_img')->nullable();
			$table->integer('created_by')->nullable();
			$table->timestamps();
		});

		Schema::dropIfExists('customer_addresses');
		Schema::create('customer_addresses', function (Blueprint $table) {
			$table->id();
			$table->bigInteger('customer_id');
			$table->enum('type', ['home', 'work', 'other'])->default('home');
			$table->integer('city_id')->nullable();
			$table->integer('state_id')->nullable();
			$table->integer('country_id')->nullable();
			$table->text('address')->nullable();
			$table->string('zip_code', 20)->nullable();
			$table->boolean('is_default')->default(false);
			$table->integer('created_by')->nullable();
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('customers');
		Schema::dropIfExists('customer_addresses');
	}
};
