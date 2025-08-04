<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('payments_management', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id'); // or just bigInteger if you prefer
            $table->string('transaction_id')->unique();
            $table->enum('payment_mode', [
                'Cash on Delivery', 'Credit Card', 'Debit Card',
                'Bank Transfer', 'Tabby', 'Tamara', 'Cheque'
            ]);
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['Pending', 'Completed', 'Failed', 'Refunded'])->default('Pending');
            $table->timestamp('payment_date')->nullable();
            $table->text('notes')->nullable();
            $table->text('payment_details')->nullable(); // Store gateway response
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('payments');
    }
};