<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Currency;

class CurrencySeeder extends Seeder
{
	/**
	 * Run the database seeds.
	 */
	public function run(): void
	{
		$currencies = [
			[
				'id' => 1,
				'title' => 'USD',
				'symbol' => '$',
				'is_prefix_symbol' => '1',
				'decimals' => '2',
				'order' => '1',
				'is_default' => '1',
				'exchange_rate' => '1',
			],
			[
				'id' => 2,
				'title' => 'EUR',
				'symbol' => '€',
				'is_prefix_symbol' => '0',
				'decimals' => '2',
				'order' => '3',
				'is_default' => '0',
				'exchange_rate' => '0.9531',
			],
			[
				'id' => 3,
				'title' => 'VND',
				'symbol' => '₫',
				'is_prefix_symbol' => '0',
				'decimals' => '0',
				'order' => '5',
				'is_default' => '0',
				'exchange_rate' => '25539.98',
			],
			[
				'id' => 4,
				'title' => 'NGN',
				'symbol' => '₦',
				'is_prefix_symbol' => '1',
				'decimals' => '2',
				'order' => '7',
				'is_default' => '0',
				'exchange_rate' => '461.50',
			],
			[
				'id' => 5,
				'title' => 'AED',
				'symbol' => 'AED',
				'is_prefix_symbol' => '1',
				'decimals' => '2',
				'order' => '9',
				'is_default' => '0',
				'exchange_rate' => '3.67',
			],
			[
				'id' => 6,
				'title' => 'SAR',
				'symbol' => 'SAR',
				'is_prefix_symbol' => '1',
				'decimals' => '2',
				'order' => '11',
				'is_default' => '0',
				'exchange_rate' => '3.75',
			]
		];

		foreach ($currencies as $currency) {
			// Use updateOrCreate to update if exists, otherwise create
			Currency::updateOrCreate(
				[
					'id' => $currency['id'],
					'title' => $currency['title'],
				],
				[
					'symbol' => $currency['symbol'],
					'is_prefix_symbol' => $currency['is_prefix_symbol'],
					'decimals' => $currency['decimals'],
					'order' => $currency['order'],
					'is_default' => $currency['is_default'],
					'exchange_rate' => $currency['exchange_rate']
				]
			);
		}
	}
}
