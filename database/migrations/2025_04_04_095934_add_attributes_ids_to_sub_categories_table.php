<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAttributesIdsToSubCategoriesTable extends Migration
{
    public function up()
    {
        // Adding the attributes_ids field to the sub_categories table
        Schema::table('sub_categories', function (Blueprint $table) {
            $table->longText('attributes_ids')->nullable(); // Store attribute IDs as JSON
        });
    }

    public function down()
    {
        // Rollback the migration
        Schema::table('sub_categories', function (Blueprint $table) {
            $table->dropColumn('attributes_ids');
        });
    }
}
