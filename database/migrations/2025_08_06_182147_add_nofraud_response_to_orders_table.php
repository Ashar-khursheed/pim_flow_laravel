<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNofraudResponsesTable extends Migration
{
    public function up()
    {
        Schema::create('nofraud_responses', function (Blueprint $table) {
            $table->id();
            $table->string('order_id');
            $table->longText('response')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('nofraud_responses');
    }
}
