<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAltTextToBrandTemp2Table extends Migration
{
    public function up()
    {
        Schema::table('brand_temp_2', function (Blueprint $table) {
            $table->longText('page_top_banners_desktop_alt_text')->nullable()->after('page_top_banners_desktop');
            $table->longText('page_top_banners_mobile_alt_text')->nullable()->after('page_top_banners_mobile');
            $table->longText('category_banners_alt_text')->nullable()->after('category_banners');
            $table->longText('page_middle_banners_desktop_alt_text')->nullable()->after('page_middle_banners_desktop');
            $table->longText('page_middle_banners_mobile_alt_text')->nullable()->after('page_middle_banners_mobile');
            $table->longText('website_banners_videos_alt_text')->nullable()->after('website_banners_videos');
            $table->longText('website_banners_videos_mobile_alt_text')->nullable()->after('website_banners_videos_mobile');
        });
    }

    public function down()
    {
        Schema::table('brand_temp_2', function (Blueprint $table) {
            $table->dropColumn([
                'page_top_banners_desktop_alt_text',
                'page_top_banners_mobile_alt_text',
                'category_banners_alt_text',
                'page_middle_banners_desktop_alt_text',
                'page_middle_banners_mobile_alt_text',
                'website_banners_videos_alt_text',
                'website_banners_videos_mobile_alt_text',
            ]);
        });
    }
}
