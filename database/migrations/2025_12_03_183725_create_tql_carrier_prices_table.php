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
        Schema::create('tql_carrier_prices', function (Blueprint $table) {
           $table->id();
            $table->unsignedBigInteger('quote_id');
            $table->string('carrier');
            $table->string('scac');
            $table->decimal('customer_rate', 10, 2);
            $table->string('carrier_quote_id')->nullable();
            $table->string('service_level');
            $table->string('service_type');
            $table->integer('transit_days');
            $table->decimal('max_liability_new', 10, 2);
            $table->decimal('max_liability_used', 10, 2);
            $table->string('service_level_description');
            $table->longText('price_charges')->nullable();
            $table->boolean('is_preferred');
            $table->boolean('is_economy');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tql_carrier_prices');
    }
};
