<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Website;
use App\Models\Currency;

class WebsiteSeeder extends Seeder
{
	/**
	 * Run the database seeds.
	 */
	public function run(): void
	{
		$currencyTitleIds = Currency::pluck('id', 'title')->toArray();
		$websites = [
			[
				'id' => 1,
				'name' => 'United Arab Emirates',
				'currency' => 'AED',
			],
			[
				'id' => 2,
				'name' => 'Saudi Arabia',
				'currency' => 'SAR',
			],
			[
				'id' => 3,
				'name' => 'United States',
				'currency' => 'USD',
			]
		];

		foreach ($websites as $website) {
			// Use updateOrCreate to update if exists, otherwise create
			Website::updateOrCreate(
				[
					'id' => $website['id']
				],
				[
					'name' => $website['name'],
					'currency_id' => $currencyTitleIds[$website['currency']] ?? null
				]
			);
		}
	}
}
