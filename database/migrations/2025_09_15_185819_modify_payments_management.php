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
		Schema::table('payments_management', function (Blueprint $table) {
            $table->text('payment_img')->nullable()->after('payment_details');  
            $table->string('rider_name')->nullable()->after('payment_img');  
            $table->integer('created_by')->nullable()->after('rider_name');  
            $table->integer('updated_by')->nullable()->after('created_by');  
        });	
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {        	
		Schema::table('payments_management', function (Blueprint $table) {
            $table->dropColumn('payment_img');
            $table->dropColumn('rider_name');
            $table->dropColumn('created_by');  
            $table->dropColumn('updated_by');  
        });
    }
};
