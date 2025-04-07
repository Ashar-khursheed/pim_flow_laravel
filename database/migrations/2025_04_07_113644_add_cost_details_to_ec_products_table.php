<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCostDetailsToEcProductsTable extends Migration
{
    public function up()
    {
        Schema::table('ec_products', function (Blueprint $table) {
            // Adding the required columns
            $table->string('cost_per_item_currency', 3)->nullable(); // Cost Per Item Currency (e.g. USD)
            $table->enum('cost_type', ['percentage', 'value'])->nullable(); // Cost Type (Percentage or Value)
            $table->decimal('additional_cost_percentage', 5, 2)->nullable(); // Additional Cost Percentage (e.g. 16)
            $table->decimal('additional_cost_value', 10, 2)->nullable(); // Additional Cost Value (e.g. 2000)
            $table->decimal('total_cost_per_item', 10, 2)->nullable(); // Total Cost Per Item (e.g. 139.2 or 2120)
        });
    }

    public function down()
    {
        Schema::table('ec_products', function (Blueprint $table) {
            // Dropping the columns if the migration is rolled back
            $table->dropColumn('cost_per_item_currency');
            $table->dropColumn('cost_type');
            $table->dropColumn('additional_cost_percentage');
            $table->dropColumn('additional_cost_value');
            $table->dropColumn('total_cost_per_item');
        });
    }
}
