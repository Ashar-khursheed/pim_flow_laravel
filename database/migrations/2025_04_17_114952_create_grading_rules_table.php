<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('grading_rules', function (Blueprint $table) {
            $table->id();
            $table->string('grade'); // A, B, C, etc.
            $table->integer('min_percentage');
            $table->integer('max_percentage');
            $table->unsignedBigInteger('product_id');  // Add product_id column without foreign key constraint
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grading_rules');
    }
};
