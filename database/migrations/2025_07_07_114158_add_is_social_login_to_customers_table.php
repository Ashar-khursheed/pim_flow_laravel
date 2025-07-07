<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('is_social_login')->default(false)->after('password');
            $table->string('apple_id')->nullable()->unique()->after('email');
        });
    }

    public function down()
    {
        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'is_social_login')) {
                $table->dropColumn('is_social_login');
            }

            if (Schema::hasColumn('customers', 'apple_id')) {
                $table->dropColumn('apple_id');
            }
        });
    }
};
