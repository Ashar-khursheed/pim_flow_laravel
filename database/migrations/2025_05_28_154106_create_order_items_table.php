<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('item_id');
            $table->string('name');
            $table->integer('quantity');
            $table->integer('shipped_quantity')->default(0);
            $table->integer('remaining_quantity');
            $table->string('supplier');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_amount', 10, 2);
            $table->string('image_url')->nullable();
            $table->enum('status', [
                'Pending', 'Confirmed', 'Supplier Delivery', 'International', 
                'Export', 'On hold', 'Ready to ship', 'Pickups', 
                'Out for delivery', 'Delivered', 'Re-Attempt', 'Returned', 
                'Cancelled', 'Out of Stock'
            ])->default('Pending');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('order_items');
    }
};
