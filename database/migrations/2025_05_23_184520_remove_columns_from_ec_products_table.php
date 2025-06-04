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
			110 => [135, 1, 61, 43, 42, 41],
			111 => [51, 109, 133, 47, 62, 105],
		];

		$usProductIdVendorIdMap = [
			1994 => 41,
			2460 => 29,
			2478 => 69,
			2480 => 69,
			2481 => 69,
			2501 => 69,
			2502 => 69,
			2534 => 69,
			2557 => 69,
			2558 => 69,
			2559 => 69,
			2560 => 69,
			2578 => 69,
			2644 => 69,
			2689 => 69,
			2725 => 69,
			2742 => 69,
			3002 => 74,
			3003 => 74,
			3004 => 74,
			3005 => 74,
			3030 => 74,
			3040 => 74,
			3041 => 74,
			3042 => 74,
			3043 => 74,
			3044 => 74,
			3045 => 74,
			3047 => 74,
			3048 => 74,
			3049 => 74,
			3050 => 74,
			3052 => 74,
			3053 => 74,
			3054 => 74,
			3056 => 74,
			3057 => 74,
			3060 => 74,
			3061 => 74,
			3062 => 74,
			3063 => 74,
			3064 => 74,
			3065 => 74,
			3066 => 74,
			3067 => 74,
			3068 => 74,
			3069 => 74,
			3070 => 74,
			3071 => 74,
			3072 => 74,
			3073 => 74,
			3074 => 74,
			3159 => 74,
			3205 => 74,
			3206 => 74,
			3207 => 74,
			3208 => 74,
			3209 => 74,
			3229 => 74,
			3230 => 74,
			3231 => 74,
			3232 => 74,
			3233 => 74,
			3234 => 74,
			3235 => 74,
			3236 => 74,
			3237 => 74,
			3238 => 74,
			3239 => 74,
			3240 => 74,
			3241 => 74,
			3242 => 74,
			3243 => 74,
			3244 => 74,
			3245 => 74,
			3246 => 74,
			3247 => 74,
			3248 => 74,
			3249 => 74,
			3250 => 74,
			3251 => 74,
			3252 => 74,
			3253 => 74,
			3254 => 74,
			3261 => 74,
			3274 => 74,
			3293 => 74,
			3323 => 74,
			3324 => 74,
			3325 => 74,
			3326 => 74,
			3329 => 74,
			3330 => 74,
			3331 => 74,
			3332 => 74,
			3333 => 74,
			3335 => 74,
			3336 => 74,
			3365 => 74,
			3366 => 74,
			3367 => 74,
			4106 => 49,
			4107 => 49,
			4152 => 49,
			4158 => 49,
			4159 => 49,
			4160 => 49,
			4161 => 49,
			4162 => 49,
			4163 => 49,
			4199 => 49,
			4200 => 49,
			4223 => 49,
			4225 => 49,
			4243 => 49,
			4245 => 49,
			4247 => 49,
			4263 => 49,
			4318 => 49,
			4322 => 49,
			4323 => 49,
			4380 => 49,
			4384 => 49,
			4385 => 49,
			4386 => 49,
			4387 => 49,
			4393 => 49,
			4394 => 49,
			4395 => 49,
			4396 => 49,
			4397 => 49,
			4398 => 49,
			4399 => 49,
			4400 => 49,
			4427 => 49,
			4428 => 49,
			4429 => 49,
			4433 => 49,
			4438 => 49,
			4439 => 49,
			4441 => 49,
			4448 => 49,
			4459 => 49,
			4460 => 49,
			4481 => 49,
			4482 => 49,
			4483 => 49,
			4599 => 49,
			4629 => 49,
			4634 => 49,
			4635 => 49,
			4638 => 49,
			4653 => 49,
			4654 => 49,
			4677 => 49,
			4678 => 49,
			4697 => 49,
			4730 => 49,
			4737 => 49,
			4739 => 49,
			4741 => 49,
			4749 => 49,
			4750 => 49,
			4753 => 49,
			4759 => 49,
			4760 => 49,
			4761 => 49,
			4770 => 49,
			4771 => 49,
			4793 => 70,
			4794 => 70,
			4811 => 70,
			4887 => 70,
			4888 => 70,
			4889 => 70,
			4890 => 70,
			4891 => 70,
			4892 => 70,
			4893 => 70,
			4894 => 70,
			4895 => 70,
			4896 => 70,
			4897 => 70,
			4898 => 70,
			4899 => 70,
			4900 => 70,
			4901 => 70,
			4902 => 70,
			4903 => 70,
			4904 => 70,
			4905 => 70,
			4906 => 70,
			4907 => 70,
			4908 => 70,
			4909 => 70,
			4910 => 70,
			4911 => 70,
			4912 => 70,
			4913 => 70,
			4914 => 70,
			4915 => 70,
			4916 => 70,
			4917 => 70,
			4918 => 70,
			4919 => 70,
			4920 => 70,
			4921 => 70,
			4922 => 70,
			4923 => 70,
			4924 => 70,
			4925 => 70,
			4926 => 70,
			4927 => 70,
			4928 => 70,
			4929 => 70,
			4930 => 70,
			4931 => 70,
			4932 => 70,
			4933 => 70,
			4934 => 70,
			4935 => 70,
			4936 => 70,
			4937 => 70,
			4938 => 70,
			4939 => 70,
			4940 => 70,
			4941 => 70,
			4942 => 70,
			4943 => 70,
			4944 => 70,
			4945 => 70,
			4946 => 70,
			4947 => 70,
			4948 => 70,
			4949 => 70,
			4950 => 70,
			4951 => 70,
			4952 => 70,
			4953 => 70,
			4954 => 70,
			4955 => 70,
			4956 => 70,
			4957 => 70,
			4958 => 70,
			4959 => 70,
			4960 => 70,
			4961 => 70,
			4962 => 70,
			4963 => 70,
			4964 => 70,
			4965 => 70,
			4966 => 70,
			4967 => 70,
			4968 => 70,
			4969 => 70,
			4971 => 70,
			4972 => 70,
			4973 => 70,
			4974 => 70,
			4975 => 70,
			4976 => 70,
			4977 => 70,
			4978 => 70,
			4979 => 70,
			4980 => 70,
			4981 => 70,
			4982 => 70,
			4983 => 70,
			4984 => 70,
			4985 => 70,
			4986 => 70,
			4987 => 70,
			4988 => 70,
			4989 => 70,
			4990 => 70,
			4991 => 70,
			4992 => 70,
			4993 => 70,
			4994 => 70,
			5701 => 29,
			5702 => 29,
			5703 => 29,
			5893 => 45,
			5896 => 45,
			6106 => 49,
			6142 => 49,
			6301 => 49,
			6320 => 49,
			6331 => 49,
			6368 => 49,
			6452 => 49,
			6453 => 49,
			6454 => 49,
			6455 => 49,
			6456 => 49,
			6457 => 49,
			6458 => 49,
			6468 => 49,
		];

		$uaeProductIdVendorIdMap = [];

		if (env('APP_WEBSITE') == 'UAE') {
			logger()->info('uae site executed', ['env' => env('APP_WEBSITE')]);
			$vendorStoreMap = $uaeVendorStoreMap;
			$productIdVendorIdMap = $usProductIdVendorIdMap;
		} elseif (env('APP_WEBSITE') == 'US') {
			logger()->info('us site executed', ['env' => env('APP_WEBSITE')]);
			$vendorStoreMap = $usVendorStoreMap;
			$productIdVendorIdMap = $uaeProductIdVendorIdMap;
		} else {
			logger()->info('default site executed', ['env' => env('APP_WEBSITE')]);
			$vendorStoreMap = $usVendorStoreMap;
			$productIdVendorIdMap = $usProductIdVendorIdMap;
		}

		/* First update based on vendor-store mapping */
		foreach ($vendorStoreMap as $vendorId => $storeIds) {
			DB::table('ec_products')
			->whereIn('store_id', $storeIds)
			->update([
				'vendor_id' => $vendorId,
				'store_id' => null,
			]);
		}

		/* Then update based on specific product-vendor mapping */
		foreach ($productIdVendorIdMap as $productId => $vendorId) {
			DB::table('ec_products')
			->where('id', $productId)
			->update([
				'vendor_id' => $vendorId,
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
