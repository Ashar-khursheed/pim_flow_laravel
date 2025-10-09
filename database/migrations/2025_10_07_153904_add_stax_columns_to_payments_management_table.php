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
        Schema::table('payments_management', function (Blueprint $table) {
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
                'COD',
                'PayPal',
                'Stax',
                'Square'				
            ) NOT NULL
        ");
    
        });
    }

    /**
     * Reverse the migrations.
     */
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
                'Cheque',
                'Tamara',
                'Paymob',
                'COD'
            ])->default('Bank Transfer')->change();
        });
        
    }
};
