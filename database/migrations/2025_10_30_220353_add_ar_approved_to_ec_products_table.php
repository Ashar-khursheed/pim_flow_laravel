<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ec_products', function (Blueprint $table) {
            // Add the ar_approved flag with default value 0 (not approved)
            $table->integer('ar_approved')->default(0)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('ec_products', function (Blueprint $table) {
            $table->dropColumn('ar_approved');
        });
    }
};
