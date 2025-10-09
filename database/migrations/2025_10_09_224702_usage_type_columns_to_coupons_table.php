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
        Schema::table('coupons', function (Blueprint $table) {
            DB::statement("
            ALTER TABLE coupons 
            MODIFY usage_type ENUM(
                'once',
                'multiple',
                'unlimited'				
            ) NOT NULL
        ");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->enum('coupons', [
                'once',
                'multiple',
            ])->default('once')->change();
        });
    }
};
