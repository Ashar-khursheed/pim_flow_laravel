<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableName = 'finances';

        // Numeric, date, and string columns
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = 'finances';

        $columns = [
            'creditLimitAmount', 'approvedAmount', 'approvalDate', 'approvalBy',
            'accountStatus', 'usedCreditAmount', 'availableCreditAmount',
            'purchaseAmount', 'dueCreditAmount', 'payment_mode', 'next_due_date'
        ];

        foreach ($columns as $column) {
            if (Schema::hasColumn($tableName, $column)) {
                DB::statement("ALTER TABLE $tableName DROP COLUMN $column");
            }
        }
    }
};
