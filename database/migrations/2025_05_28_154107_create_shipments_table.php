// Migration: create_shipment_items_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('shipment_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shipment_id')->nullable();
            $table->unsignedBigInteger('order_item_id');
            $table->integer('quantity');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('shipment_items');
    }
};