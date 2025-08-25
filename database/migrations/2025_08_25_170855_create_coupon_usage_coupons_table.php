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
        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coupon_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->decimal('order_value', 10, 2);
            $table->decimal('discount_amount', 10, 2);
            $table->dateTime('used_at');
            $table->timestamps();
            
            $table->unsignedBigInteger('coupon_id')->references('id')->on('coupons')->onDelete('cascade');
            $table->unsignedBigInteger('customer_id')->references('id')->on('users')->onDelete('cascade');
            $table->unsignedBigInteger('order_id')->references('id')->on('orders')->onDelete('set null');
            
         
            $table->index(['used_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupon_usages');
    }
};