<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up()
	{
		try {
			DB::statement("ALTER TABLE ec_products DROP FOREIGN KEY ec_products_shipping_length_id_foreign");
		} catch (\Exception $e) {
			logger()->warning('Foreign key ec_products_shipping_length_id_foreign does not exist or already dropped.');
		}

		try {
			DB::statement("ALTER TABLE ec_products DROP FOREIGN KEY ec_products_unit_of_measurement_id_foreign");
		} catch (\Exception $e) {
			logger()->warning('Foreign key ec_products_unit_of_measurement_id_foreign does not exist or already dropped.');
		}

		DB::statement('CREATE TABLE ec_products1 LIKE ec_products');
		DB::statement('INSERT INTO ec_products1 SELECT * FROM ec_products');

		Schema::table('ec_products', function (Blueprint $table) {
			$table->integer('vendor_id')->after('store_id')->nullable();
			$table->boolean('quote_available')->after('brand_id')->nullable();

			$table->dropColumn([
				'allow_checkout_when_out_of_stock',
				'with_storehouse_management',
				'sale_type',
				'start_date',
				'end_date',
				'length',
				'length_unit_id',
				'width',
				'height',
				'depth',
				'weight',
				'weight_unit_id',
				'created_by_type',
				'image',
				'minimum_order_quantity',
				'maximum_order_quantity',
				'handle',
				'variant_grams',
				'variant_inventory_tracker',
				'variant_inventory_quantity',
				'variant_inventory_policy',
				'unit_of_weight_id',
				'unit_of_measurement_id',
				'variant_barcode',
				'gift_card',
				'video_url',
				'shipping_weight_option',
				'shipping_weight',
				'shipping_dimension_option',
				'shipping_width',
				'shipping_depth',
				'shipping_height',
				'shipping_length',
				'shipping_length_id',
				'compare_type',
				'compare_products',
				'cost_type',
				'additional_cost_percentage',
				'total_cost_per_item',
			]);

			$table->renameColumn('created_by_id', 'created_by');
		});

		$usVendorStoreMap = [
			1  => [8, 17, 18, 32, 48],
			3  => [29],
			10 => [64],
			13 => [8, 17, 18, 29, 37, 41, 50, 51],
			16 => [52],
			17 => [30],
			18 => [31],
			25 => [58],
			26 => [34],
			29 => [21, 23, 39, 50],
			33 => [66],
			34 => [27],
			37 => [42],
			38 => [28],
			39 => [16],
			40 => [7],
			41 => [19],
			43 => [60],
			44 => [38],
			45 => [40],
			46 => [22],
			47 => [43],
			48 => [20],
			49 => [54, 59],
			50 => [41],
			52 => [37],
			54 => [26],
			67 => [33, 36, 47, 56],
			68 => [61, 63],
			69 => [57],
			70 => [44, 46, 53, 55],
			71 => [62],
			72 => [65],
			73 => [49],
			74 => [35],
			75 => [45],
			76 => [48],
		];

		$uaeVendorStoreMap = [
			1   => [107],
			2   => [94],
			3   => [71],
			4   => [76],
			5   => [78],
			6   => [56],
			7   => [40],
			8   => [93],
			9   => [48],
			10  => [52],
			12  => [58],
			13  => [28],
			14  => [32],
			15  => [103],
			16  => [29, 85],
			17  => [96],
			18  => [53],
			19  => [123],
			20  => [97],
			21  => [99],
			22  => [119],
			23  => [108],
			24  => [89],
			25  => [33],
			26  => [98],
			27  => [124],
			28  => [37],
			29  => [57],
			30  => [114],
			31  => [131],
			32  => [54],
			33  => [44],
			34  => [74],
			35  => [118],
			36  => [104],
			37  => [92],
			38  => [116],
			39  => [117],
			40  => [90],
			41  => [115],
			42  => [134],
			43  => [110],
			44  => [80],
			45  => [95],
			46  => [102],
			47  => [27],
			48  => [113],
			49  => [26, 35, 79],
			50  => [81],
			51  => [75],
			52  => [70],
			53  => [88],
			54  => [112],
			55  => [91],
			56  => [122],
			57  => [34],
			58  => [84],
			59  => [101],
			60  => [73],
			61  => [100],
			62  => [127],
			63  => [126],
			64  => [60],
			65  => [50],
			66  => [106],
			67  => [31, 125],
			68  => [87],
			69  => [121],
			70  => [129],
			71  => [82],
			72  => [132],
			73  => [130],
			74  => [120],
			75  => [59],
			76  => [38],
			77  => [77],
			78  => [86, 128],
			79  => [63],
			80  => [36],
			88  => [45, 55],
			99  => [30],
			100 => [39],
			101 => [64],
			102 => [65],
			103 => [66],
			104 => [67],
			105 => [68],
			106 => [69],
			107 => [83],
			108 => [111],
		];

		if (env('APP_WEBSITE') == 'UAE') {
			logger()->info('uae site executed', ['env' => env('APP_WEBSITE')]);
			$vendorStoreMap = $uaeVendorStoreMap;
		} elseif (env('APP_WEBSITE') == 'US') {
			logger()->info('us site executed', ['env' => env('APP_WEBSITE')]);
			$vendorStoreMap = $usVendorStoreMap;
		} else {
			logger()->info('default site executed', ['env' => env('APP_WEBSITE')]);
			$vendorStoreMap = $usVendorStoreMap;
		}

		foreach ($vendorStoreMap as $vendorId => $storeIds) {
			DB::table('ec_products')
			->whereIn('store_id', $storeIds)
			->update([
				'vendor_id' => $vendorId,
				'store_id' => null,
			]);
		}
	}

	public function down()
	{
		Schema::table('ec_products', function (Blueprint $table) {
			$table->tinyInteger('allow_checkout_when_out_of_stock')->default(0);
			$table->tinyInteger('with_storehouse_management')->default(0);
			$table->tinyInteger('sale_type')->default(0);
			$table->timestamp('start_date')->nullable();
			$table->timestamp('end_date')->nullable();
			$table->double('length', 8, 2)->nullable();
			$table->integer('length_unit_id')->nullable();
			$table->double('width', 8, 2)->nullable();
			$table->double('height', 8, 2)->nullable();
			$table->double('depth', 8, 2)->nullable();
			$table->double('weight', 8, 2)->nullable();
			$table->integer('weight_unit_id')->nullable();
			$table->string('created_by_type', 191)->default('Botble\\ACL\\Models\\User');
			$table->text('image')->nullable();
			$table->integer('minimum_order_quantity')->default(0);
			$table->integer('maximum_order_quantity')->default(0);
			$table->string('handle', 191)->nullable();
			$table->integer('variant_grams')->nullable();
			$table->string('variant_inventory_tracker', 191)->nullable();
			$table->integer('variant_inventory_quantity')->nullable();
			$table->string('variant_inventory_policy', 191)->nullable();
			$table->bigInteger('unit_of_weight_id')->nullable();
			$table->bigInteger('unit_of_measurement_id')->nullable();
			$table->string('variant_barcode', 191)->nullable();
			$table->boolean('gift_card')->nullable();
			$table->string('video_url', 191)->nullable();
			$table->string('shipping_weight_option')->nullable();
			$table->decimal('shipping_weight', 8, 2)->nullable();
			$table->string('shipping_dimension_option')->nullable();
			$table->decimal('shipping_width', 8, 2)->nullable();
			$table->decimal('shipping_depth', 8, 2)->nullable();
			$table->decimal('shipping_height', 8, 2)->nullable();
			$table->decimal('shipping_length', 8, 2)->nullable();
			$table->bigInteger('shipping_length_id')->nullable();
			$table->longText('compare_type')->nullable();
			$table->longText('compare_products')->nullable();
			$table->enum('cost_type', ['percentage', 'value'])->nullable();
			$table->decimal('additional_cost_percentage', 5, 2)->nullable();
			$table->decimal('total_cost_per_item', 10, 2)->nullable();

			$table->renameColumn('created_by', 'created_by_id');
			$table->dropColumn('vendor_id');
			$table->dropColumn('quote_available');
		});
	}
};
