<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddGenTypeToEcProductsTable extends Migration
{
    public function up(): void
    {
        Schema::table('ec_products', function (Blueprint $table) {
            $table->boolean('gen_type')->default(0)->after('id'); // adjust 'after' to your column order
        });
    }

    public function down(): void
    {
        Schema::table('ec_products', function (Blueprint $table) {
            $table->dropColumn('gen_type');
        });
    }
}
