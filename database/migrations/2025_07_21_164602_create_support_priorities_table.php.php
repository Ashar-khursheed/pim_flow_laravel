<?php
// database/migrations/xxxx_xx_xx_create_support_priorities_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSupportPrioritiesTable extends Migration
{
    public function up()
    {
        Schema::create('support_priorities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedTinyInteger('level')->default(1); // 1 = Low, 2 = Medium, 3 = High
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('support_priorities');
    }
}
