<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCategoryColumnsToCategoryPagesTable extends Migration
{
    public function up()
    {
        Schema::table('category_pages', function (Blueprint $table) {
            $table->text('top_picks_in_santos')->nullable();
            $table->text('top_deals_from_our_sellers')->nullable();
            $table->text('explore_top_picks')->nullable();
            $table->text('hot_new_releases')->nullable();
            $table->text('products_you_may_also_like')->nullable();
            $table->text('inspired_by_your_browsing_history')->nullable();
        });
    }

    public function down()
    {
        Schema::table('category_pages', function (Blueprint $table) {
            $table->dropColumn([
                'top_picks_in_santos',
                'top_deals_from_our_sellers',
                'explore_top_picks',
                'hot_new_releases',
                'products_you_may_also_like',
                'inspired_by_your_browsing_history',
            ]);
        });
    }
}
