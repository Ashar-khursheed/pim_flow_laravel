<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up()
{
    Schema::table('customer_cart_products', function (Blueprint $table) {
        $table->text('accessories_options')->nullable()->after('unit_price');
    });
}

public function down()
{
    Schema::table('customer_cart_products', function (Blueprint $table) {
        $table->dropColumn('accessories_options');
    });
}


};
