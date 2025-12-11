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
        Schema::create('finances_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->unsignedBigInteger('finances_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('order_number')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            
            $table->date('due_date')->nullable();
            $table->decimal('due_amount', 10, 2)->default(0);
            $table->date('paid_on_date')->nullable();
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->decimal('balance', 10, 2)->default(0);

            $table->string('creditTerms')->nullable();

            $table->enum('status', ['Paid', 'Pending'])->default('Pending');
            $table->string('payment_mode')->nullable();

            $table->unsignedBigInteger('paid_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finances_histories');
    }
};
