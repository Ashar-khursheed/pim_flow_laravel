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
        // ------- Modify finances table -------
        Schema::table('finances', function (Blueprint $table) { 
            
            if (Schema::hasColumn('finances', 'dueCreditAmount')) {
                $table->dropColumn('dueCreditAmount');
            }

            if (Schema::hasColumn('finances', 'purchaseAmount')) {
                $table->dropColumn('purchaseAmount');
            }
            $table->decimal('next_due_amt', 12, 2)->nullable();
            $table->decimal('paidAmount', 12, 2)->nullable();
        });

        // ------- Drop old payments table -------
        Schema::dropIfExists('finances_payments');

        // ------- Create new finances_payments table -------
        Schema::create('finances_payments', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('finances_id');
            $table->unsignedBigInteger('customer_id');

            $table->date('due_date')->nullable();
            $table->decimal('due_amount', 12, 2)->nullable();

            $table->date('paid_on_date')->nullable();
            $table->decimal('paid_amount', 12, 2)->nullable();

            $table->decimal('balance', 12, 2)->nullable();

            $table->string('creditTerms')->nullable();
            $table->string('payment_mode')->nullable();

            $table->unsignedBigInteger('paid_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('finances', function (Blueprint $table) {
           $table->dropColumn('purchaseAmount');
        });

        Schema::dropIfExists('finances_payments');
    }
};
