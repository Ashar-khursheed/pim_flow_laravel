<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up()
    {
        Schema::table('payments_managment', function (Blueprint $table) {
            $table->string('payment_method')->nullable()->after('id'); // or after any existing column
        });
    }

    public function down()
    {
        Schema::table('payments_managment', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });
    }
};
