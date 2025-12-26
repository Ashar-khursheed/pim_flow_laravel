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
        Schema::create('finances_payments', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('finances_id');
        $table->decimal('limitAmount', 12, 2)->nullable();
        $table->decimal('usedAmount', 12, 2)->nullable();
        $table->decimal('availableAmount', 12, 2)->nullable();
        $table->decimal('purchaseAmount', 12, 2)->nullable();
        $table->decimal('dueAmount', 12, 2)->nullable();
        $table->enum('creditTerms', ['Net 30 Days', 'Net 45 Days', 'Net 60 Days'])->nullable();
        $table->date('nextPaymentDue')->nullable();
        $table->string('payment_mode')->nullable();
        $table->timestamps();        
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finances_payments');
    }
};
