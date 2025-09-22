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
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('status', [
                'Pending',
                'Confirmed',
                'Supplier Delivery',
                'International',
                'Export',
                'On hold',
                'Ready to ship',
                'Pickups',
                'Out for delivery',
                'Delivered',
                'Partially Delivered',
                'Completed',
                'Re-Attempt',
                'Returned',
                'Cancelled',
                'Failed',
                'Refunded',
                'Aborted',
                'Failure',
                'complete',
                'Invalid',
                'Captured',
                'Success',
                'Processing',
                'Awaited',
                'requires_payment_method',
                'requires_action',
                'succeeded',
                'needs_response',
                'canceled',
                'under_review',
                'won',
                'lost'
            ])->default('Pending')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('status', [
                'Pending',
                'Confirmed',
                'Supplier Delivery',
                'International',
                'Export',
                'On hold',
                'Ready to ship',
                'Pickups',
                'Out for delivery',
                'Delivered',
                'Partially Delivered',
                'Completed',
                'Re-Attempt',
                'Returned',
                'Cancelled',
                'Failed',
                'Refunded',
                'Aborted',
                'Failure',
                'complete',
                'Invalid',
                'Captured',
                'Success',
                'Processing',
                'Awaited',
                'requires_payment_method',
                'requires_action',
                'succeeded',
                'needs_response',
                'canceled',
                'under_review',
                'won',
                'lost'
            ])->default('Pending')->change();
        });
    }
};
