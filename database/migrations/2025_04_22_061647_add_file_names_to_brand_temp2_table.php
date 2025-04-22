<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFileNamesToBrandTemp2Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('brand_temp_2', function (Blueprint $table) {
            $table->json('page_top_banners_desktop_file_name')->nullable();
            $table->json('page_top_banners_mobile_file_name')->nullable();
            $table->json('category_banners_file_name')->nullable();
            $table->json('page_middle_banners_desktop_file_name')->nullable();
            $table->json('page_middle_banners_mobile_file_name')->nullable();
            $table->json('website_banners_videos_file_name')->nullable();
            $table->json('website_banners_videos_mobile_file_name')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('brand_temp_2', function (Blueprint $table) {
            $table->dropColumn([
                'page_top_banners_desktop_file_name',
                'page_top_banners_mobile_file_name',
                'category_banners_file_name',
                'page_middle_banners_desktop_file_name',
                'page_middle_banners_mobile_file_name',
                'website_banners_videos_file_name',
                'website_banners_videos_mobile_file_name'
            ]);
        });
    }
}