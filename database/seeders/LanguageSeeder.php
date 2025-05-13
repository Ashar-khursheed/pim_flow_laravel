<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class LanguageSeeder extends Seeder
{
	/**
	 * Run the database seeds.
	 */
	public function run(): void
	{
		$languages = [
			[
				'code' => 'en',
				'name' => 'English',
				'rtl' => 0,
				'isDefault' => 1,
				'created_by' => 1,
				'updated_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
			[
				'code' => 'ar',
				'name' => 'Arabic',
				'rtl' => 1,
				'isDefault' => 0,
				'created_by' => 1,
				'updated_by' => null,
				'created_at' => Carbon::now(),
				'updated_at' => Carbon::now(),
			],
		];

		foreach ($languages as $lang) {
			DB::table('languages')->updateOrInsert(
				['code' => $lang['code']],
				$lang
			);
		}
	}
}
