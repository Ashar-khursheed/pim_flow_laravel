// Migration: create_order_tracking_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('order_tracking', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id'); // or just bigInteger if you prefer
            $table->unsignedBigInteger('shipment_id')->nullable();
            $table->string('status');
            $table->text('description');
            $table->string('location')->nullable();
            $table->timestamp('tracked_at');
            $table->text('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('order_tracking');
    }
};
