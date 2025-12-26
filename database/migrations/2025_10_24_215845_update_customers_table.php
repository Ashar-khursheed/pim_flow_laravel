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
            $table->tinyInteger('is_tax_free')->default(0)->after('profile_img');
            $table->integer('approval_action_by')->nullable()->after('is_tax_free');
            $table->text('approval_action_notes')->nullable()->after('approval_action_by');
            $table->timestamp('approval_action_at')->nullable()->after('approval_action_notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'is_tax_free',
                'approval_action_by',
                'approval_action_notes',
                'approval_action_at',
            ]);
        });
    }
};
