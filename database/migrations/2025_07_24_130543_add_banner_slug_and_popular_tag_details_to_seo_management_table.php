<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBannerSlugAndPopularTagDetailsToSeoManagementTable extends Migration
{
    public function up()
    {
        Schema::table('seo_management', function (Blueprint $table) {
            $table->string('banner_slug')->nullable(); // Replace existing_column_name with the correct one
            $table->text('popularTag_details')->nullable()->after('banner_slug');
        });
    }

    public function down()
    {
        Schema::table('seo_management', function (Blueprint $table) {
            $table->dropColumn(['banner_slug', 'popularTag_details']);
        });
    }
}
