<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('brand_temp_2', function (Blueprint $table) {
            $table->unsignedBigInteger('brand_id')->nullable()->after('id');
        });
    }

    public function down()
    {
        Schema::table('brand_temp_2', function (Blueprint $table) {
            $table->dropColumn('brand_id');
        });
    }
};
