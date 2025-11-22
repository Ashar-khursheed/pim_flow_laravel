<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop term_selection if exists
        Schema::table('finances', function (Blueprint $table) {
            if (Schema::hasColumn('finances', 'term_selection')) {
                $table->dropColumn('term_selection');
            }
        });

        // Add term_selection safely
        Schema::table('finances', function (Blueprint $table) {
            if (!Schema::hasColumn('finances', 'term_selection')) {
                $table->enum('term_selection', ['Net 30 Days', 'Net 45 Days', 'Net 60 Days'])
                    ->nullable();
            }
        });

        // Add creditLimitAmount safely (optional: only if your migration wants to add it)
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

            if (Schema::hasColumn('finances', 'creditLimitAmount')) {
                $table->dropColumn('creditLimitAmount');
            }
        });
    }
};
