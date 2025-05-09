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
        Schema::create('product_group_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id'); // No foreign key constraint
            $table->unsignedBigInteger('product_id'); // No foreign key constraint
            $table->timestamps();
        });
        
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_group_items');
    }
};
