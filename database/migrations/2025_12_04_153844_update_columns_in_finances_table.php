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
           
            $table->string('legal_business_name')->nullable();
            $table->string('doing_business')->nullable();
            $table->string('business_address')->nullable();
            $table->string('role_at_business')->nullable();
            $table->string('last_name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('email')->nullable();
            $table->string('country')->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->string('zipcode')->nullable();
            $table->text('rejection_reason')->nullable();
            
            $table->renameColumn('creditLimitAmount', 'credit_limit_amount');
            $table->renameColumn('requestedAmount', 'requested_amount');
            $table->renameColumn('accountsPayableEmail', 'accounts_payable_email');
            $table->renameColumn('accountsPayablePhone', 'accounts_payable_phone');
            $table->renameColumn('approvedAmount', 'approved_amount');
            $table->renameColumn('approvalDate', 'approval_date');
            $table->renameColumn('usedCreditAmount', 'used_credit_amount');
            $table->renameColumn('availableCreditAmount', 'available_credit_amount');
            $table->renameColumn('paidAmount', 'paid_amount');
            $table->renameColumn('accountsStatus', 'accounts_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('finances', function (Blueprint $table) {
            $table->dropColumn([
                'legal_business_name',
                'doing_business',
                'business_address',
                'role_at_business',
            ]);

            // Reverse renamed columns
            $table->renameColumn('credit_limit_amount', 'creditLimitAmount');
            $table->renameColumn('requested_amount', 'requestedAmount');
            $table->renameColumn('accounts_payable_email', 'accountsPayableEmail');
            $table->renameColumn('accounts_payable_phone', 'accountsPayablePhone');
            $table->renameColumn('approved_amount', 'approvedAmount');
            $table->renameColumn('approval_date', 'approvalDate');
            $table->renameColumn('used_credit_amount', 'usedCreditAmount');
            $table->renameColumn('available_credit_amount', 'availableCreditAmount');
            $table->renameColumn('paid_amount', 'paidAmount');
            $table->renameColumn('accounts_status', 'accountsStatus');
        });
    }
};
