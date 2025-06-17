<?php

// database/migrations/xxxx_xx_xx_create_guest_recently_viewed_products_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGuestRecentlyViewedProductsTable extends Migration
{
    public function up()
    {
        Schema::create('guest_recently_viewed_products', function (Blueprint $table) {
            $table->id();
            $table->string('guest_token');
            $table->unsignedBigInteger('product_id'); // No foreign key constraint
            $table->timestamps();

            $table->index(['guest_token', 'product_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('guest_recently_viewed_products');
    }
}
