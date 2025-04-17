<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('brand_temp_2', function (Blueprint $table) {
            $table->id();

            $table->json('page_top_banners_desktop')->nullable();
            $table->json('page_top_banners_mobile')->nullable();
            $table->json('category_banners')->nullable(); // category_ids + product_ids
            $table->json('page_middle_banners_desktop')->nullable();
            $table->json('page_middle_banners_mobile')->nullable();
            $table->json('website_banners_videos')->nullable(); // image/video (file + type)
            $table->json('website_banners_videos_mobile')->nullable();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('brand_temp_2');
    }
};
