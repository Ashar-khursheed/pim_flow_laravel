<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('redirect_links', function (Blueprint $table) {
            $table->id();
            $table->string('from')->unique(); // Old path (e.g., /category1)
            $table->string('to');             // New path (e.g., /category4324/22)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redirect_links');
    }
};
