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
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = 'finances';

        // Numeric and date columns
        $columns = [
            'creditLimitAmount' => "decimal(12,2) NULL",
            'approvedAmount' => "decimal(12,2) NULL",
            'approvalDate' => "date NULL",
            'approvalBy' => "int NULL",
            'usedCreditAmount' => "decimal(12,2) NULL",
            'availableCreditAmount' => "decimal(12,2) NULL",
            'purchaseAmount' => "decimal(12,2) NULL",
            'dueCreditAmount' => "decimal(12,2) NULL",
            'payment_mode' => "varchar(255) NULL",
            'next_due_date' => "date NULL",
        ];

        foreach ($columns as $name => $type) {
            if (!Schema::hasColumn($tableName, $name)) {
                DB::statement("ALTER TABLE $tableName ADD $name $type");
            }
        }

        // ENUM column handled separately
        if (!Schema::hasColumn($tableName, 'accountStatus')) {
            DB::statement("ALTER TABLE $tableName ADD accountStatus ENUM('Active','Overdue','Pending') NOT NULL DEFAULT 'Pending'");
        }
    }

    public function down(): void
    {
        $tableName = 'finances';

        $columns = [
            'creditLimitAmount', 'approvedAmount', 'approvalDate', 'approvalBy',
            'usedCreditAmount', 'availableCreditAmount', 'purchaseAmount',
            'dueCreditAmount', 'payment_mode', 'next_due_date', 'accountStatus'
        ];

        foreach ($columns as $column) {
            if (Schema::hasColumn($tableName, $column)) {
                DB::statement("ALTER TABLE $tableName DROP COLUMN $column");
            }
        }
    }
};
