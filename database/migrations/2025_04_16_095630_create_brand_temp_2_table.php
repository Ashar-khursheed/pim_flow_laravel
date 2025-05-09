<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('brand_temp_2', function (Blueprint $table) {
            $table->id();

            $table->longText('page_top_banners_desktop')->nullable();
            $table->longText('page_top_banners_mobile')->nullable();
            $table->longText('category_banners')->nullable(); // category_ids + product_ids
            $table->longText('page_middle_banners_desktop')->nullable();
            $table->longText('page_middle_banners_mobile')->nullable();
            $table->longText('website_banners_videos')->nullable(); // image/video (file + type)
            $table->longText('website_banners_videos_mobile')->nullable();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('brand_temp_2');
    }
};
