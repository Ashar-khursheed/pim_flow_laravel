<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateProductQuestionsTableChangeCustomerIdToEmail extends Migration
{
    public function up()
    {
        Schema::table('product_questions', function (Blueprint $table) {
            // Drop the customer_id column
            if (Schema::hasColumn('product_questions', 'customer_id')) {
                $table->dropColumn('customer_id');
            }

            // Add email column
            $table->string('email')->nullable()->after('id'); // Adjust 'after' as needed
        });
    }

    public function down()
    {
        Schema::table('product_questions', function (Blueprint $table) {
            // Reverse: remove email and add back customer_id
            if (Schema::hasColumn('product_questions', 'email')) {
                $table->dropColumn('email');
            }

            $table->unsignedBigInteger('customer_id')->nullable(); // Adjust type if needed
        });
    }
}
