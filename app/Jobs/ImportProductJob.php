<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Batchable;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception, Throwable;
use Illuminate\Support\Facades\Storage;

use App\Models\Brand;
use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Store;
use App\Models\MetaBox;
use App\Models\Slug;
use App\Models\Discount;
use App\Models\DiscountProduct;
use App\Models\TransactionLog;
use App\Models\UnitOfMeasurement;

class ImportProductJob implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;
	public $timeout = 43200;

	protected $header;
	protected $chunk;
	protected $userId;
	protected $categoryIdNames;
	protected $tagIdNames;
	protected $productFileFormatArray;

	public function __construct($data)
	{
		$this->header = $data['header'];
		$this->chunk = $data['chunk'];
		$this->userId = $data['userId'];
		$this->productFileFormatArray = $data['productFileFormatArray'];
	}

	public function handle()
	{
		$brandIdNames = Brand::pluck('name', 'id')->all();
		$storeIdNames = Store::pluck('name', 'id')->all();
		$this->categoryIdNames = Category::whereDoesntHave('children')->pluck('name', 'id')->all();
		$this->tagIdNames = Tag::pluck('name', 'id')->all();
		$SKUs = Product::pluck('sku', 'id')->all();

		$log = TransactionLog::where('identifier', $this->batch()->id)->first();
		$descArray = json_decode($log->description, true) ?? ["Errors" => ''];
		$previousSuccessCount = $descArray["Success Count"] ?? 0;
		$previousFailedCount = $descArray["Failed Count"] ?? 0;

		$errorArray = [];
		$success = 0;
		$failed = 0;

		foreach ($this->chunk as $row) {
			$rowData = [];
			$rowError = [];
			if (count($this->header) == count($row)) {
				$rowData = array_combine($this->header, $row);
			} else {
				$rowError[] = 'The data in this row is not compatible for import.';
				$errorArray[] = [
					"Row Number" => $failed + $success + 2 + $previousSuccessCount + $previousFailedCount,
					"Error" => implode(' | ', $rowError)
				];
				$failed++;
				continue;
			}

			foreach ($this->productFileFormatArray as $headerKey => $variableName) {
				if (in_array($headerKey, $this->header)) {
					${$variableName} = trim($rowData[$headerKey]);
				}
			}

			/* Required data validation */
			if (empty($url) || empty($name) || empty($sku) || empty($brand) || empty($vendor) || empty($category) || empty($status)) {
				$rowError[] = 'One or more required fields are missing.';
				$errorArray[] = [
					"Row Number" => $failed + $success + 2 + $previousSuccessCount + $previousFailedCount,
					"Error" => implode(' | ', $rowError),
				];
				$failed++;
				continue;
			}

			if (!empty($id)) {
				$product = Product::find($id);
				if (!$product) {
					$rowError[] = 'Product does not exist with the given ID.';
					$errorArray[] = [
						"Row Number" => $failed + $success + 2 + $previousSuccessCount + $previousFailedCount,
						"Error" => implode(' | ', $rowError),
					];
					$failed++;
					continue;
				}

				/* Check if SKU is changed and is already taken by another product */
				if (!empty($product->sku) && $product->sku !== $sku && in_array($sku, $SKUs)) {
					$existingId = array_search($sku, $SKUs);
					$rowError[] = "SKU already exists with ID: $existingId.";
					$errorArray[] = [
						"Row Number" => $failed + $success + 2 + $previousSuccessCount + $previousFailedCount,
						"Error" => implode(' | ', $rowError),
					];
					$failed++;
					continue;
				}
			} else {
				$product = new Product();

				/* Check if SKU already exists in the database */
				if (!empty($sku) && in_array($sku, $SKUs)) {
					$existingId = array_search($sku, $SKUs);
					$rowError[] = "SKU already exists with ID: $existingId.";
					$errorArray[] = [
						"Row Number" => $failed + $success + 2 + $previousSuccessCount + $previousFailedCount,
						"Error" => implode(' | ', $rowError),
					];
					$failed++;
					continue;
				}
			}

			/* Brand validation */
			if (!in_array($brand, array_values($brandIdNames))) {
				$rowError[] = "$brand brand does not exist.";
			} else {
				$brandId = array_search($brand, $brandIdNames);
			}

			/* Vendor validation */
			if (!in_array($vendor, array_values($storeIdNames))) {
				$rowError[] = "$vendor vendor does not exist.";
			} else {
				$storeId = array_search($vendor, $storeIdNames);
			}

			/* Category validation */
			$lowercaseCategory = strtolower($category);
			$lowercaseCategoryIdNames = array_change_key_case(array_flip($this->categoryIdNames), CASE_LOWER);
			if (array_key_exists($lowercaseCategory, $lowercaseCategoryIdNames)) {
				$categoryId = $lowercaseCategoryIdNames[$lowercaseCategory];
			} else {
				$rowError[] = "$category category does not exist or is not a valid lowest-level category.";
			}

			$usStatusArray = [
				1 => "published",
				2 => "draft",
				3 => "pending"
			];
			/* Status validation */
			if (!is_numeric($status) || !in_array($status, [1, 2, 3])) {
				$rowError[] = "Status should be numeric and either 1 for Published, 2 for Draft, or 3 for Pending.";
			} else {
				$status = $usStatusArray[$status];
			}

			/* Additional field validations */

			/* Stock status validation */
			$usStockStatusArray = [
				1 => "in_stock",
				2 => "out_of_stock",
				3 => "on_backorder"
			];

			if ($stockStatus) {
				if (!is_numeric($stockStatus) || !array_key_exists((int) $stockStatus, $usStockStatusArray)) {
					$rowError[] = "Stock status should be numeric and either 1 for In-Stock, 2 for Out of Stock, or 3 for Pre Order.";
				} else {
					$stockStatus = $usStockStatusArray[(int) $stockStatus];
				}
			} else {
				$stockStatus = null;
			}

			/* With storehouse management validation (Check for 0 if empty) */
			if ($withStorehouseManagement !== '' && (!is_numeric($withStorehouseManagement) || !in_array($withStorehouseManagement, [0, 1]))) {
				$rowError[] = "With storehouse management should be numeric and either 1 for Yes, or 0 for No.";
			} else {
				$withStorehouseManagement = $withStorehouseManagement !== '' ? (int) $withStorehouseManagement : 0;
			}

			/* Unit of measurement validation */
			$usUnitOfMeasurementArray = UnitOfMeasurement::pluck('name', 'id')->all();
			if ($unitOfMeasurement && (!is_numeric($unitOfMeasurement) || !array_key_exists((int) $unitOfMeasurement, $usUnitOfMeasurementArray))) {
				$rowError[] = "Unit of measurement should be numeric and either 1 for Each, 2 for Dozen, 3 for Box, or 4 for Case.";
			} else {
				$unitOfMeasurementID = $unitOfMeasurement ? $unitOfMeasurement : null;
			}

			/* Variant requires shipping validation (Check for 0 if empty) */
			if ($variantRequiresShipping !== '' && (!is_numeric($variantRequiresShipping) || !in_array($variantRequiresShipping, [0, 1]))) {
				$rowError[] = "Variant requires shipping should be numeric and either 1 for Yes, or 0 for No.";
			} else {
				$variantRequiresShipping = $variantRequiresShipping !== '' ? (int) $variantRequiresShipping : null;
			}

			/* Refund policy validation */
			$usRefundPolicyArray = [
				1 => "non-refundable",
				2 => "15 days",
				3 => "90 days"
			];
			if ($refundPolicy && (!is_numeric($refundPolicy) || !in_array($refundPolicy, [1, 2, 3]))) {
				$rowError[] = "Refund policy should be numeric and either 1 for Non-Refundable, 2 for 15 Days Refund, or 3 for 90 Days Refund.";
			} else {
				$refundPolicy = $refundPolicy ? $usRefundPolicyArray[$refundPolicy] ?? null : null;
			}

			/* Is featured validation (Check for 0 if empty) */
			if ($isFeatured !== '' && (!is_numeric($isFeatured) || !in_array($isFeatured, [0, 1]))) {
				$rowError[] = "Is featured should be numeric and either 1 for Enable, or 0 for Disable.";
			} else {
				$isFeatured = $isFeatured !== '' ? (int) $isFeatured : 0;
			}

			/* Weight option validation */
			$usWeightArray = [
				5 => "kg",
				6 => "g",
				9 => "lbs",
			];
			if ($weightOption && !in_array($weightOption, ['lbs', 'kg', 'g'])) {
				$rowError[] = "Weight option should be 'lbs', 'kg', or 'g'.";
			} else {
				$weightOption = $weightOption ? array_search($weightOption, $usWeightArray) : 9;
			}

			/* Dimension option validation */
			$usDimensionArray = [
				1 => "cm",
				3 => "inch",
				11 => "mm",
			];
			if ($dimensionOption && !in_array($dimensionOption, ['inch', 'cm', 'mm'])) {
				$rowError[] = "Dimension option should be 'inch', 'cm', or 'mm'.";
			} else {
				$dimensionOption = $dimensionOption ? array_search($dimensionOption, $usDimensionArray) : 3;
			}

			/* Shipping weight option validation */
			if ($shippingWeightOption && !in_array($shippingWeightOption, ['lbs', 'kg', 'g'])) {
				$rowError[] = "Shipping weight option should be 'lbs', 'kg', or 'g'.";
			} else {
				$shippingWeightOption = $shippingWeightOption ? $shippingWeightOption : 'lbs';
			}

			/* Shipping dimension option validation */
			if ($shippingDimensionOption && !in_array($shippingDimensionOption, ['inch', 'cm', 'mm'])) {
				$rowError[] = "Shipping dimension option should be 'inch', 'cm', or 'mm'.";
			} else {
				$shippingDimensionOption = $shippingDimensionOption ? $shippingDimensionOption : 'inch';
			}

			$frequentlyBoughtTogether = trim($rowData['Frequently Bought Together']);
			if ($frequentlyBoughtTogether) {
				$frequentlyBoughtTogether = json_encode(array_map(fn($value) => ['value' => trim($value)], explode(',', $frequentlyBoughtTogether)));
			} else {
				$frequentlyBoughtTogether = null;
			}

			$compareProducts = trim($rowData['Compare Products']);
			if ($compareProducts) {
				$compareProductsArray = array_unique(array_map(fn($value) => trim($value), explode(',', $compareProducts)));
				$compareProducts = !empty($compareProductsArray) ? json_encode($compareProductsArray) : null;
			} else {
				$compareProducts = null;
			}

			if ($price && $salePrice && $price < $salePrice) {
				$rowError[] = "The sale price must be less than the price.";
			}

			if ($rowError) {
				$errorArray[] = [
					"Row Number" => $failed + $success + 2 + $previousSuccessCount + $previousFailedCount,
					"Error" => implode(' | ', $rowError),
				];
				$failed++;
				continue;
			}

			/* Process Images */
			$fetchedImages = $this->getImageURLs((array) $images ?? []);


			/* Get Sale Type */
			$saleType = ($startDateSalePrice || $endDateSalePrice) ? 1 : 0;

			/* Set Quantity */
			if (!$withStorehouseManagement) {
				$quantity = null;
			}

			// Wrap in a transaction
			DB::beginTransaction();

			try {
				/*************/
				$product->name = $name;
				$product->description = !empty($description) ? $description : null;
				$product->content = !empty($content) ? $content : null;
				$product->warranty_information = !empty($warrantyInformation) ? $warrantyInformation : null;
				$product->sku = $sku;
				$product->status = $status;
				$product->delivery_days = !empty($deliveryDays) ? $deliveryDays : null;
				$product->is_featured = $isFeatured;
				$product->brand_id = $brandId;
				$product->images = json_encode($fetchedImages);
				$product->image = $fetchedImages[0] ?? null;
				$product->video_path = $uploadVideo;
				$product->stock_status = $stockStatus;
				$product->with_storehouse_management = $withStorehouseManagement;
				$product->unit_of_measurement_id = $unitOfMeasurementID;
				$product->quantity = !empty($quantity) ? $quantity : null;
				$product->cost_per_item = !empty($costPerItem) ? $costPerItem : null;
				$product->price = !empty($price) ? $price : null;
				$product->sale_price = !empty($salePrice) ? $salePrice : null;
				$product->start_date = !empty($startDateSalePrice) ? Carbon::parse($startDateSalePrice) : null;
				$product->end_date = !empty($endDateSalePrice) ? Carbon::parse($endDateSalePrice) : null;
				$product->sale_type = $saleType;
				$product->weight = !empty($weight) ? $weight : null;
				$product->weight_unit_id = $weightOption;
				$product->length = !empty($length) ? $length : null;
				$product->length_unit_id = $dimensionOption;
				$product->width = !empty($width) ? $width : null;
				$product->height = !empty($height) ? $height : null;
				$product->depth = !empty($depth) ? $depth : null;
				$product->shipping_weight_option = $shippingWeightOption;
				$product->shipping_weight = !empty($shippingWeight) ? $shippingWeight : null;
				$product->shipping_dimension_option = $shippingDimensionOption;
				$product->shipping_width = !empty($shippingWidth) ? $shippingWidth : null;
				$product->shipping_depth = !empty($shippingDepth) ? $shippingDepth : null;
				$product->shipping_height = !empty($shippingHeight) ? $shippingHeight : null;
				$product->shipping_length = !empty($shippingLength) ? $shippingLength : null;
				$product->frequently_bought_together = $frequentlyBoughtTogether;
				// $product->compare_type = !empty($compareType) ? $compareType : null;
				$product->compare_products = $compareProducts;
				$product->refund = $refundPolicy;
				$product->currency_id = 1;
				$product->variant_1_title = !empty($variant1Title) ? $variant1Title : null;
				$product->variant_1_value = !empty($variant1Value) ? $variant1Value : null;
				$product->variant_1_products = !empty($variant1Products) ? $variant1Products : null;
				$product->variant_2_title = !empty($variant2Title) ? $variant2Title : null;
				$product->variant_2_value = !empty($variant2Value) ? $variant2Value : null;
				$product->variant_2_products = !empty($variant2Products) ? $variant2Products : null;
				$product->variant_3_title = !empty($variant3Title) ? $variant3Title : null;
				$product->variant_3_value = !empty($variant3Value) ? $variant3Value : null;
				$product->variant_3_products = !empty($variant3Products) ? $variant3Products : null;
				$product->variant_color_title = !empty($variantColorTitle) ? $variantColorTitle : null;
				$product->variant_color_value = !empty($variantColorValue) ? $variantColorValue : null;
				$product->variant_color_products = !empty($variantColorProducts) ? $variantColorProducts : null;
				$product->barcode = !empty($barcode) ? $barcode : null;
				$product->minimum_order_quantity = !empty($minimumOrderQuantity) ? $minimumOrderQuantity : 0;
				$product->variant_requires_shipping = $variantRequiresShipping;
				$product->google_shopping_category = !empty($googleShoppingCategory) ? $googleShoppingCategory : null;
				$product->google_shopping_mpn = !empty($googleShoppingMpn) ? $googleShoppingMpn : null;
				$product->box_quantity = !empty($boxQuantity) ? $boxQuantity : null;

				$product->store_id = $storeId;
				$product->created_at = now();
				$product->updated_at = now();
				$product->created_by_id = $this->userId;
				$product->created_by_type = User::class;
				$product->save();

				$SKUs[$product->id] = $sku;

				// $this->saveProductProductType($product, $productTypes);
				// $categoryIdArray = $this->changeCategoryNameToId($categories);
				// $this->saveProductCategory($product, $categoryIdArray);
				$this->saveProductCategory($product, $categoryId);
				$this->saveProductTag($product, $tags);
				// $this->saveSeoMetaData($product, $seoTitle, $seoDescription);
				$this->saveSlugData($product, $url);
				$this->saveTranslation($product, $rowData);
				$this->saveDiscount($product, $rowData);

				DB::commit();

				$success++;
			} catch (\Exception $e) {
				DB::rollBack();

				$rowError[] = 'Error processing row: ' . $e->getMessage();
				$rowError[] = 'File: ' . $e->getFile();
				$rowError[] = 'Line: ' . $e->getLine();
				$errorArray[] = [
					"Row Number" => $failed + $success + 2 + $previousSuccessCount + $previousFailedCount,
					"Error" => implode(' | ', $rowError),
				];
				$failed++;
			}
		}

		/* Update Transaction Log */
		$log = TransactionLog::where('identifier', $this->batch()->id)->first();
		$descArray = json_decode($log->description, true) ?? ["Errors" => ''];
		$descArray["Success Count"] = $descArray["Success Count"] + $success;
		$descArray["Failed Count"] = $descArray["Failed Count"] + $failed;
		$descArray["Errors"] = array_merge($descArray["Errors"], $errorArray);

		TransactionLog::where('id', $log->id)->update([
			'description' => json_encode($descArray),
		]);
	}

	private function saveProductCategory($product, $categoryId)
	{
		/* Step 1: Fetch existing pivot data for the product */
		$existingCategories = $product->categories()->pluck('category_id')->toArray();

		if (!in_array($categoryId, $existingCategories)) {
			/* Clear existing specs if the category is different */
			$product->specifications()->delete();
		}

		/* Step 2: Prepare the category for syncing */
		$categoryWithTimestamp = in_array($categoryId, $existingCategories)
		? [$categoryId => []]
		: [$categoryId => ['created_at' => now()]];

		/* Step 3: Sync the single category */
		$product->categories()->sync($categoryWithTimestamp);
	}

	private function saveProductTag($product, string $tags)
	{
		$tagIds = [];
		$tagNames = explode(',', $tags);

		foreach ($tagNames as $tagName) {
			$trimmedName = trim($tagName);
			if (empty($trimmedName)) {
				continue;
			}
			$tagId = array_search($trimmedName, $this->tagIdNames);
			if ($tagId !== false) {
				$tagIds[] = $tagId;
			} else {
				$tag = Tag::create(['name' => $trimmedName]);
				$tagIds[] = $tag->id;

				// Update the tagIdNames array with the new tag
				$this->tagIdNames[$tag->id] = $trimmedName;
			}
		}
		$product->tags()->sync($tagIds);
	}

	private function saveSeoMetaData($product, $seoTitle, $seoDescription)
	{
		/* Retrieve or create the SEO metadata */
		$seoMetaData = $product->seoMetaData ?: new MetaBox([
			'meta_key' => 'seo_meta',
			'reference_id' => $product->id,
			'reference_type' => Product::class,
		]);

		/* Decode existing meta_value if present */
		$existingMetaValue = is_array($seoMetaData->meta_value)
		? $seoMetaData->meta_value
		: (json_decode($seoMetaData->meta_value, true) ?? []);

		/* Ensure $existingMetaValue is an array */
		if (!is_array($existingMetaValue)) {
			$existingMetaValue = [];
		}

		$updatedMetaValue = [];
		if (!empty($seoTitle)) {
			$updatedMetaValue['seo_title'] = $seoTitle;
		}

		if (!empty($seoDescription)) {
			$updatedMetaValue['seo_description'] = $seoDescription;
		}
		$updatedMetaValue['index'] = $existingMetaValue['index'] ?? 'index';

		/* Store the updated meta value as an array */
		$seoMetaData->meta_value = [$updatedMetaValue];

		/* Save the updated meta data */
		$seoMetaData->save();
	}

	private function saveSlugData($product, $url)
	{
		if (strpos($url, '/products/') !== false) {
			$urlParts = explode('/products/', $url);
			$outputUrl = $urlParts[1];
		} else {
			$outputUrl = null; // Handle the case where "/products/" is not found
		}
		/* Retrieve or create the slug data */
		$slugData = $product->slugData ?: new Slug([
			'prefix' => 'products',
			'reference_id' => $product->id,
			'reference_type' => Product::class,
		]);

		$slugData->key = $outputUrl;

		$slugData->save();
	}

	private function saveTranslation($product, $rowData)
	{
		if (!empty(trim($rowData['Name (AR)'] ?? '')) || !empty(trim($rowData['Description (AR)'] ?? '')) || !empty(trim($rowData['Content (AR)'] ?? '')) || !empty(trim($rowData['Warranty Information (AR)'] ?? ''))) {
			$checkExist = $product->translations()->where('lang_code', 'ar')->first();

			if ($checkExist) {
				$checkExist->update([
					'name' => $rowData['Name (AR)'],
					'description' => $rowData['Description (AR)'],
					'content' => $rowData['Content (AR)'],
					'warranty_information' => $rowData['Warranty Information (AR)'],
				]);
			} else {
				$product->translations()->create([
					'lang_code' => 'ar',
					'ec_products_id' => $product->id,
					'name' => $rowData['Name (AR)'],
					'description' => $rowData['Description (AR)'],
					'content' => $rowData['Content (AR)'],
					'warranty_information' => $rowData['Warranty Information (AR)'],
				]);
			}
		}
	}

	private function saveDiscount($product, $rowData)
	{
		$requiredFieldValues = [
			'quantity1' => $rowData['Buying Quantity1'] ?? null,
			'value1' => $rowData['Discount1'] ?? null,
			'start_date1' => $rowData['Start Date1'] ?? null,
			'quantity2' => $rowData['Buying Quantity2'] ?? null,
			'value2' => $rowData['Discount2'] ?? null,
			'start_date2' => $rowData['Start Date2'] ?? null,
		];

		$requiredFieldsProvided = !empty($requiredFieldValues['quantity1']) && !empty($requiredFieldValues['value1']) && !empty($requiredFieldValues['start_date1']) && !empty($requiredFieldValues['quantity2']) && !empty($requiredFieldValues['value2']) && !empty($requiredFieldValues['start_date2']);
		if ($requiredFieldsProvided) {
			for ($i = 1; $i <= 3; $i++) {
				// Check if the current iteration is optional (3rd discount)
				$isOptional = ($i === 3);

				// Required fields for discounts
				$requiredFields = [
					'quantity' => $rowData['Buying Quantity' . $i] ?? null,
					'value' => $rowData['Discount' . $i] ?? null,
					'start_date' => $rowData['Start Date' . $i] ?? null,
				];

				// Check if all required fields are non-empty
				$allFieldsProvided = !empty($requiredFields['quantity']) && !empty($requiredFields['value']) && !empty($requiredFields['start_date']);

				// Validate required fields for discounts
				if ($allFieldsProvided) {
					$discount = new Discount();
					$discount->product_quantity = $requiredFields['quantity'];
					$discount->title = $discount->product_quantity . ' products';
					$discount->type_option = 'percentage';
					$discount->type = 'promotion';
					$discount->value = $requiredFields['value'];
					$discount->start_date = !empty($requiredFields['start_date']) ? Carbon::parse($requiredFields['start_date']) : null;
					$discount->end_date = !empty($rowData['End Date' . $i]) ? Carbon::parse($rowData['End Date' . $i]) : null;
					$discount->save();

					// Associate the discount with the product
					$discountProduct = new DiscountProduct();
					$discountProduct->discount_id = $discount->id;
					$discountProduct->product_id = $product->id;
					$discountProduct->save();
				}
			}
		}
	}

	protected function getImageURLs(array $images): array
	{
		/* Ensure images are properly formatted and split if needed */
		$images = array_values(array_filter(
			array_map('trim', preg_split('/\s*,\s*/', implode(',', $images)))
		));

		Log::info('Image URLs before processing: ' . json_encode($images));

		foreach ($images as $key => $image) {
			if (Str::startsWith($image, 'https://horecastore-s3-storage.s3.us-west-1.amazonaws.com/')) {
				$images[$key] = $image;
			} else {
				if (Str::startsWith($image, ['http://', 'https://'])) {
					$uploadedImage = $this->uploadImageFromURL($image);
					$images[$key] = $uploadedImage;
				} else {
					Log::warning("Invalid image URL at index $key: " . $image);
					unset($images[$key]);
				}
			}
		}

		Log::info('Final Processed Images: ' . json_encode(array_values($images)));
		return array_values($images);
	}

	protected function uploadImageFromURL(?string $url): ?string
	{
		$s3Disk = Storage::disk('s3');

		// Validate URL
		if (!filter_var($url, FILTER_VALIDATE_URL)) {
			Log::error('Invalid URL provided: ' . $url);
			return null;
		}

		// Fetch image content
		$imageContents = file_get_contents($url);
		if ($imageContents === false || empty($imageContents)) {
			Log::error('Failed to download image from URL or content is empty: ' . $url);
			return null;
		}

		// Sanitize file name
		$fileNameWithQuery = basename(parse_url($url, PHP_URL_PATH));
		$fileName = preg_replace('/\?.*/', '', $fileNameWithQuery);
		$fileBaseName = pathinfo($fileName, PATHINFO_FILENAME);
		$fileExtension = 'webp'; // Convert all to WebP

		if (empty($fileBaseName)) {
			Log::error('Invalid file name extracted from URL: ' . $url);
			return null;
		}

		// Define sizes
		$sizes = [
			'thumb' => [150, 150],
			'medium' => [300, 300],
			'large' => [790, 510]
		];

		$imageUrl = '';

		try {
			// Create image resource from content
			$image = imagecreatefromstring($imageContents);
			if (!$image) {
				Log::error('Failed to create image from URL: ' . $url);
				return null;
			}

			/* Ensure image is in Truecolor format */
			if (imageistruecolor($image) === false) {
				imagepalettetotruecolor($image);
			}

			// Save original image
			$originalPath = env('STORAGE_ENV', 'tanuj_local')."/products/{$fileBaseName}.{$fileExtension}";
			ob_start();
			imagewebp($image);
			$originalData = ob_get_clean();
			$s3Disk->put($originalPath, $originalData);
			$imageUrl = $s3Disk->url($originalPath);

			// Resize and save images
			foreach ($sizes as $sizeName => [$width, $height]) {
				$resizedImage = $this->resizeImageGD($image, $width, $height);
				if (!$resizedImage) {
					continue;
				}

				$resizedPath = env('STORAGE_ENV', 'tanuj_local')."/products/{$fileBaseName}-{$width}x{$height}.{$fileExtension}";
				ob_start();
				imagewebp($resizedImage);
				$resizedData = ob_get_clean();
				$s3Disk->put($resizedPath, $resizedData);
				// $imageUrls[$sizeName] = $s3Disk->url($resizedPath);
			}

			imagedestroy($image);
			Log::info('Uploaded Images: ' . $imageUrl);
			return $imageUrl;
		} catch (\Exception $e) {
			Log::error('S3 Upload Error: ' . $e->getMessage());
			return null;
		}
	}

	protected function resizeImageGD($image, $newWidth, $newHeight)
	{
		$width = imagesx($image);
		$height = imagesy($image);

		// Create new image canvas with exact width & height
		$resizedImage = imagecreatetruecolor($newWidth, $newHeight);
		imagealphablending($resizedImage, false);
		imagesavealpha($resizedImage, true);
		$transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
		imagefill($resizedImage, 0, 0, $transparent);

		// Force resize without aspect ratio (stretching)
		imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

		return $resizedImage;
	}

	/**
	 * Handle a job failure.
	 */
	public function failed(Throwable $exception): void
	{
		$error = $exception->getMessage().$exception->getTraceAsString();
		logger(__("Product Import Error").': '.$error);
	}
}
