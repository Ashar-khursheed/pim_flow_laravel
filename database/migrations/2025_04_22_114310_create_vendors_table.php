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
		Schema::create('vendors', function (Blueprint $table) {

			$table->id();
			$table->string('name')->index();
			$table->integer('country_id')->index();
			$table->string('email')->index();
			$table->string('contact_person')->index();
			$table->string('landline_number')->nullable();
			$table->string('mobile_number')->nullable();
			$table->longText('description')->nullable();
			$table->text('website_ids')->nullable();
			$table->text('city_ids')->nullable();
			$table->text('zipcode_ids')->nullable();
			$table->boolean('dropshipping')->default(0);
			$table->string('website_link')->nullable();
			$table->enum('domain', ['Horeca', 'Rapid Supplies'])->nullable();
			$table->enum('type', ['direct', 'indirect'])->nullable();
			$table->text('warehouse_locations')->nullable();
			$table->string('credit_limit')->nullable();
			$table->string('net_terms')->nullable();
			$table->text('logo_url')->nullable();
			$table->text('tax_certificate_url')->nullable();
			$table->string('business_licence_number')->nullable();
			$table->text('business_licence_url')->nullable();
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
		Schema::dropIfExists('vendors');
	}
};
