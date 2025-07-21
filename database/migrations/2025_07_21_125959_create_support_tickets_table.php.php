<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable(); // Link to customer (no FK)
            $table->string('full_name');
            $table->string('email');
            $table->string('company_name')->nullable();
            $table->string('phone_number')->nullable();
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('priority_id');
            $table->string('subject');
            $table->text('description');
            $table->string('reference_id')->nullable();
            $table->string('file_path')->nullable(); // File upload
            $table->enum('status', ['open', 'in_progress', 'resolved', 'closed'])->default('open');
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('support_tickets');
    }
};
