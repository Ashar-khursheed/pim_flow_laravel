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
        Schema::create('tql_quotes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('pick_location_type');
            $table->string('drop_location_type');
            $table->dateTime('shipment_date');
            $table->longText('origin')->nullable();
            $table->longText('destination')->nullable;
            $table->longText('pickup_details')->nullable();
            $table->longText('delivery_details')->nullable();
            $table->longText('accessorials');
            $table->dateTime('created_date');
            $table->dateTime('tendered_date')->nullable();
            $table->integer('po_number')->nullable();
            $table->dateTime('expiration_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tql_quotes');
    }
};
