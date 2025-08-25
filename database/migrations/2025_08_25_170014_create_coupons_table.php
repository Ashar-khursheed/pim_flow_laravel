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
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['fixed', 'percentage'])->default('fixed');
            $table->decimal('value', 10, 2);
            $table->enum('basis', ['customer', 'category', 'product', 'promotional'])->default('promotional');
            
            // Order value constraints
            $table->decimal('min_order_value', 10, 2)->default(0);
            $table->decimal('max_order_value', 10, 2)->nullable();
            
            // Usage constraints
            $table->enum('usage_type', ['once', 'multiple'])->default('multiple');
            $table->integer('usage_limit')->nullable(); // null means unlimited
            $table->integer('usage_count')->default(0);
            $table->integer('usage_limit_per_customer')->nullable();
            
            // Date constraints
            $table->dateTime('start_date');
            $table->dateTime('expire_date');
            
            // Status and approval
            $table->boolean('is_active')->default(true);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            
            // Tracking
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->dateTime('approved_at')->nullable();
            
            $table->timestamps();
            
            // Foreign keys
            $table->unsignedBigInteger('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->unsignedBigInteger('approved_by')->references('id')->on('users')->onDelete('set null');
            
            // Indexes
            $table->index(['code', 'is_active']);
            $table->index(['basis']);
            $table->index(['start_date', 'expire_date']);
            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};