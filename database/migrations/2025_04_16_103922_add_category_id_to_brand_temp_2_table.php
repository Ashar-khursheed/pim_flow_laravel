<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCategoryIdToBrandTemp2Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('brand_temp_2', function (Blueprint $table) {
            $table->text('category_id')->nullable()->after('website_banners_videos_mobile');
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
            $table->dropColumn('category_id'); // Remove the category_id column if we rollback
        });
    }
}
