<?php
// database/migrations/xxxx_xx_xx_create_product_title_formula_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductTitleFormulaTable extends Migration
{
    public function up()
    {
        Schema::create('product_title_formula', function (Blueprint $table) {
            $table->id();
            $table->text('attribute_ids'); // store multiple IDs as JSON
            $table->unsignedBigInteger('category_id')->nullable(); // new column
            $table->boolean('locked')->default(false); // false = unlocked, true = locked
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_title_formula');
    }
}
