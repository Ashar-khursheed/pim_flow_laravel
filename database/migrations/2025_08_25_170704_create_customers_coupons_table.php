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
        Schema::create('coupon_customers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coupon_id');
            $table->unsignedBigInteger('customer_id');
            $table->integer('usage_count')->default(0);
            $table->timestamps();
            
            $table->unsignedBigInteger('coupon_id')->references('id')->on('coupons')->onDelete('cascade');
            $table->unsignedBigInteger('customer_id')->references('id')->on('users')->onDelete('cascade');
            
            $table->unique(['coupon_id', 'customer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupon_customers');
    }
};