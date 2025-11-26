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
        Schema::table('finances', function (Blueprint $table) {
            $table->string('accountsPayableEmail')->nullable()->after('approvalBy');
            $table->string('accountsPayablePhone')->nullable()->after('accountsPayableEmail');
            $table->unsignedBigInteger('customer_address_id')->nullable()->after('accountsPayablePhone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('finances', function (Blueprint $table) {
            $table->dropColumn([
            'accountsPayableEmail',
            'accountsPayablePhone',
            'customer_address_id'
            ]);
        });
    }
};
