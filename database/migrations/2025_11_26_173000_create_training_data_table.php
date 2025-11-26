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
       Schema::create('training_data', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('business_name')->nullable();
        $table->string('phone_number')->nullable();
        $table->boolean('quotation')->default(false);
        $table->longText('call_summary')->nullable();
        $table->longText('transcript')->nullable();
        $table->string('type')->nullable();
        $table->boolean('successful')->default(false);
        $table->string('zipcode')->nullable();
        $table->timestamps();
    });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('training_data');
    }
};
