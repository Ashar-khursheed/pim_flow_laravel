<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('category_pages', function (Blueprint $table) {
            $table->string('banner_image_alt')->nullable()->after('banner_image');
            $table->longText('six_images_alt')->nullable()->after('six_images');
            $table->longText('four_banners_alt')->nullable()->after('four_banners');
            $table->longText('twelve_images_alt')->nullable()->after('twelve_images');
        });
    }

    public function down(): void
    {
        Schema::table('category_pages', function (Blueprint $table) {
            $table->dropColumn([
                'banner_image_alt',
                'six_images_alt',
                'four_banners_alt',
                'twelve_images_alt',
            ]);
        });
    }
};
