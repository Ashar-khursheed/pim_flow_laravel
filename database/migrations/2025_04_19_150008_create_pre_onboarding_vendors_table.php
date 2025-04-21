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
		Schema::create('pre_onboarding_vendors', function (Blueprint $table) {
			$table->id();
			$table->string('name')->index();
			$table->string('contact_person')->index();
			$table->string('email')->index();
			$table->string('phone_number');
			$table->integer('country_id')->index();
			$table->string('account_number')->nullable();
			$table->text('city_ids')->nullable();
			$table->text('zipcode_ids')->nullable();
			$table->text('category_ids')->nullable();
			$table->enum('type', ['direct', 'indirect'])->default('indirect');
			$table->boolean('dropshipping')->default(0);
			$table->string('shipping_days')->nullable();
			$table->string('credit_limit')->nullable();
			$table->string('credit_terms')->nullable();
			$table->integer('score')->nullable();
			$table->string('grade')->nullable();
			$table->integer('created_by');
			$table->integer('updated_by')->nullable();
			$table->longText('product_demand_level')->nullable();
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('pre_onboarding_vendors');
	}
};
