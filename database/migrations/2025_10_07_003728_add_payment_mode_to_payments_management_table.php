<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments_management', function (Blueprint $table) {
            $table->enum('payment_mode', [
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
                'Stax' // new value added
            ])->default('Bank Transfer')->change();
        });
    }

    public function down(): void
    {
        Schema::table('payments_management', function (Blueprint $table) {
            $table->enum('payment_mode', [
                'Bank Transfer',
                'Stripe',
                'Razorpay',
                'Cash on Delivery',
                'CC Avenue',
                'Credit Card',
                'Debit Card',
                'Tabby',
                 'Paymob',
                   'Stax',
                'Cheque',
                'Tamara'
            ])->default('Bank Transfer')->change();
        });
    }
};
