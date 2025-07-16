<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBusinessFieldsToCustomersTable extends Migration
{
    public function up()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('business_name')->nullable()->after('name');
            $table->string('business_licence')->nullable()->after('business_name');
            $table->string('trn_number')->nullable()->after('business_licence');
            $table->string('vat_certificate')->nullable()->after('trn_number');
        });
    }

    public function down()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'business_name',
                'business_licence',
                'trn_number',
                'vat_certificate'
            ]);
        });
    }
}
