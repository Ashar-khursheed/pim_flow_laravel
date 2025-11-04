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
        Schema::create('finances', function (Blueprint $table) {
            $table->id();
            $table->string('payment_selection')->nullable();
            $table->string('payment_options')->nullable();
            $table->string('term_selection')->nullable(); // Net 30 / 45 / 60 Days
            $table->decimal('amount', 15, 2)->nullable();
            $table->text('documents')->nullable(); // store file path(s)
            $table->date('payment_due')->nullable();
            $table->string('type_of_business')->nullable();
            $table->string('business_name')->nullable();
            $table->text('business_address')->nullable();
            $table->string('country')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('zip')->nullable();
            $table->string('annual_revenue')->nullable();
            $table->string('years_in_business')->nullable(); // Less than 2, etc.
            $table->string('accounts_payable_email')->nullable();
            $table->string('accounts_payable_phone')->nullable();
            $table->string('duns_number')->nullable();
            $table->string('status')->nullable();
            $table->string('created_by')->nullable(); 
            $table->string('updated_by')->nullable(); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finances');
    }
};
