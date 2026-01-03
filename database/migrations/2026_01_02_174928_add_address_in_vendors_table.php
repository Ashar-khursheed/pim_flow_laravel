<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->text('address')->nullable()->after('website_ids');
            $table->string('zipcode', 20)->nullable()->after('city_ids');

            if (Schema::hasColumn('vendors', 'zipcode_ids')) {
                $table->dropColumn('zipcode_ids');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn('address');
            $table->dropColumn('zipcode');
            $table->string('zipcode_ids')->nullable();
        });
    }
};