<?php

use PhpUnitsOfMeasure\PhysicalQuantity\Length;
use PhpUnitsOfMeasure\PhysicalQuantity\Mass;
use PhpUnitsOfMeasure\PhysicalQuantity\Volume;
use PhpUnitsOfMeasure\PhysicalQuantity\Temperature;
use PhpUnitsOfMeasure\PhysicalQuantity\Time;
use PhpUnitsOfMeasure\PhysicalQuantity\Speed;
use PhpUnitsOfMeasure\PhysicalQuantity\Area;
use PhpUnitsOfMeasure\PhysicalQuantity\Energy;
use PhpUnitsOfMeasure\PhysicalQuantity\Pressure;
use PhpUnitsOfMeasure\PhysicalQuantity\Force;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;

if (!function_exists('app_constants')) {
	function app_constants($key = null) {
		$constants = [
			'DELIVERY_DAYS' => [
				'1 to 2 Days',
				'2 to 3 Days',
				'5 to 7 Days',
				'10 to 12 Days',
				'3 to 4 Weeks',
				'6 Weeks',
				'8 to 10 Weeks',
				'12 Weeks'
			],
			'WARRANTY_OPTIONS' => [
				'1 Month',
				'2 Months',
				'3 Months',
				'6 Months',
				'1 Year',
				'2 Years',
				'3 Years',
				'5 Years',
				'10 Years',
				'Lifetime Warranty'
			],
			'RETURN_POLICY' => [
				'Non-Returnable',
				'3 Days',
				'7 Days',
				'14 Days',
				'30 Days',
				'60 Days',
				'90 Days'
			],
			'IN_STOCK_OPTIONS' => [
				1 => 'Yes',
				0 => 'No',
			],
			'FREE_SHIPPING_OPTIONS' => [
				1 => 'Yes',
				0 => 'No',
			],
		];

		return $key ? ($constants[$key] ?? []) : $constants;
	}
}

if (!function_exists('product_constants')) {
	function product_constants($key = null) {
		$constants = [
			'HEADER_MAP1' => [
				"id" => "Id",
				"url" => "URL",
				"name" => "Name",
				"sku" => "SKU",
				"brand" => "Brand",
				"categories" => "Categories",
			// "content" => "Content",
			],
			'DESCRIPTION_COLUMNS' => [
				"description1" => "Description1",
				"description2" => "Description2",
				"description3" => "Description3",
				"description4" => "Description4",
			],
			'BENIFITS_FEATURES_COLUMNS' => [
				"benefit1" => "Benefit1",
				"feature1" => "Feature1",
				"benefit2" => "Benefit2",
				"feature2" => "Feature2",
				"benefit3" => "Benefit3",
				"feature3" => "Feature3",
				"benefit4" => "Benefit4",
				"feature4" => "Feature4",
				"benefit5" => "Benefit5",
				"feature5" => "Feature5",
				"benefit6" => "Benefit6",
				"feature6" => "Feature6",
				"benefit7" => "Benefit7",
				"feature7" => "Feature7",
				"benefit8" => "Benefit8",
				"feature8" => "Feature8",
				"benefit9" => "Benefit9",
				"feature9" => "Feature9",
				"benefit10" => "Benefit10",
				"feature10" => "Feature10",
			],
			'FAQ_COLUMNS' => [
				"faq_question1" => "FAQ Question1",
				"faq_answer1" => "FAQ Answer1",
				"faq_question2" => "FAQ Question2",
				"faq_answer2" => "FAQ Answer2",
				"faq_question3" => "FAQ Question3",
				"faq_answer3" => "FAQ Answer3",
				"faq_question4" => "FAQ Question4",
				"faq_answer4" => "FAQ Answer4",
				"faq_question5" => "FAQ Question5",
				"faq_answer5" => "FAQ Answer5",
				"faq_question6" => "FAQ Question6",
				"faq_answer6" => "FAQ Answer6",
				"faq_question7" => "FAQ Question7",
				"faq_answer7" => "FAQ Answer7",
				"faq_question8" => "FAQ Question8",
				"faq_answer8" => "FAQ Answer8",
				"faq_question9" => "FAQ Question9",
				"faq_answer9" => "FAQ Answer9",
				"faq_question10" => "FAQ Question10",
				"faq_answer10" => "FAQ Answer10",
			],
			'HEADER_MAP2' => [
			// "description" => "Description",
				"warranty_information" => "Warranty Information",
				"vendor" => "Vendor",
				"tags" => "Tags",
				"stock_status" => "Stock Status",
				// "with_storehouse_management" => "With Storehouse Management",
				"quantity" => "Quantity",
				"cost_per_item" => "Cost Per Item",
				// "unit_of_measurement" => "Unit of Measurement",
				"price" => "Price",
				"sale_price" => "Sale Price",
				// "start_date_sale_price" => "Start Date Sale Price",
				// "end_date_sale_price" => "End Date Sale Price",
				// "minimum_order_quantity" => "Minimum Order Quantity",
				"box_quantity" => "Box Quantity",
				"delivery_days" => "Delivery Days",
				"variant_requires_shipping" => "Variant Requires Shipping",
				"images" => "Images",
				"upload_video" => "Upload Video",
				"barcode" => "Barcode (ISBN, UPC, GTIN, etc.)",
				"refund_policy" => "Refund Policy",
				"status" => "Status",
				"google_shopping_category" => "Google Shopping Category",
				"google_shopping_mpn" => "Google Shopping Mpn",
				"is_featured" => "Is Featured",
				// "weight_option" => "Weight Option",
				// "weight" => "Weight",
				// "dimension_option" => "Dimension Option",
				// "length" => "Length",
				// "width" => "Width",
				// "height" => "Height",
				// "depth" => "Depth",
				// "shipping_weight_option" => "Shipping Weight Option",
				// "shipping_weight" => "Shipping Weight",
				// "shipping_dimension_option" => "Shipping Dimension Option",
				// "shipping_width" => "Shipping Width",
				// "shipping_depth" => "Shipping Depth",
				// "shipping_height" => "Shipping Height",
				// "shipping_length" => "Shipping Length",
				"frequently_bought_together" => "Frequently Bought Together",
				// "compare_products" => "Compare Products",
				"variant_1_title" => "Variant 1 Title",
				"variant_1_value" => "Variant 1 Value",
				"variant_1_products" => "Variant 1 Products",
				"variant_2_title" => "Variant 2 Title",
				"variant_2_value" => "Variant 2 Value",
				"variant_2_products" => "Variant 2 Products",
				"variant_3_title" => "Variant 3 Title",
				"variant_3_value" => "Variant 3 Value",
				"variant_3_products" => "Variant 3 Products",
				"variant_color_title" => "Variant Color Title",
				"variant_color_value" => "Variant Color Value",
				"variant_color_products" => "Variant Color Products",
			],
			'SEO_SECTION' => [
				"meta_title" => "Meta Title",
				"meta_description" => "Meta Description",
			],
			'DISCOUNT_SECTION' => [
				"buying_quantity1" => "Buying Quantity1",
				"discount1" => "Discount1",
				"start_date1" => "Start Date1",
				"end_date1" => "End Date1",
				"buying_quantity2" => "Buying Quantity2",
				"discount2" => "Discount2",
				"start_date2" => "Start Date2",
				"end_date2" => "End Date2",
				"buying_quantity3" => "Buying Quantity3",
				"discount3" => "Discount3",
				"start_date3" => "Start Date3",
				"end_date3" => "End Date3",
			],
			'TRANSLATION_SECTION' => [
				"name_ar" => "Name (AR)",
				"description_ar" => "Description (AR)",
				"content_ar" => "Content (AR)",
				"warranty_information_ar" => "Warranty Information (AR)"
			],
		];

		return $key ? ($constants[$key] ?? []) : $constants;
	}
}


if (!function_exists('product_import_constants')) {
	function product_import_constants($key = null) {
		$constants = [
			'ID' => [
				'Id' => 'id',
			],

			'URL' => [
				'URL' => 'url',
			],

			'GENERAL_FIELDS' => [
				'Name' => 'name',
				'SKU' => 'sku',
				'Brand' => 'brand',
				'Categories' => 'category',
			],

			'DESCRIPTION_SECTION' => [
				'Description1' => 'description1',
				'Description2' => 'description2',
				'Description3' => 'description3',
				'Description4' => 'description4',
			],

			'BENEFIT_SECTION' => [
				'Benefit1' => 'benefit1',
				'Feature1' => 'feature1',
				'Benefit2' => 'benefit2',
				'Feature2' => 'feature2',
				'Benefit3' => 'benefit3',
				'Feature3' => 'feature3',
				'Benefit4' => 'benefit4',
				'Feature4' => 'feature4',
				'Benefit5' => 'benefit5',
				'Feature5' => 'feature5',
				'Benefit6' => 'benefit6',
				'Feature6' => 'feature6',
				'Benefit7' => 'benefit7',
				'Feature7' => 'FEATURE7',
				'Benefit8' => 'benefit8',
				'Feature8' => 'feature8',
				'Benefit9' => 'benefit9',
				'Feature9' => 'feature9',
				'Benefit10' => 'benefit10',
				'Feature10' => 'feature10',
			],

			'FAQ_SECTION' => [
				"FAQ Question1" => "faq_question1",
				"FAQ Answer1" => "faq_answer1",
				"FAQ Question2" => "faq_question2",
				"FAQ Answer2" => "faq_answer2",
				"FAQ Question3" => "faq_question3",
				"FAQ Answer3" => "faq_answer3",
				"FAQ Question4" => "faq_question4",
				"FAQ Answer4" => "faq_answer4",
				"FAQ Question5" => "faq_question5",
				"FAQ Answer5" => "faq_answer5",
				"FAQ Question6" => "faq_question6",
				"FAQ Answer6" => "faq_answer6",
				"FAQ Question7" => "faq_question7",
				"FAQ Answer7" => "faq_answer7",
				"FAQ Question8" => "faq_question8",
				"FAQ Answer8" => "faq_answer8",
				"FAQ Question9" => "faq_question9",
				"FAQ Answer9" => "faq_answer9",
				"FAQ Question10" => "faq_question10",
				"FAQ Answer10" => "faq_answer10",
			],

			'ADVANCED_FIELDS' => [
				'Warranty Information' => 'warrantyInformation',
				'Vendor' => 'vendor',
				'Tags' => 'tags',
				'Stock Status' => 'stockStatus',
				// 'With Storehouse Management' => 'withStorehouseManagement',
				'Quantity' => 'quantity',
				'Cost Per Item' => 'costPerItem',
				// 'Unit of Measurement' => 'unitOfMeasurement',
				'Price' => 'price',
				'Sale Price' => 'salePrice',
				// 'Start Date Sale Price' => 'startDateSalePrice',
				// 'End Date Sale Price' => 'endDateSalePrice',
				// 'Minimum Order Quantity' => 'minimumOrderQuantity',
				'Box Quantity' => 'boxQuantity',
				'Delivery Days' => 'deliveryDays',
				'Variant Requires Shipping' => 'variantRequiresShipping',
				'Images' => 'images',
				'Upload Video' => 'uploadVideo',
				'Barcode (ISBN, UPC, GTIN, etc.)' => 'barcode',
				'Refund Policy' => 'refundPolicy',
				'Status' => 'status',
				'Google Shopping Category' => 'googleShoppingCategory',
				'Google Shopping Mpn' => 'googleShoppingMpn',
				'Is Featured' => 'isFeatured',
				// 'Weight Option' => 'weightOption',
				// 'Weight' => 'weight',
				// 'Dimension Option' => 'dimensionOption',
				// 'Length' => 'length',
				// 'Width' => 'width',
				// 'Height' => 'height',
				// 'Depth' => 'depth',
				// 'Shipping Weight Option' => 'shippingWeightOption',
				// 'Shipping Weight' => 'shippingWeight',
				// 'Shipping Dimension Option' => 'shippingDimensionOption',
				// 'Shipping Width' => 'shippingWidth',
				// 'Shipping Depth' => 'shippingDepth',
				// 'Shipping Height' => 'shippingHeight',
				// 'Shipping Length' => 'shippingLength',
				'Frequently Bought Together' => 'frequentlyBoughtTogether',
				// 'Compare Products' => 'compareProducts',
				'Variant 1 Title' => 'variant1Title',
				'Variant 1 Value' => 'variant1Value',
				'Variant 1 Products' => 'variant1Products',
				'Variant 2 Title' => 'variant2Title',
				'Variant 2 Value' => 'variant2Value',
				'Variant 2 Products' => 'variant2Products',
				'Variant 3 Title' => 'variant3Title',
				'Variant 3 Value' => 'variant3Value',
				'Variant 3 Products' => 'variant3Products',
				'Variant Color Title' => 'variantColorTitle',
				'Variant Color Value' => 'variantColorValue',
				'Variant Color Products' => 'variantColorProducts',
			],

			'SEO_SECTION' => [
				"Meta Title" => "meta_title",
				"Meta Description" => "meta_description",
			],

			'DISCOUNT_SECTION' => [
				'Buying Quantity1' => 'buyingQuantity1',
				'Discount1' => 'discount1',
				'Start Date1' => 'startDate1',
				'End Date1' => 'endDate1',
				'Buying Quantity2' => 'buyingQuantity2',
				'Discount2' => 'discount2',
				'Start Date2' => 'startDate2',
				'End Date2' => 'endDate2',
				'Buying Quantity3' => 'buyingQuantity3',
				'Discount3' => 'discount3',
				'Start Date3' => 'startDate3',
				'End Date3' => 'endDate3',
			],

			'TRANSLATION_SECTION' => [
				'Name (AR)' => 'nameAr',
				'Description (AR)' => 'descriptionAr',
				'Content (AR)' => 'contentAr',
				'Warranty Information (AR)' => 'warrantyInformationAr',
			],
		];

		return $key ? ($constants[$key] ?? []) : $constants;
	}
}

if (!function_exists('seo_import_constants')) {
	function seo_import_constants($key = null) {
		$constants = [
			'ALL_FIELDS' => [
				'Relational Name' => 'relational_name',
				'Relational ID' => 'relational_id',
				'Relational Type' => 'relational_type',
				'URL' => 'url',
				'Primary Keyword' => 'primary_keyword',
				'Primary Monthly Search Volume' => 'primary_monthly_search_volume',
				'Secondary Keyword' => 'secondary_keyword',
				'Secondary Monthly Search Volume' => 'secondary_monthly_search_volume',
				'Title Tag' => 'title_tag',
				'Meta Title' => 'meta_title',
				'Meta Description' => 'meta_description',
				'Internal Links(Separated By |)' => 'internal_links',
				'Indexing' => 'indexing',
				'Og Title' => 'og_title',
				'Og Description' => 'og_description',
				'Og Image URL' => 'og_image_url',
				'Og Image Alt Text' => 'og_image_alt_text',
				'Og Image Name' => 'og_image_name',
				'Tags(Separated By |)' => 'tags',
				// 'paragraph 1' => 'paragraph_1',
				// 'paragraph 2' => 'paragraph_2',
				// 'paragraph 3' => 'paragraph_3',
				// 'paragraph 4' => 'paragraph_4',
				// 'Popular Tags' => 'popular_tags',
			],
		];

		return $key ? ($constants[$key] ?? []) : $constants;
	}
}

if (!function_exists('convert_unit')) {
	function convert_unit(string $type, float $value, string $fromUnit, string $toUnit): float|string
	{
		try {
			$quantityClassMap = [
				'length' => Length::class,
				'mass' => Mass::class,
				'volume' => Volume::class,
				'temperature' => Temperature::class,
				'time' => Time::class,
				'speed' => Speed::class,
				'area' => Area::class,
				'energy' => Energy::class,
				'pressure' => Pressure::class,
				'force' => Force::class,
			];

			$type = strtolower($type);
			if (!isset($quantityClassMap[$type])) {
				return "Unsupported type: $type";
			}

			$quantityClass = $quantityClassMap[$type];
			$quantity = new $quantityClass($value, $fromUnit);

			return $quantity->toUnit($toUnit);
		} catch (\Exception $e) {
			return "Conversion error: " . $e->getMessage();
		}
	}
}

function uploadImageToWebpS3FromFile(Request $request, string $key, string $pathPrefix)
{
	if (!$request->hasFile($key) || !$request->file($key)->isValid()) {
		return null;
	}

	try {
		$file = $request->file($key);
		$image = imagecreatefromstring(file_get_contents($file->getRealPath()));
		if (!$image) {
			Log::error('Failed to create image from file.');
			return null;
		}

		if (!imageistruecolor($image)) {
			imagepalettetotruecolor($image);
		}

		ob_start();
		imagewebp($image);
		$webpData = ob_get_clean();
		imagedestroy($image);

		$filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
		$uniqueName = $filename . '_' . time() . '.webp';
		$path = "{$pathPrefix}/{$uniqueName}";

		Storage::disk('s3')->put($path, $webpData);

		return Storage::disk('s3')->url($path);
	} catch (\Exception $e) {
		Log::error('uploadImageToWebpS3FromFile error: ' . $e->getMessage());
		return null;
	}
}

function uploadFileToS3($file, $path)
{
	$filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
	return Storage::disk('s3')->putFileAs($path, $file, $filename, 'public') ? Storage::disk('s3')->url("$path/$filename") : null;
}
