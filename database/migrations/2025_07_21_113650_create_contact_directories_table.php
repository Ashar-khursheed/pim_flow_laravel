<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateContactDirectoriesTable extends Migration
{
    public function up()
    {
        Schema::create('contact_directories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id'); // No foreign key
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('image')->nullable();
            $table->string('type')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('contact_directories');
    }
}
