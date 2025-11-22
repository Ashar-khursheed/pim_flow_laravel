<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finances', function (Blueprint $table) {
            // Drop term_selection if exists
            if (Schema::hasColumn('finances', 'term_selection')) {
                $table->dropColumn('term_selection');
            }
        });

        Schema::table('finances', function (Blueprint $table) {
            // Only add column if it doesn't exist
            if (!Schema::hasColumn('finances', 'term_selection')) {
                $table->enum('term_selection', ['Net 30 Days', 'Net 45 Days', 'Net 60 Days'])
                    ->nullable();
            }
        });

        // Example for creditLimitAmount, if your migration also adds it
        Schema::table('finances', function (Blueprint $table) {
            if (!Schema::hasColumn('finances', 'creditLimitAmount')) {
                $table->decimal('creditLimitAmount', 12, 2)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('finances', function (Blueprint $table) {
            if (Schema::hasColumn('finances', 'term_selection')) {
                $table->dropColumn('term_selection');
            }
        });
    }
};
