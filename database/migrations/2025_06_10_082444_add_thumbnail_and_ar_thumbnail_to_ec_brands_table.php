<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddThumbnailAndArThumbnailToEcBrandsTable extends Migration
{
    public function up()
    {
        Schema::table('ec_brands', function (Blueprint $table) {
            $table->string('thumbnail')->nullable()->after('logo');
            $table->string('ar_thumbnail')->nullable()->after('thumbnail');
        });
    }

    public function down()
    {
        Schema::table('ec_brands', function (Blueprint $table) {
            $table->dropColumn(['thumbnail', 'ar_thumbnail']);
        });
    }
}
