<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            // 🔹 New columns
            if (!Schema::hasColumn('product_variants', 'variants')) {
                $table->text('variants')->nullable()->after('parent_id');
            }
            if (!Schema::hasColumn('product_variants', 'child_ids')) {
                $table->text('child_ids')->nullable()->after('variants');
            }
            $table->dropForeign(['child_id']);
            // 🔹 Drop old columns (check if exists to avoid errors)
            if (Schema::hasColumn('product_variants', 'child_id')) {
 
                $table->dropColumn('child_id');                
 
 
            }
            $table->dropForeign(['child_id']);
            if (Schema::hasColumn('product_variants', 'label')) {
                $table->dropColumn('label');
            }
            if (Schema::hasColumn('product_variants', 'attribute_id')) {
                $table->dropColumn('attribute_id');
            }
            if (Schema::hasColumn('product_variants', 'type')) {
                $table->dropColumn('type');
            }
        });
         
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
         Schema::table('product_variants', function (Blueprint $table) {
            // 🔹 Drop new columns
            if (Schema::hasColumn('product_variants', 'variants')) {
                $table->dropColumn('variants');
            }
            if (Schema::hasColumn('product_variants', 'child_ids')) {
                $table->dropColumn('child_ids');
            }
            $table->dropForeign(['child_id']);
            if (Schema::hasColumn('product_variants', 'child_id')) {
                $table->dropColumn('child_id');
                 $table->dropForeign(['child_id']);
            }
            if (Schema::hasColumn('product_variants', 'label')) {
                $table->dropColumn('label');
            }
            if (Schema::hasColumn('product_variants', 'attribute_id')) {
                $table->dropColumn('attribute_id');
            }
            if (Schema::hasColumn('product_variants', 'type')) {
                $table->dropColumn('type');
            }
        });
    }
};
