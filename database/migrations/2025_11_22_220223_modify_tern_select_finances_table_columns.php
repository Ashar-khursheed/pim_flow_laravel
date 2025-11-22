<?php

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
