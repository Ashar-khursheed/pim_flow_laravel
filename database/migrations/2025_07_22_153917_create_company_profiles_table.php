<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCompanyProfilesTable extends Migration
{
    public function up()
    {
        Schema::create('company_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id'); // No foreign key
            $table->string('business_name');
            $table->string('trade_name')->nullable();
            $table->string('company_reg_no')->nullable();
            $table->string('vat_number')->nullable();
            $table->string('country');
            $table->string('legal_status')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('company_profiles');
    }
}
