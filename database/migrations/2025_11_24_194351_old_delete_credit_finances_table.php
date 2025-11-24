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
         
        Schema::dropIfExists('finances');
        Schema::create('finances', function (Blueprint $table) {
        $table->id();
        $table->integer('customer_id');
        $table->string('payment_options')->nullable();
        $table->decimal('requestedAmount', 15, 2)->nullable();
        $table->text('documents')->nullable();
        $table->date('payment_due')->nullable();

        $table->string('type_of_business')->nullable();
        $table->string('annual_revenue')->nullable();
        $table->string('years_in_business')->nullable();
        $table->string('duns_number')->nullable();

        $table->enum('term_selection', ['Net 30 Days', 'Net 45 Days', 'Net 60 Days'])
        ->nullable();

        $table->decimal('creditLimitAmount', 12, 2)->nullable();
        $table->decimal('approvedAmount', 12, 2)->nullable();

        $table->date('approvalDate')->nullable();
        $table->integer('approvalBy')->nullable();

        $table->enum('status', ['Active', 'Overdue', 'Pending'])
        ->default('Pending');

        $table->decimal('usedCreditAmount', 12, 2)->nullable();
        $table->decimal('availableCreditAmount', 12, 2)->nullable();
        $table->decimal('purchaseAmount', 12, 2)->nullable();
        $table->decimal('dueCreditAmount', 12, 2)->nullable();

        $table->string('payment_mode')->nullable();
        $table->date('next_due_date')->nullable();

        $table->unsignedBigInteger('created_by');
        $table->unsignedBigInteger('updated_by')->nullable();

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
