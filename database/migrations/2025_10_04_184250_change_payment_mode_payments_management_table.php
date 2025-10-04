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
         DB::statement("
            ALTER TABLE payments_management 
            MODIFY payment_mode ENUM(
                'Bank Transfer',
                'Stripe',
                'Razorpay',
                'Cash on Delivery',
                'CC Avenue',
                'Credit Card',
                'Debit Card',
                'Tabby',
                'Cheque',
                'Tamara',
                'Paymob',
                'COD'
            ) NOT NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
         DB::statement("
            ALTER TABLE payments_management 
            MODIFY payment_mode ENUM(
                'Bank Transfer',
                'Stripe',
                'Razorpay',
                'Cash on Delivery',
                'CC Avenue',
                'Credit Card',
                'Debit Card',
                'Tabby',
                'Cheque',
                'Tamara'
            ) NOT NULL
        ");
    }
};
