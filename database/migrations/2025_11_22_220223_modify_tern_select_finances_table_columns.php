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
        Schema::table('finances', function (Blueprint $table) {
            if (!Schema::hasColumn('finances', 'creditLimitAmount')) {
                $table->decimal('creditLimitAmount', 12, 2)->nullable();
            }
            if (!Schema::hasColumn('finances', 'approvedAmount')) {
                $table->decimal('approvedAmount', 12, 2)->nullable();
            }
            if (!Schema::hasColumn('finances', 'approvalDate')) {
                $table->date('approvalDate')->nullable();
            }
            if (!Schema::hasColumn('finances', 'approvalBy')) {
                $table->integer('approvalBy')->nullable();
            }
            if (!Schema::hasColumn('finances', 'accountStatus')) {
                $table->enum('accountStatus', ['Active', 'Overdue','Pending'])->default('Pending');
            }
            if (!Schema::hasColumn('finances', 'usedCreditAmount')) {
                $table->decimal('usedCreditAmount', 12, 2)->nullable();
            }
            if (!Schema::hasColumn('finances', 'availableCreditAmount')) {
                $table->decimal('availableCreditAmount', 12, 2)->nullable();
            }
            if (!Schema::hasColumn('finances', 'purchaseAmount')) {
                $table->decimal('purchaseAmount', 12, 2)->nullable();
            }
            if (!Schema::hasColumn('finances', 'dueCreditAmount')) {
                $table->decimal('dueCreditAmount', 12, 2)->nullable();
            }
            if (!Schema::hasColumn('finances', 'payment_mode')) {
                $table->string('payment_mode')->nullable();
            }
            if (!Schema::hasColumn('finances', 'next_due_date')) {
                $table->date('next_due_date')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('finances', function (Blueprint $table) {
            $table->dropColumn([
                'creditLimitAmount', 'approvedAmount', 'approvalDate', 'approvalBy',
                'accountStatus', 'usedCreditAmount', 'availableCreditAmount',
                'purchaseAmount', 'dueCreditAmount', 'payment_mode', 'next_due_date'
            ]);
        });
    }
};
