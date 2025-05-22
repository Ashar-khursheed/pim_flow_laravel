<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductYouMayLikeItemsTable extends Migration
{
    public function up(): void
    {
        Schema::create('product_you_may_like_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_you_may_like_id');
            $table->unsignedBigInteger('product_id'); // Suggested product
            $table->float('priority')->default(0);
            $table->float('similarity')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_you_may_like_items');
    }
}
