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
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedBigInteger('rejectedBy')->nullable()->after('updated_at'); // FK to users/admins who rejected
            $table->timestamp('rejected_date')->nullable()->after('rejectedBy');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['rejectedBy']);
            $table->dropColumn(['rejectedBy', 'rejected_date']);
        });
    }
};
