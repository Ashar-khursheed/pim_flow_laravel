<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up()
{
    Schema::create('utms', function (Blueprint $table) {
        $table->id();
        $table->string('utm_source')->nullable();
        $table->string('utm_medium')->nullable();
        $table->string('utm_campaign')->nullable();
        $table->string('utm_term')->nullable();
        $table->string('utm_content')->nullable();
        $table->string('gclid')->nullable();
        $table->string('session_id')->index();
        $table->timestamps();
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('utms');
    }
};
