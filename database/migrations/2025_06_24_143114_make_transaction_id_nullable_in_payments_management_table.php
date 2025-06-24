<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeTransactionIdNullableInPaymentsManagementTable extends Migration
{
    public function up()
    {
        Schema::table('payments_management', function (Blueprint $table) {
            $table->string('transaction_id')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('payments_management', function (Blueprint $table) {
            $table->string('transaction_id')->nullable(false)->change();
        });
    }
}
