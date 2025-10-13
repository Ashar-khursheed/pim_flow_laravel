<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::table('inquiries', function (Blueprint $table) {
			$table->string('lead_type')->nullable()->after('restaurant_type');
			$table->string('lead_source')->nullable()->after('lead_type');
			$table->string('landing_page')->nullable()->after('lead_source');
		});
	}

	public function down(): void
	{
		Schema::table('inquiries', function (Blueprint $table) {
			$table->dropColumn(['lead_type', 'lead_source', 'landing_page']);
		});
	}
};
