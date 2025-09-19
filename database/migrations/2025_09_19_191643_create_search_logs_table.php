<?php

// database/migrations/xxxx_xx_xx_create_search_logs_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('search_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable(); // null = guest
            $table->string('search_term')->nullable();             // when user searches
            $table->unsignedBigInteger('product_id')->nullable();  // when user clicks
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_logs');
    }
};
