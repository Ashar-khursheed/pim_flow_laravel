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
        Schema::table('customer_carts', function (Blueprint $table) {
            $table->string('additional_amount_name')->nullable()->after('total_amount');
            $table->decimal('additional_amount_price', 10, 2)->nullable()->after('additional_amount_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_carts', function (Blueprint $table) {
            $table->dropColumn(['additional_amount_name', 'additional_amount_price']);
        });
    }
};
