<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateAttributeColumnInProductTitleFormulasTable extends Migration
{
    public function up()
    {
        Schema::table('product_title_formula', function (Blueprint $table) {
            // Drop the old JSON column if it exists
            if (Schema::hasColumn('product_title_formula', 'attribute_ids')) {
                $table->dropColumn('attribute_ids');
            }

            // Add the new integer column
            $table->unsignedBigInteger('attribute_id')->after('id');

        });
    }

    public function down()
    {
        Schema::table('product_title_formula', function (Blueprint $table) {
            // Reverse: drop new column and restore old one
            $table->dropForeign(['attribute_id']);
            $table->dropColumn('attribute_id');
            $table->text('attribute_ids')->nullable(); // restore if needed
        });
    }
}
