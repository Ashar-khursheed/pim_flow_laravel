<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->timestamp('order_date');
            $table->time('order_time');
            
            // Customer Information
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone');
            
            // Address Information
            $table->string('company')->nullable();
            $table->text('address');
            $table->string('city');
            $table->string('country');
            
            // Order Details
            $table->enum('status', [
                'Pending', 'Confirmed', 'Supplier Delivery', 'International', 
                'Export', 'On hold', 'Ready to ship', 'Pickups', 
                'Out for delivery', 'Delivered', 'Re-Attempt', 'Returned', 'Cancelled'
            ])->default('Pending');
            
            $table->decimal('total_amount', 10, 2);
            $table->integer('total_products');
            
            // Delivery Options
            $table->boolean('ship_all_at_once')->default(true);
            $table->boolean('separate_deliveries')->default(false);
            
            // Payment Status
            $table->boolean('is_paid')->default(false);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->decimal('pending_amount', 10, 2)->default(0);
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('orders');
    }
};
