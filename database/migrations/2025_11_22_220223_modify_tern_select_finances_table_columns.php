<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Use raw SQL to avoid duplicate column errors
        DB::statement("ALTER TABLE finances ADD COLUMN IF NOT EXISTS creditLimitAmount DECIMAL(12,2) NULL");
        DB::statement("ALTER TABLE finances ADD COLUMN IF NOT EXISTS approvedAmount DECIMAL(12,2) NULL");
        DB::statement("ALTER TABLE finances ADD COLUMN IF NOT EXISTS approvalDate DATE NULL");
        DB::statement("ALTER TABLE finances ADD COLUMN IF NOT EXISTS approvalBy INT NULL");
        DB::statement("ALTER TABLE finances ADD COLUMN IF NOT EXISTS accountStatus ENUM('Active','Overdue','Pending') NOT NULL DEFAULT 'Pending'");
        DB::statement("ALTER TABLE finances ADD COLUMN IF NOT EXISTS usedCreditAmount DECIMAL(12,2) NULL");
        DB::statement("ALTER TABLE finances ADD COLUMN IF NOT EXISTS availableCreditAmount DECIMAL(12,2) NULL");
        DB::statement("ALTER TABLE finances ADD COLUMN IF NOT EXISTS purchaseAmount DECIMAL(12,2) NULL");
        DB::statement("ALTER TABLE finances ADD COLUMN IF NOT EXISTS dueCreditAmount DECIMAL(12,2) NULL");
        DB::statement("ALTER TABLE finances ADD COLUMN IF NOT EXISTS payment_mode VARCHAR(255) NULL");
        DB::statement("ALTER TABLE finances ADD COLUMN IF NOT EXISTS next_due_date DATE NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE finances DROP COLUMN IF EXISTS creditLimitAmount");
        DB::statement("ALTER TABLE finances DROP COLUMN IF EXISTS approvedAmount");
        DB::statement("ALTER TABLE finances DROP COLUMN IF EXISTS approvalDate");
        DB::statement("ALTER TABLE finances DROP COLUMN IF EXISTS approvalBy");
        DB::statement("ALTER TABLE finances DROP COLUMN IF EXISTS accountStatus");
        DB::statement("ALTER TABLE finances DROP COLUMN IF EXISTS usedCreditAmount");
        DB::statement("ALTER TABLE finances DROP COLUMN IF EXISTS availableCreditAmount");
        DB::statement("ALTER TABLE finances DROP COLUMN IF EXISTS purchaseAmount");
        DB::statement("ALTER TABLE finances DROP COLUMN IF EXISTS dueCreditAmount");
        DB::statement("ALTER TABLE finances DROP COLUMN IF EXISTS payment_mode");
        DB::statement("ALTER TABLE finances DROP COLUMN IF EXISTS next_due_date");
    }
};
