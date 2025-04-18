<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('ccavenue_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('order_id')->unique();
            $table->string('tracking_id')->nullable();
            $table->string('bank_ref_no')->nullable();
            $table->string('order_status');
            $table->string('payment_mode')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('currency')->default('INR');
            $table->json('raw_response'); // full response
            $table->timestamps();
        });
    }
    

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ccavenue_transactions');
    }
};
