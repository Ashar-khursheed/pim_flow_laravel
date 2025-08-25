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
        Schema::create('coupon_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coupon_id');
            $table->unsignedBigInteger('category_id');
            $table->timestamps();
            
            $table->unsignedBigInteger('coupon_id')->references('id')->on('coupons')->onDelete('cascade');
            $table->unsignedBigInteger('category_id')->references('id')->on('categories')->onDelete('cascade');
            
            $table->unique(['coupon_id', 'category_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupon_categories');
    }
};