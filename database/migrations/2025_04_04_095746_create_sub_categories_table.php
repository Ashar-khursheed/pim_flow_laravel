<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSubCategoriesTable extends Migration
{
    public function up()
    {
        // Create subcategories table
        Schema::create('sub_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('category_id'); // Belongs to one category
            $table->json('products_ids')->nullable(); // Store product IDs as JSON
            $table->json('web_banners')->nullable(); // Store web banners as JSON
            $table->json('mobile_banners')->nullable(); // Store mobile banners as JSON
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sub_categories');
    }
}
