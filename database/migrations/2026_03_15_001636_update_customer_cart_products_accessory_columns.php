<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
	/* Run the migrations */
	public function up(): void
	{
		Schema::table('customer_cart_products', function (Blueprint $table) {
			$table->decimal('accessory_item_charge', 10, 2)->default(0)->after('amount');
		});

		if (in_array(config('app.website'), ['US', 'US_T'])) {
			/* US — accessories_options JSON array se accessory_charges populate karo */
			$cartProducts = DB::table('customer_cart_products')
				->whereNotNull('accessories_options')
				->where('accessories_options', '!=', '[]')
				->get();

			foreach ($cartProducts as $cartProduct) {
				$accessoryIds = is_array($cartProduct->accessories_options)
					? $cartProduct->accessories_options
					: (json_decode($cartProduct->accessories_options, true) ?? []);

				if (empty($accessoryIds)) {
					continue;
				}

				/* Total accessory charge calculate karo is cart product ke liye */
				$totalCharge = 0;

				foreach ($accessoryIds as $accessoryItemId) {
					/* price fetch karo product_accessory_types se */
					$price = DB::table('accessory_items')
						->where('product_accessory_id', $accessoryItemId)
						->value('price');

					if (!$price) {
						continue;
					}

					DB::table('accessory_charges')->insert([
						'relation_type' => 'App\\Models\\FrontEnd\\CustomerCartProduct',
						'relation_id'   => $cartProduct->id,
						'accessory_item_id' => $accessoryItemId,
						'amount'        => $price,
						'created_at'    => $cartProduct->created_at,
					]);

					$totalCharge += $price;
				}

				/* accessory_item_charge update karo */
				if ($totalCharge > 0) {
					DB::table('customer_cart_products')
						->where('id', $cartProduct->id)
						->update(['accessory_item_charge' => $totalCharge]);
				}
			}
		}

		Schema::table('customer_cart_products', function (Blueprint $table) {
			$table->dropColumn('accessories_options');
		});
	}

	/* Reverse the migrations */
	public function down(): void
	{
		Schema::table('customer_cart_products', function (Blueprint $table) {
			$table->dropColumn('accessory_item_charge');
			$table->text('accessories_options')->nullable()->after('amount');
		});
	}
};