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
             $table->enum('status', [
                'Pending',
                'Paid',
                'Failed',
                'Cancelled',
                'Overdue'
            ])->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('finances_payments', function (Blueprint $table) {
            $table->enum('status', [
                'Pending',
                'Paid'               
            ])->change();
        });
    }
};
