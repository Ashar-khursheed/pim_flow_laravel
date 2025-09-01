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
         Schema::table('alternate_products', function (Blueprint $table) { 
			$table->string('order')->nullable()->after('similarity');
			$table->string('status')->nullable()->after('order');
			$table->integer('created_by')->after('status');
			$table->integer('updated_by')->after('created_by');
			$table->integer('rejected_by')->after('updated_by');
			$table->string('reason')->nullable()->after('rejected_by');          
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('alternate_products', function (Blueprint $table) {
            $table->string('order')->nullable()->change();
            $table->string('status')->nullable()->change();
            $table->string('created_by')->nullable()->change();
            $table->string('updated_by')->nullable()->change();
            $table->string('rejected_by')->nullable()->change();
            $table->string('reason')->nullable()->change();

        });
    }
};
