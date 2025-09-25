<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id');
            $table->unsignedBigInteger('child_id');
            $table->unsignedBigInteger('attribute_id')->nullable();
            $table->string('label')->nullable();
            $table->string('type')->nullable();
            $table->enum('status', ['published', 'draft', 'pending'])->default('Pending');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['parent_id', 'child_id']);
            $table->foreign('parent_id')->references('id')->on('ec_products')->onDelete('cascade');
            $table->foreign('child_id')->references('id')->on('ec_products')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
