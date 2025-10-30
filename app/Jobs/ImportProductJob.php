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
use App\Models\MetaBox;
use App\Models\Slug;
// use App\Models\Discount;
// use App\Models\DiscountProduct;
use App\Models\TransactionLog;
use App\Models\Faq;

class ImportProductJob implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

	protected $header;
	protected $chunk;
	protected $userId;
	protected $categoryIdNames;
	protected $tagIdNames;
	protected $productFileFormatArray;
	protected $userRole;

	public function __construct($data)
	{
		$this->header = $data['header'];
		$this->chunk = $data['chunk'];
		$this->userId = $data['userId'];
		$this->productFileFormatArray = $data['fileFormatArray'];
		$this->userRole = $data['userRole'];

	}

	public function handle()
	{
		$brandIdNames = Brand::pluck('name', 'id')->all();
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
			$rowError = [];

			if (in_array($this->userRole, ['Content Writing Manager', 'Content Writer'])) {
				if (empty($id)) {
					$rowError[] = 'ID is missing';
				}
				if (empty($name)) {
					$rowError[] = 'Name is missing';
				}
				if (empty($sku)) {
					$rowError[] = 'SKU is missing';
				}
			} else {
				if (empty($name)) {
					$rowError[] = 'Name is missing';
				}
				if (empty($sku)) {
					$rowError[] = 'SKU is missing';
				}
				if (empty($brand)) {
					$rowError[] = 'Brand is missing';
				}
				if (empty($category)) {
					$rowError[] = 'Category is missing';
				}
				if (empty($status)) {
					$rowError[] = 'Status is missing';
				}
			}

			if (!empty($rowError)) {
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

				if (!in_array($this->userRole, ['Admin', 'Super Admin']) && $product->approved == 1) {
					$rowError[] = "This product has already been approved and cannot be modified.";
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


			if (!empty($this->userRole) && in_array($this->userRole, ['Content Writing Manager', 'Content Writer'])) {

				/* Description validations */
				for ($i = 1; $i <= 4; $i++) {
					$desc = ${"description$i"} ?? '';
					if (!empty($desc) && strlen($desc) > 500) {
						$rowError[] = "Maximum 500 characters allowed in Description$i";
					}
					if ($i === 1 && empty($desc)) {
						$rowError[] = "Description1 is required";
					}
				}

				/* Benefit & Feature validations */
				for ($i = 1; $i <= 10; $i++) {
					$benefit = ${"benefit$i"} ?? '';
					$feature = ${"feature$i"} ?? '';

					if (!empty($benefit) && strlen($benefit) > 40) {
						$rowError[] = "Maximum 40 characters allowed in Benefit$i";
					}
					if (!empty($feature) && strlen($feature) > 200) {
						$rowError[] = "Maximum 200 characters allowed in Feature$i";
					}

					if ($i <= 5) {
						if (empty($benefit)) $rowError[] = "Benefit$i is required";
						if (empty($feature)) $rowError[] = "Feature$i is required";
					}
				}

				/* FAQ validations */
				for ($i = 1; $i <= 10; $i++) {
					$faqQ = ${"faq_question$i"} ?? '';
					$faqA = ${"faq_answer$i"} ?? '';

					if (!empty($faqQ) && strlen($faqQ) > 300) {
						$rowError[] = "Maximum 300 characters allowed in FAQ Question$i";
					}
					if (!empty($faqA) && strlen($faqA) > 500) {
						$rowError[] = "Maximum 500 characters allowed in FAQ Answer$i";
					}

					if ($i <= 5) {
						if (empty($faqQ)) $rowError[] = "FAQ Question$i is required";
						if (empty($faqA)) $rowError[] = "FAQ Answer$i is required";
					}
				}

				/* Group Descriptions */
				$description = [];
				for ($i = 1; $i <= 4; $i++) {
					if (!empty(${"description$i"})) {
						$description[] = ${"description$i"};
					}
				}

				/* Group Benefits & Features */
				$benefitsFeatures = [];
				for ($i = 1; $i <= 10; $i++) {
					$b = ${"benefit$i"} ?? '';
					$f = ${"feature$i"} ?? '';
					if (!empty($b) && !empty($f)) {
						$benefitsFeatures[] = ['benefit' => $b, 'feature' => $f];
					}
				}

				/* Convert to JSON */
				$jsonDescription = json_encode($description);
				$jsonBenefitsFeatures = json_encode($benefitsFeatures);
			} else {
				/* Brand validation */
				if (!in_array($brand, array_values($brandIdNames))) {
					$rowError[] = "$brand brand does not exist.";
				} else {
					$brandId = array_search($brand, $brandIdNames);
				}

				/* Category validation */
				$lowercaseCategory = strtolower($category);
				$lowercaseCategoryIdNames = array_change_key_case(array_flip($this->categoryIdNames), CASE_LOWER);
				if (array_key_exists($lowercaseCategory, $lowercaseCategoryIdNames)) {
					$categoryId = $lowercaseCategoryIdNames[$lowercaseCategory];
				} else {
					$rowError[] = "$category category does not exist or is not a valid lowest-level category.";
				}

				/* Is featured validation (Check for 0 if empty) */
				if ($isFeatured !== '' && (!is_numeric($isFeatured) || !in_array($isFeatured, [0, 1]))) {
					$rowError[] = "Is featured should be numeric and either 1 for Enable, or 0 for Disable.";
				} else {
					$isFeatured = $isFeatured !== '' ? (int) $isFeatured : 0;
				}

				/* Process Images */
				$fetchedImages = $this->getImageURLs((array) $images ?? []);

				$statusArray = [
					1 => "published",
					2 => "draft",
					3 => "pending"
				];

				/* Status Validation */
				if (!is_numeric($status) || !in_array((int)$status, [1, 2, 3])) {
					$rowError[] = "Status must be a numeric value: 1 (Published), 2 (Draft), or 3 (Pending).";
				} else {
					$status = (int) $status;

					if ($status === 1) {
						if (!$product->id) {
							$rowError[] = "You cannot set status to 'Published' during creation. Please save the product first.";
						}

						if (count($fetchedImages) === 0) {
							$rowError[] = "At least one product image is required to publish.";
						}

						$benefits = $product->benefits_features;
						$benefits = is_string($benefits) ? json_decode($benefits, true) : $benefits;
						if (!is_array($benefits) || count($benefits) < 5) {
							$rowError[] = "At least 5 benefits & features are required to publish.";
						}

						if ($product->productAttributes->count() < 5) {
							$rowError[] = "At least 5 product attributes are required to publish.";
						}

						if (!$product->sellingUnitAttribute) {
							$rowError[] = "The 'Selling Unit' attribute is required to publish.";
						}

						if ($product->productSuppliers->count() < 1) {
							$rowError[] = "At least one vendor price detail (product supplier) is required to publish.";
						}

						if ($product->productSuppliers->contains(function ($supplier) {
							return $supplier->in_stock !== 1;
						})) {
							$rowError[] = "All price details must have 'in_stock' set to Yes.";
						}

						if (empty($rowError)) {
							$status = $statusArray[$status];
						}
					} else {
						$status = $statusArray[$status];
					}
				}


				if ($rowError) {
					$errorArray[] = [
						"Row Number" => $failed + $success + 2 + $previousSuccessCount + $previousFailedCount,
						"Error" => implode(' | ', $rowError),
					];
					$failed++;
					continue;
				}
			}

			if ($rowError) {
				$errorArray[] = [
					"Row Number" => $failed + $success + 2 + $previousSuccessCount + $previousFailedCount,
					"Error" => implode(' | ', $rowError),
				];
				$failed++;
				continue;
			}

			// Wrap in a transaction
			DB::beginTransaction();

			try {
				/*************/
				$product->gen_type = 0;
				if (!empty($this->userRole) && in_array($this->userRole, ['Content Writing Manager', 'Content Writer'])) {
					$product->description = $jsonDescription;
					$product->benefits_features = $jsonBenefitsFeatures;
					if (in_array(config('app.website'), ['UAE', 'UAE_T', 'SA'])) {
						$product->translateOrNew('en')->description_tr = $jsonDescription;
						$product->translateOrNew('en')->benefits_features_tr = $jsonBenefitsFeatures;
					}

					Product::$observerUserId = $this->userId;
					$product->currency_id = in_array(config('app.website'), ['UAE', 'UAE_T']) ? 2 : 1;
					$product->website_ids = in_array(config('app.website'), ['UAE', 'UAE_T']) ? 2 : 1;
					$product->save();
					Product::$observerUserId = null;

					$submittedQuestions = [];

					/* Fetch existing FAQs for this product */
					$existingFaqs = $product->faqs()->get()->keyBy('question');

					for ($i = 1; $i <= 10; $i++) {
						$faqQuestion = trim(${'faq_question' . $i} ?? '');
						$faqAnswer = trim(${'faq_answer' . $i} ?? '');

						if (!empty($faqQuestion)) {
							$submittedQuestions[] = $faqQuestion;

							if ($existingFaqs->has($faqQuestion)) {
								/* Update existing FAQ */
								$faq = $existingFaqs[$faqQuestion];
								$faq->update([
									'question' => $faqQuestion,
									'answer' => $faqAnswer,
								]);

								/* Update translation (English) */
								if (in_array(config('app.website'), ['UAE', 'UAE_T', 'SA'])) {
									$faq->translateOrNew('en')->question_tr = $faqQuestion;
									$faq->translateOrNew('en')->answer_tr   = $faqAnswer;
								}

								$faq->save();
							} else {
								/* Create new FAQ */
								$faq = new Faq([
									'product_id' => $product->id,
									'category_id' => 4,
									'question' => $faqQuestion,
									'answer' => $faqAnswer,
									'status' => 'published',
								]);

								/* Add translations */
								if (in_array(config('app.website'), ['UAE', 'UAE_T', 'SA'])) {
									$faq->translateOrNew('en')->question_tr = $faqQuestion;
									$faq->translateOrNew('en')->answer_tr = $faqAnswer;
								}

								$faq->save();
							}
						}
					}

					/* Delete FAQs not in current submission */
					$product->faqs()->whereNotIn('question', $submittedQuestions)->each(function ($faq) {
						$faq->delete();
					});
				} else {
					$jsonImages = json_encode($fetchedImages);
					$product->name = $name;
					$product->sku = $sku;
					$product->status = $product->id ? $status : 'draft';
					$product->is_featured = $isFeatured;
					$product->brand_id = $brandId;
					$product->images = $jsonImages;
					if (in_array(config('app.website'), ['UAE', 'UAE_T', 'SA'])) {
						$product->translateOrNew('en')->name_tr = $name;
						$product->translateOrNew('en')->images_tr = $jsonImages;
					}

					$product->video_path = $uploadVideo;
					$product->currency_id = in_array(config('app.website'), ['UAE', 'UAE_T']) ? 2 : 1;
					$product->website_ids = in_array(config('app.website'), ['UAE', 'UAE_T']) ? 2 : 1;
					$product->barcode = !empty($barcode) ? $barcode : null;
					$product->created_at = $product->id ? $product->created_at : now();
					$product->updated_at = now();
					$product->created_by = $this->userId;
					Product::$observerUserId = $this->userId;
					$product->save();
					Product::$observerUserId = null;

					$SKUs[$product->id] = $sku;

					$this->saveProductCategory($product, $categoryId);
					$this->saveProductTag($product, $tags);
				}

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
			$product->productAttributes()->delete();
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

	// private function saveSeoMetaData($product, $seoTitle, $seoDescription)
	// {
	// 	/* Retrieve or create the SEO metadata */
	// 	$seoMetaData = $product->seoMetaData ?: new MetaBox([
	// 		'meta_key' => 'seo_meta',
	// 		'reference_id' => $product->id,
	// 		'reference_type' => Product::class,
	// 	]);

	// 	/* Decode existing meta_value if present */
	// 	$existingMetaValue = is_array($seoMetaData->meta_value)
	// 	? $seoMetaData->meta_value
	// 	: (json_decode($seoMetaData->meta_value, true) ?? []);

	// 	/* Ensure $existingMetaValue is an array */
	// 	if (!is_array($existingMetaValue)) {
	// 		$existingMetaValue = [];
	// 	}

	// 	$updatedMetaValue = [];
	// 	if (!empty($seoTitle)) {
	// 		$updatedMetaValue['seo_title'] = $seoTitle;
	// 	}

	// 	if (!empty($seoDescription)) {
	// 		$updatedMetaValue['seo_description'] = $seoDescription;
	// 	}
	// 	$updatedMetaValue['index'] = $existingMetaValue['index'] ?? 'index';

	// 	/* Store the updated meta value as an array */
	// 	$seoMetaData->meta_value = [$updatedMetaValue];

	// 	/* Save the updated meta data */
	// 	$seoMetaData->save();
	// }

	// private function saveSlugData($product, $url)
	// {
	// 	if (strpos($url, '/products/') !== false) {
	// 		$urlParts = explode('/products/', $url);
	// 		$outputUrl = $urlParts[1];
	// 	} else {
	// 		$outputUrl = null; // Handle the case where "/products/" is not found
	// 	}
	// 	/* Retrieve or create the slug data */
	// 	$slugData = $product->slugData ?: new Slug([
	// 		'prefix' => 'products',
	// 		'reference_id' => $product->id,
	// 		'reference_type' => Product::class,
	// 	]);

	// 	$slugData->key = $outputUrl;

	// 	$slugData->save();
	// }

	// private function saveTranslation($product, $rowData)
	// {
	// 	if (!empty(trim($rowData['Name (AR)'] ?? '')) || !empty(trim($rowData['Description (AR)'] ?? '')) || !empty(trim($rowData['Warranty Information (AR)'] ?? ''))) {
	// 		$checkExist = $product->translations()->where('lang_code', 'ar')->first();

	// 		if ($checkExist) {
	// 			$checkExist->update([
	// 				'name' => $rowData['Name (AR)'],
	// 				'description' => $rowData['Description (AR)'],
	// 				'content' => $rowData['Content (AR)'],
	// 				'warranty_information' => $rowData['Warranty Information (AR)'],
	// 			]);
	// 		} else {
	// 			$product->translations()->create([
	// 				'lang_code' => 'ar',
	// 				'ec_products_id' => $product->id,
	// 				'name' => $rowData['Name (AR)'],
	// 				'description' => $rowData['Description (AR)'],
	// 				'content' => $rowData['Content (AR)'],
	// 				'warranty_information' => $rowData['Warranty Information (AR)'],
	// 			]);
	// 		}
	// 	}
	// }

	// private function saveDiscount($product, $rowData)
	// {
	// 	$requiredFieldValues = [
	// 		'quantity1' => $rowData['Buying Quantity1'] ?? null,
	// 		'value1' => $rowData['Discount1'] ?? null,
	// 		'start_date1' => $rowData['Start Date1'] ?? null,
	// 		'quantity2' => $rowData['Buying Quantity2'] ?? null,
	// 		'value2' => $rowData['Discount2'] ?? null,
	// 		'start_date2' => $rowData['Start Date2'] ?? null,
	// 	];

	// 	$requiredFieldsProvided = !empty($requiredFieldValues['quantity1']) && !empty($requiredFieldValues['value1']) && !empty($requiredFieldValues['start_date1']) && !empty($requiredFieldValues['quantity2']) && !empty($requiredFieldValues['value2']) && !empty($requiredFieldValues['start_date2']);
	// 	if ($requiredFieldsProvided) {
	// 		for ($i = 1; $i <= 3; $i++) {
	// 			// Check if the current iteration is optional (3rd discount)
	// 			$isOptional = ($i === 3);

	// 			// Required fields for discounts
	// 			$requiredFields = [
	// 				'quantity' => $rowData['Buying Quantity' . $i] ?? null,
	// 				'value' => $rowData['Discount' . $i] ?? null,
	// 				'start_date' => $rowData['Start Date' . $i] ?? null,
	// 			];

	// 			// Check if all required fields are non-empty
	// 			$allFieldsProvided = !empty($requiredFields['quantity']) && !empty($requiredFields['value']) && !empty($requiredFields['start_date']);

	// 			// Validate required fields for discounts
	// 			if ($allFieldsProvided) {
	// 				$discount = new Discount();
	// 				$discount->product_quantity = $requiredFields['quantity'];
	// 				$discount->title = $discount->product_quantity . ' products';
	// 				$discount->type_option = 'percentage';
	// 				$discount->type = 'promotion';
	// 				$discount->value = $requiredFields['value'];
	// 				$discount->start_date = !empty($requiredFields['start_date']) ? Carbon::parse($requiredFields['start_date']) : null;
	// 				$discount->end_date = !empty($rowData['End Date' . $i]) ? Carbon::parse($rowData['End Date' . $i]) : null;
	// 				$discount->save();

	// 				// Associate the discount with the product
	// 				$discountProduct = new DiscountProduct();
	// 				$discountProduct->discount_id = $discount->id;
	// 				$discountProduct->product_id = $product->id;
	// 				$discountProduct->save();
	// 			}
	// 		}
	// 	}
	// }

	protected function getImageURLs(array $images): array
	{
		/* Ensure images are properly formatted and split if needed */
		$images = array_values(array_filter(
			array_map('trim', preg_split('/\s*,\s*/', implode(',', $images)))
		));

		foreach ($images as $key => $image) {
			if (Str::startsWith($image, env('AWS_URL'))) {
				$images[$key] = $image;
			} else {
				if (Str::startsWith($image, ['http://', 'https://'])) {
					$uploadedImage = $this->uploadImageFromURL($image);
					$images[$key] = $uploadedImage;
					Log::info("Uploaded image " . $uploadedImage);
				} else {
					Log::warning("Invalid image URL at index $key: " . $image);
					unset($images[$key]);
				}
			}
		}

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
			$originalPath = env('STORAGE_ENV')."/products/{$fileBaseName}.{$fileExtension}";
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

				$resizedPath = env('STORAGE_ENV')."/products/{$fileBaseName}-{$width}x{$height}.{$fileExtension}";
				ob_start();
				imagewebp($resizedImage);
				$resizedData = ob_get_clean();
				$s3Disk->put($resizedPath, $resizedData);
				// $imageUrls[$sizeName] = $s3Disk->url($resizedPath);
			}

			imagedestroy($image);
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
	public function failed(\Throwable $exception): void
	{
		$log = TransactionLog::where('identifier', $this->batch()->id)->first();

		if (!$log) {
			logger()->error('Transaction log not found for batch: ' . $this->batch()->id);
			return;
		}

		$jobName = class_basename($this);

		$errorDetails = [
			'job' => $jobName,
			'message' => $exception->getMessage(),
			'file' => $exception->getFile(),
			'line' => $exception->getLine(),
			'trace' => $exception->getTraceAsString(),
		];

		logger()->error("{$jobName} failed", $errorDetails);

		$description = json_decode($log->description, true) ?? [];

		$description['Success Count'] = $description['Success Count'] ?? 0;
		$description['Failed Count'] = $description['Failed Count'] ?? 0;
		$description['Errors'] = $description['Errors'] ?? [];

		$description['Errors'][] = [
			'Row Number' => 'N/A',
			'Job' => $jobName,
			'Error' => $errorDetails['message'],
			'File' => $errorDetails['file'],
			'Line' => $errorDetails['line'],
		];

		TransactionLog::where('id', $log->id)->update([
			'status' => 'Failed',
			'description' => json_encode($description, JSON_UNESCAPED_UNICODE),
		]);
	}
}
