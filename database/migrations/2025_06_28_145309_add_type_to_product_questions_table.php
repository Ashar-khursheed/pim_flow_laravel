<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTypeToProductQuestionsTable extends Migration
{
    public function up(): void
    {
        Schema::table('product_questions', function (Blueprint $table) {
            $table->string('type')->nullable()->after('question'); // Adjust 'question' if needed
        });
    }

    public function down(): void
    {
        Schema::table('product_questions', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
}
