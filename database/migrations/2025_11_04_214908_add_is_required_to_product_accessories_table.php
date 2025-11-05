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
        Schema::table('product_accessories', function (Blueprint $table) {
            $table->boolean('isRequired')
                ->default(false)
                ->after('isapproved');
                
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_accessories', function (Blueprint $table) {
            $table->dropColumn('isRequired');
        });
    }
};
