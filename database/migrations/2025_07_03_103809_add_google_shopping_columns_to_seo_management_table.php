<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddGoogleShoppingColumnsToSeoManagementTable extends Migration
{
    public function up()
    {
        Schema::table('seo_management', function (Blueprint $table) {
            $table->string('google_shopping_feed_title')->nullable();
            $table->text('google_shopping_feed_description')->nullable();
            $table->string('short_title_variant', 70)->nullable();
        });
    }

    public function down()
    {
        Schema::table('seo_management', function (Blueprint $table) {
            $table->dropColumn([
                'google_shopping_feed_title',
                'google_shopping_feed_description',
                'short_title_variant',
            ]);
        });
    }
}
