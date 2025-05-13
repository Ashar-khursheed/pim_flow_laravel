<?php

// database/migrations/xxxx_xx_xx_create_product_suppliers_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('sku');
            $table->unsignedBigInteger('vendor_id');
            $table->unsignedBigInteger('product_id'); // Not a foreign key
            $table->text('warranty_information')->nullable();
            $table->text('refund')->nullable();
            $table->text('delivery_days')->nullable();
            $table->decimal('cost_per_item', 10, 2)->nullable();
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('margin', 10, 2)->nullable();
            $table->integer('inventory')->nullable();
            $table->decimal('additional_cost', 10, 2)->nullable();
            $table->decimal('final_cost_price', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_suppliers');
    }
};
