<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('seo_monitorings', function (Blueprint $table) {
            $table->id();
            $table->string('relational_type')->nullable();
            $table->date('date')->nullable();
            $table->text('url')->nullable();
            $table->string('keyword')->nullable();
            $table->string('country')->nullable();
            $table->string('device')->nullable();
            $table->integer('total_clicks')->default(0);
            $table->integer('impressions')->default(0);
            $table->float('click_rate')->default(0);
            $table->float('position')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seo_monitorings');
    }
    
};
