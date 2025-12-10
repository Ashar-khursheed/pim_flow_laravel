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
        Schema::table('finances_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable()->after('finances_id');
            $table->string('order_number')->nullable()->after('order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('finances_payments', function (Blueprint $table) {
             $table->dropColumn(['order_id', 'order_number']);
        });
    }
};
