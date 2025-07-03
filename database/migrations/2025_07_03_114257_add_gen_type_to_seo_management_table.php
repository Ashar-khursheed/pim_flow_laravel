<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddGenTypeToSeoManagementTable extends Migration
{
    public function up()
    {
        Schema::table('seo_management', function (Blueprint $table) {
            $table->tinyInteger('gen_type')->nullable()->default(0)->after('id'); // adjust 'after' if needed
        });
    }

    public function down()
    {
        Schema::table('seo_management', function (Blueprint $table) {
            $table->dropColumn('gen_type');
        });
    }
}
