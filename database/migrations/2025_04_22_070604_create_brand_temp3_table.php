<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('brand_temp_3', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_id')->nullable();
            $table->text('category_id')->nullable();

            $table->text('page_top_banners_desktop')->nullable();
            $table->text('page_top_banners_desktop_file_name')->nullable();
            $table->longText('page_top_banners_desktop_alt_text')->nullable();

            $table->text('page_top_banners_mobile')->nullable();
            $table->text('page_top_banners_mobile_file_name')->nullable();
            $table->longText('page_top_banners_mobile_alt_text')->nullable();

            $table->text('page_middle_banners_desktop')->nullable();
            $table->text('page_middle_banners_desktop_file_name')->nullable();
            $table->longText('page_middle_banners_desktop_alt_text')->nullable();

            $table->text('page_middle_banners_mobile')->nullable();
            $table->text('page_middle_banners_mobile_file_name')->nullable();
            $table->longText('page_middle_banners_mobile_alt_text')->nullable();

            $table->text('website_banners_videos')->nullable();
            $table->longText('website_banners_videos_alt_text')->nullable();
            $table->text('website_banners_videos_file_name')->nullable();

            $table->text('website_banners_videos_mobile')->nullable();
            $table->longText('website_banners_videos_mobile_alt_text')->nullable();
            $table->text('website_banners_videos_mobile_file_name')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brand_temp_3');
    }
};
