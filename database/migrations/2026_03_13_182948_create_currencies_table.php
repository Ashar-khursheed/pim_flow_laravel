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
		/* Create currencies table */
		Schema::create('currencies', function (Blueprint $table) {
			$table->id();
			$table->string('title', 191);
			$table->string('symbol', 10);
			$table->string('major_unit_name', 50)->nullable()->comment('E.g., Dollars, Dirhams, Pounds');
			$table->string('minor_unit_name', 50)->nullable()->comment('E.g., Cents, Fils, Pence');
			$table->boolean('is_default')->default(0);
			$table->unsignedBigInteger('created_by')->nullable();
			$table->unsignedBigInteger('updated_by')->nullable();
			$table->timestamps();

			/* Indexes */
			$table->index('title');
			$table->index('is_default');
			$table->index('created_by');
			$table->index('updated_by');
		});

		/* Copy data from ec_currencies to currencies */
		DB::table('currencies')->insertUsing(
			['id', 'title', 'symbol', 'is_default', 'created_by', 'updated_by', 'created_at', 'updated_at'],
			DB::table('ec_currencies')
			->select(
				'id',
				'title',
				'symbol',
				'is_default',
				DB::raw('1 as created_by'),
				DB::raw('1 as updated_by'),
				'created_at',
				'updated_at'
			)
		);

		/* Optional: Update specific currencies with major/minor unit names */
		DB::table('currencies')->where('symbol', 'USD')->orWhere('symbol', '$')->update([
			'major_unit_name' => 'U.S. Dollars',
			'minor_unit_name' => 'Cents'
		]);

		DB::table('currencies')->where('symbol', 'AED')->update([
			'major_unit_name' => 'AED',
			'minor_unit_name' => 'Fils'
		]);
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('currencies');
	}
};