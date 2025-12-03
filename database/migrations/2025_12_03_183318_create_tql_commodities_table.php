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
        Schema::create('tql_commodities', function (Blueprint $table) {
           $table->id();
            $table->unsignedBigInteger('quote_id');
            $table->string('description');
            $table->integer('quantity');
            $table->decimal('weight', 10, 1);
            $table->integer('dimension_length');
            $table->integer('dimension_width');
            $table->integer('dimension_height');
            $table->boolean('is_hazmat')->default(false);
            $table->string('freight_class_code');
            $table->string('unit_type_code');
            $table->string('nmfc')->nullable();
            $table->integer('piece_case_count')->nullable();
            $table->boolean('is_stackable')->default(false);
            $table->longText('hazmat_details')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tql_commodities');
    }
};
