<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCatDescToSeoManagementTable extends Migration
{
    public function up()
    {
        Schema::table('seo_management', function (Blueprint $table) {
            $table->text('cat_desc')->nullable(); // Replace 'existing_column_name' if you want to control the position
        });
    }

    public function down()
    {
        Schema::table('seo_management', function (Blueprint $table) {
            $table->dropColumn('cat_desc');
        });
    }
}
