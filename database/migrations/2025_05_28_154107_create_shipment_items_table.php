// Migration: create_shipments_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('shipment_number')->unique();
            $table->timestamp('shipment_date');
            $table->string('tracking_number')->nullable();
            $table->string('carrier')->nullable();
            $table->enum('status', [
                'Preparing', 'Shipped', 'In Transit', 'Out for Delivery', 
                'Delivered', 'Failed Delivery', 'Returned'
            ])->default('Preparing');
            $table->decimal('shipping_cost', 10, 2)->default(0);
            $table->timestamp('estimated_delivery')->nullable();
            $table->timestamp('actual_delivery')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('shipments');
    }
};
