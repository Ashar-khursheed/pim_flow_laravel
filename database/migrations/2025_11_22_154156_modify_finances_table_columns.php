<?php

// use Illuminate\Database\Migrations\Migration;
// use Illuminate\Database\Schema\Blueprint;
// use Illuminate\Support\Facades\Schema;

// return new class extends Migration
// {
//     /**
//      * Run the migrations.
//      */
//     public function up(): void
//     {         
//         Schema::table('finances', function (Blueprint $table) {         
//         $table->decimal('creditLimitAmount', 12, 2)->nullable();
//         $table->decimal('approvedAmount', 12, 2)->nullable();
//         $table->date('approvalDate')->nullable();
//         $table->integer('approvalBy')->nullable();        
//         $table->enum('accountStatus', ['Active', 'Overdue','Pending'])->default('Pending');
//         $table->decimal('usedCreditAmount', 12, 2)->nullable();
//         $table->decimal('availableCreditAmount', 12, 2)->nullable();
//         $table->decimal('purchaseAmount', 12, 2)->nullable();
//         $table->decimal('dueCreditAmount', 12, 2)->nullable();
//         $table->string('payment_mode')->nullable();         
//         $table->date('next_due_date')->nullable();       
        
//     });
//     }

//     /**
//      * Reverse the migrations.
//      */
//     public function down(): void
//     {
//          Schema::table('finances', function (Blueprint $table) {       
//             $table->dropColumn(['creditLimitAmount', 'approvedAmount','approvalDate','approvalBy','accountStatus','usedCreditAmount','availableCreditAmount','purchaseAmount','dueCreditAmount','payment_mode','next_due_date']);
//         });
//     }
// };


use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $columns = [
            "creditLimitAmount DECIMAL(12,2) NULL",
            "approvedAmount DECIMAL(12,2) NULL",
            "approvalDate DATE NULL",
            "approvalBy INT NULL",
            "accountStatus ENUM('Active','Overdue','Pending') NOT NULL DEFAULT 'Pending'",
            "usedCreditAmount DECIMAL(12,2) NULL",
            "availableCreditAmount DECIMAL(12,2) NULL",
            "purchaseAmount DECIMAL(12,2) NULL",
            "dueCreditAmount DECIMAL(12,2) NULL",
            "payment_mode VARCHAR(255) NULL",
            "next_due_date DATE NULL",
        ];

        foreach ($columns as $column) {
            DB::statement("ALTER TABLE finances ADD COLUMN IF NOT EXISTS $column");
        }
    }

    public function down(): void
    {
        $columns = [
            'creditLimitAmount', 'approvedAmount', 'approvalDate', 'approvalBy',
            'accountStatus', 'usedCreditAmount', 'availableCreditAmount',
            'purchaseAmount', 'dueCreditAmount', 'payment_mode', 'next_due_date'
        ];

        foreach ($columns as $column) {
            DB::statement("ALTER TABLE finances DROP COLUMN IF EXISTS $column");
        }
    }
};
