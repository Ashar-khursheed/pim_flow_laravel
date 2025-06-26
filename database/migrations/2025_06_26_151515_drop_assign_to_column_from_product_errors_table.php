<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('product_errors', function (Blueprint $table) {
            $table->dropColumn('assign_to');
        });
    }

    public function down(): void
    {
        Schema::table('product_errors', function (Blueprint $table) {
            $table->unsignedBigInteger('assign_to')->nullable(); // or use the original type
        });
    }
};
