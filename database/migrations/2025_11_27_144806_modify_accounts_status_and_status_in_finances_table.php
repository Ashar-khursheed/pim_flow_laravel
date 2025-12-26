<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        
        DB::table('finances')->where('accountsStatus', 'Reject')->update(['accountsStatus' => 'Rejected']);
        DB::table('finances')->whereNull('accountsStatus')->update(['accountsStatus' => 'Pending']);
        DB::table('finances')->whereNotIn('status', ['Overdue', 'Pending', 'Paid'])
            ->update(['status' => 'Pending']);

        // Step 2: Modify ENUM columns
        Schema::table('finances', function (Blueprint $table) {
            $table->enum('accountsStatus', ['Pending', 'Approved', 'Rejected', 'Hold'])
                  ->default('Pending')
                  ->change();

            $table->enum('status', ['Overdue', 'Pending', 'Paid'])
                  ->default('Pending')
                  ->change();
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('finances', function (Blueprint $table) {
            $table->enum('accountsStatus', ['Pending', 'Approved', 'Reject'])
                  ->default('Pending')
                  ->change();

            $table->enum('status', ['Overdue', 'Pending'])
                  ->default('Pending')
                  ->change();
        });
    }
};
