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
        $table->decimal('creditLimitAmount', 12, 2)->nullable();
        $table->decimal('approvedAmount', 12, 2)->nullable();
        $table->date('approvalDate')->nullable();
        $table->integer('approvalBy')->nullable();        
        $table->enum('accountStatus', ['Active', 'Overdue','Pending'])->default('Pending');
        $table->decimal('usedCreditAmount', 12, 2)->nullable();
        $table->decimal('availableCreditAmount', 12, 2)->nullable();
        $table->decimal('purchaseAmount', 12, 2)->nullable();
        $table->decimal('dueCreditAmount', 12, 2)->nullable();
        $table->string('payment_mode')->nullable();         
        $table->date('next_due_date')->nullable();       
        
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
         Schema::table('finances', function (Blueprint $table) {       
            $table->dropColumn(['creditLimitAmount', 'approvedAmount','approvalDate','approvalBy','accountStatus','usedCreditAmount','availableCreditAmount','purchaseAmount','dueCreditAmount','payment_mode','next_due_date']);
        });
    }
};
