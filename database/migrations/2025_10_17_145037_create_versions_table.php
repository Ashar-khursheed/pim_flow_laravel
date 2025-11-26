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
        Schema::create('versions', function (Blueprint $table) {

            $table->id();
            $table->string('version_id')->nullable(); // model id
            $table->string('module')->nullable(); // model class
            $table->string('action')->nullable(); // created/updated/deleted
            $table->string('status')->nullable(); // who status
            $table->longText('description')->nullable(); // full snapshot JSON (or diff)
            $table->longText('meta')->nullable(); // extra info (ip, reason)
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable(); //  "autosave"
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('versions');
    }
};
