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
       	Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id');
            $table->text('variants')->nullable();
            $table->text('child_ids')->nullable();                       
            $table->enum('status', ['published', 'draft', 'pending'])->default('Pending');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();             
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
