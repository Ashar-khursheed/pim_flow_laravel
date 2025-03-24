<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('faqs', function (Blueprint $table) {
            if (!Schema::hasColumn('faqs', 'product_id')) { // Prevent duplicate column errors
                $table->unsignedBigInteger('product_id')->nullable()->after('category_id');
                $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            }
        });
    }

    public function down()
    {
        Schema::table('faqs', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropColumn('product_id');
        });
    }
};
