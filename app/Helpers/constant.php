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
use Illuminate\Support\Facades\Http;

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
				"tags" => "Tags",
				"images" => "Images",
				"upload_video" => "Upload Video",
				"barcode" => "Barcode (ISBN, UPC, GTIN, etc.)",
				"status" => "Status",
				"google_shopping_category" => "Google Shopping Category",
				"google_shopping_mpn" => "Google Shopping Mpn",
				"is_featured" => "Is Featured",
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
				'Tags' => 'tags',
				'Images' => 'images',
				'Upload Video' => 'uploadVideo',
				'Barcode (ISBN, UPC, GTIN, etc.)' => 'barcode',
				'Status' => 'status',
				'Google Shopping Category' => 'googleShoppingCategory',
				'Google Shopping Mpn' => 'googleShoppingMpn',
				'Is Featured' => 'isFeatured',
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

if (!function_exists('getDateRange')) {
	function getDateRange(Carbon\Carbon|string $createdAt, string $deliveryDays): string
	{
		$createdAt = $createdAt instanceof \Carbon\Carbon
		? $createdAt->copy()
		: \Carbon\Carbon::parse($createdAt);

		$deliveryDays = trim($deliveryDays);
		$isWeekFormat = str_contains($deliveryDays, 'Week');
		$isRange = str_contains($deliveryDays, ' to ');

		if ($isWeekFormat) {
			$range = explode(' to ', str_replace([' Weeks', ' Week'], '', $deliveryDays));

			if (count($range) === 1) {
				$minDays = $maxDays = ((int) $range[0]) * 7;
			} else {
				$minDays = ((int) $range[0]) * 7;
				$maxDays = ((int) $range[1]) * 7;
			}

			$startDate = $createdAt->copy()->addDays($minDays);
			$endDate = $createdAt->copy()->addDays($maxDays);
		} else {
			$range = explode(' to ', str_replace(' Days', '', $deliveryDays));

			if (count($range) === 1) {
				$minDays = $maxDays = (int) $range[0];
			} else {
				$minDays = (int) $range[0];
				$maxDays = (int) $range[1];
			}

			$addBusinessDays = function ($date, $days) {
				$businessDaysAdded = 0;
				while ($businessDaysAdded < $days) {
					$date->addDay();
					if (!in_array($date->dayOfWeek, [Carbon\Carbon::SATURDAY, Carbon\Carbon::SUNDAY])) {
						$businessDaysAdded++;
					}
				}
				return $date;
			};

			$startDate = $addBusinessDays(clone $createdAt, $minDays);
			$endDate = $addBusinessDays(clone $createdAt, $maxDays);
		}

		return $isRange
		? $startDate->format('D, F j') . ' - ' . $endDate->format('D, F j')
		: $startDate->format('D, F j');
	}
}

function convertNumberToWords($amount, $currencyMain = 'U.S. Dollars', $currencyFraction = 'Cents')
{
	$amount = str_replace(',', '', $amount);
	$number = floor($amount);
	$decimal = round(($amount - $number) * 100);

	$words = [
		0 => 'Zero', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four',
		5 => 'Five', 6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine',
		10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen',
		14 => 'Fourteen', 15 => 'Fifteen', 16 => 'Sixteen',
		17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen',
		20 => 'Twenty', 30 => 'Thirty', 40 => 'Forty', 50 => 'Fifty',
		60 => 'Sixty', 70 => 'Seventy', 80 => 'Eighty', 90 => 'Ninety'
	];

	$units = ['', 'Thousand', 'Million', 'Billion'];
	$str = [];

	$i = 0;
	while ($number > 0) {
		$chunk = $number % 1000;
		if ($chunk) {
			$chunkWords = [];

			$hundreds = floor($chunk / 100);
			$tensUnits = $chunk % 100;

			if ($hundreds) {
				$chunkWords[] = $words[$hundreds] . ' Hundred';
			}

			if ($tensUnits > 0) {
				if ($tensUnits < 21) {
					$chunkWords[] = $words[$tensUnits];
				} else {
					$tens = floor($tensUnits / 10) * 10;
					$unitsDigit = $tensUnits % 10;
					$chunkWords[] = $words[$tens] . ($unitsDigit ? '-' . $words[$unitsDigit] : '');
				}
			}

			$part = implode(' ', $chunkWords);
			$str[] = trim($part . ' ' . $units[$i]);
		}

		$number = floor($number / 1000);
		$i++;
	}

	$whole = implode(', ', array_reverse($str));
	$whole = preg_replace('/\s+/', ' ', $whole);

	$fraction = $decimal > 0 ? ' and ' . convertNumberToWords($decimal, $currencyFraction, '') : '';

	return trim("$whole $currencyMain$fraction Only");
}

function getBase64Image($url)
{
	try {
		$response = Http::timeout(10)->get($url);

		if ($response->successful()) {
			$contentType = $response->header('Content-Type');
			$base64 = base64_encode($response->body());

			return "data:{$contentType};base64,{$base64}";
		}
	} catch (\Exception $e) {
	}

	return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAE/wJLCNRVswAAAABJRU5ErkJggg==';
}

if (!function_exists('glitch_error_reporting_mails')) {
	function glitch_error_reporting_mails() {
		$mails = [
			'nomanpeera@horecastore.ae',
			'nomanpeera@gmail.com',
			'aksitbhardwaj@gmail.com',
			'asharKhursheed26@gmail.com',
			'sales@thehorecastore.com',
		];

		// $mails = [
		// 	'aksitbhardwaj@gmail.com',
		// 	'webdeveloper04@horecastore.ae',
		// ];

		return $mails;
	}
}

if (!function_exists('order_cc_mails')) {
	function order_cc_mails() {
		$mails = [
			'ofm@thehorecastore.com',
			'nomanpeera@horecastore.ae',
			'shehzad@rapid-supplies.com',
			'ofs@thehorecastore.com',
			'mfaizan@rapid-supplies.com',
			'ofs02@thehorecastore.com'
		];

		return $mails;
	}
}
