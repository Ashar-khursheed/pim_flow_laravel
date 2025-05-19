<?php

// namespace App\Jobs;

// use Illuminate\Bus\Queueable;
// use Illuminate\Contracts\Queue\ShouldQueue;
// use Illuminate\Foundation\Bus\Dispatchable;
// use Illuminate\Queue\InteractsWithQueue;
// use Illuminate\Queue\SerializesModels;
// use Illuminate\Bus\Batchable;

// use Illuminate\Support\Facades\DB;
// use Illuminate\Support\Facades\Log;
// use Illuminate\Support\Facades\Storage;

// use App\Models\TransactionLog;
// use App\Models\Product;
// use App\Models\Category;
// use App\Models\Brand;
// use App\Models\Blog;
// use App\Models\SeoManagement;
// use App\Models\SeoSecondaryKeyword;

// class ImportSeoDetailJob implements ShouldQueue
// {
// 	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

// 	public $timeout = 43200;

// 	protected $header;
// 	protected $chunk;
// 	protected $userId;
// 	protected $seoFileFormatArray;

// 	public function __construct(array $data)
// 	{
// 		$this->header = $data['header'];
// 		$this->chunk = $data['chunk'];
// 		$this->userId = $data['userId'];
// 		$this->seoFileFormatArray = $data['seoFileFormatArray'];
// 	}

// 	public function handle()
// 	{
// 		$log = TransactionLog::where('identifier', $this->batch()->id)->first();
// 		$descArray = json_decode($log->description, true) ?? ["Errors" => ''];
// 		$previousSuccessCount = $descArray["Success Count"] ?? 0;
// 		$previousFailedCount = $descArray["Failed Count"] ?? 0;

// 		$errorArray = [];
// 		$success = 0;
// 		$failed = 0;

// 		$groupedPrimary = [];

// 		foreach ($this->chunk as $rowIndex => $row) {
// 			$rowData = [];
// 			$rowError = [];

// 			if (count($this->header) === count($row)) {
// 				$rowData = array_combine($this->header, $row);
// 			} else {
// 				$rowError[] = 'The data in this row is not compatible for import.';
// 				$errorArray[] = [
// 					"Row Number" => $rowIndex + 2 + $previousSuccessCount + $previousFailedCount,
// 					"Error" => implode(' | ', $rowError)
// 				];
// 				$failed++;
// 				continue;
// 			}

// 			foreach ($this->seoFileFormatArray as $headerKey => $variableName) {
// 				if (array_key_exists($headerKey, $rowData)) {
// 					${$variableName} = trim($rowData[$headerKey]);
// 				}
// 			}

// 			// Required data validation
// 			// if ((empty($relational_id) && empty($relational_name)) || empty($relational_type) || empty($url) || empty($primary_keyword) || empty($primary_monthly_search_volume) || empty($secondary_keyword) || empty($secondary_monthly_search_volume) || empty($title_tag) || empty($meta_title) || empty($meta_description)) {
// 			if ((empty($relational_id) && empty($relational_name)) || empty($relational_type) || empty($primary_keyword) || empty($primary_monthly_search_volume) || empty($secondary_keyword) || empty($secondary_monthly_search_volume)) {
// 				$rowError[] = 'Required fields are missing.';
// 				$errorArray[] = [
// 					"Row Number" => $rowIndex + 2 + $previousSuccessCount + $previousFailedCount,
// 					"Error" => implode(' | ', $rowError),
// 				];
// 				$failed++;
// 				continue;
// 			}

// 			$fieldsWithLimits = [
// 				'title_tag' => ['limit' => 70, 'label' => 'Title Tag'],
// 				'meta_title' => ['limit' => 60, 'label' => 'Meta Title'],
// 				'meta_description' => ['limit' => 160, 'label' => 'Meta Description'],
// 				'og_title' => ['limit' => 60, 'label' => 'OG Title'],
// 				'og_description' => ['limit' => 200, 'label' => 'OG Description'],
// 			];

// 			foreach ($fieldsWithLimits as $field => $config) {
// 				if (!empty($$field) && strlen($$field) > $config['limit']) {
// 					$rowError[] = "Maximum {$config['limit']} characters allowed in {$config['label']}.";
// 				}
// 			}

// 			if (!empty($rowError)) {
// 				$errorArray[] = [
// 					"Row Number" => $rowIndex + 2 + $previousSuccessCount + $previousFailedCount,
// 					"Error" => implode(' | ', $rowError),
// 				];
// 				$failed++;
// 				continue;
// 			}

// 			if (!in_array($relational_type, ['Product', 'Category', 'Brand', 'Blog'])) {
// 				$rowError[] = "Invalid Relational Type. Must be one of 'Product', 'Category', 'Brand', 'Blog'.";
// 				$errorArray[] = [
// 					"Row Number" => $rowIndex + 2 + $previousSuccessCount + $previousFailedCount,
// 					"Error" => implode(' | ', $rowError),
// 				];
// 				$failed++;
// 				continue;
// 			}

// 			$model = match ($relational_type) {
// 				'Product' => Product::class,
// 				'Category' => Category::class,
// 				'Brand' => Brand::class,
// 				'Blog' => Blog::class,
// 			};
// 			$relational_type = $model;

// 			if (!empty($relational_id)) {
// 				$exist = $model::find($relational_id);
// 			} elseif (!empty($relational_name)) {
// 				$exist = $model::where('name', $relational_name)->first();
// 			} else {
// 				$exist = null;
// 			}

// 			if ($exist) {
// 				$relational_id = $exist->id;
// 			} else {
// 				$rowError[] = class_basename($relational_type) . " does not exist for the given relational identifier." .
// 				" [Provided relational_id: " . ($relational_id ?? 'NULL') . ", relational_name: '" . ($relational_name ?? 'NULL') . "']";
// 				$errorArray[] = [
// 					"Row Number" => $rowIndex + 2 + $previousSuccessCount + $previousFailedCount,
// 					"Error" => implode(' | ', $rowError),
// 				];
// 				$failed++;
// 				continue;
// 			}

// 			/* Validate OG Image Fields - all or none */
// 			$primaryKey = $relational_id . '|' . $relational_type;
// 			$ogFields = [
// 				'Og Image URL' => $og_image_url ?? null,
// 				'Og Image Alt Text' => $og_image_alt_text ?? null,
// 				'Og Image Name' => $og_image_name ?? null,
// 			];
// 			$ogFilledCount = count(array_filter($ogFields));
// 			if ($ogFilledCount > 0 && $ogFilledCount < 3) {
// 				foreach ($ogFields as $label => $value) {
// 					if (empty($value)) $rowError[] = "{$label} is required when any OG Image field is provided.";
// 				}
// 				$errorArray[] = [
// 					"Row Number" => $rowIndex + 2 + $previousSuccessCount + $previousFailedCount,
// 					"Error" => implode(' | ', $rowError),
// 				];
// 				$failed++;
// 				continue;
// 			}
// 			$uploadedUrl = ($ogFilledCount === 3) ? $this->uploadImageFromURL($og_image_url, $og_image_name) : null;
// 			$ogFieldsProvided = !empty($og_image_url) && !empty($og_image_alt_text) && !empty($og_image_name);
// 			if ($ogFieldsProvided) {
// 				$ogFieldsStorage[$primaryKey] = [
// 					'og_image_url' => $uploadedUrl,
// 					'og_image_alt_text' => $og_image_alt_text,
// 					'og_image_name' => $og_image_name,
// 				];
// 			}

// 			if (isset($ogFieldsStorage[$primaryKey])) {
// 				$og_image_url = $ogFieldsStorage[$primaryKey]['og_image_url'];
// 				$og_image_alt_text = $ogFieldsStorage[$primaryKey]['og_image_alt_text'];
// 				$og_image_name = $ogFieldsStorage[$primaryKey]['og_image_name'];
// 			}

// 			// Set default values for schema-related fields
// 			$schema_rating = $schema_rating ?? 5;
// 			$schema_reviews_count = $schema_reviews_count ?? 0;

// 			$groupedPrimary[$primaryKey]['primary'] = [
// 				'relational_id' => $relational_id,
// 				'relational_name' => $relational_name,
// 				'relational_type' => $relational_type,
// 				'url' => $url,
// 				'primary_keyword' => $primary_keyword,
// 				'monthly_search_volume' => $primary_monthly_search_volume,
// 				'title_tag' => $title_tag,
// 				'meta_title' => $meta_title,
// 				'meta_description' => $meta_description,
// 				'internal_links' => $internal_links ?? null,
// 				'indexing' => empty($indexing) ? 0 : $indexing,
// 				'og_title' => $og_title ?? null,
// 				'og_description' => $og_description ?? null,
// 				'og_image_url' => $og_image_url ?? null,
// 				'og_image_alt_text' => $og_image_alt_text ?? null,
// 				'og_image_name' => $og_image_name ?? null,
// 				'tags' => $tags ?? null,
// 				'created_by' => $this->userId,
// 				'schema_rating' => $schema_rating,
// 				'schema_reviews_count' => $schema_reviews_count
// 			];

// 			$groupedPrimary[$primaryKey]['secondary'][] = [
// 				'secondary_keyword' => $secondary_keyword,
// 				'monthly_search_volume' => $secondary_monthly_search_volume,
// 			];

// 			$success++;
// 		}

// 		DB::beginTransaction();

// 		try {
// 			foreach ($groupedPrimary as $group) {
// 				$primaryData = $group['primary'];
// 				if (env('APP_WEBSITE') == 'UAE') {
// 					$pythonScriptPath = base_path('app/Script/main_uae.py');
// 					$pythonCmd = base_path('venv/bin/python');
// 					// $pythonCmd = 'python3';
// 				} elseif (env('APP_WEBSITE') == 'US') {
// 					$pythonScriptPath = base_path('app/Script/main_us_2.py');
// 					$pythonCmd = base_path('venv/bin/python');
// 				} else {
// 					$pythonScriptPath = base_path('app/Script/main_us_2.py');
// 					$pythonCmd = (env('STORAGE_ENV') == 'tanuj_system') ? 'python' : base_path('venv/bin/python');
// 				}

// 				// $inputJson = json_encode($primaryData);
// 				$tempInputPath = storage_path('app/temp_input.json');
// 				file_put_contents($tempInputPath, json_encode($primaryData));

// 				$command = "{$pythonCmd} \"{$pythonScriptPath}\" < \"{$tempInputPath}\"";

// 				$outputJson = shell_exec($command . " 2>&1");
// 				unlink($tempInputPath);

// 				$primaryData = json_decode($outputJson, true);

// 				unset($primaryData['relational_name']);

// 				$primaryData['created_at'] = now();
// 				$primaryData['updated_at'] = now();

// 				// Add this check before the updateOrCreate
// 				if (!isset($primaryData['relational_id'])) {
// 					$primaryData['relational_id'] = $group['primary']['relational_id'];
// 				}
// 				if (!isset($primaryData['relational_type'])) {
// 					$primaryData['relational_type'] = $group['primary']['relational_type'];
// 				}

// 				// Create/update the SEO record first
// 				$seo = SeoManagement::updateOrCreate(
// 					[
// 						'relational_id' => $primaryData['relational_id'],
// 						'relational_type' => $primaryData['relational_type']
// 					],
// 					$primaryData
// 				);

// 				// Generate schema
// 				$schema = $this->generateSchema($seo);

// 				// Add schema to the SEO record in a separate update to ensure it's always generated
// 				$seo->update(['schema' => json_encode($schema)]);

// 				// Process secondary keywords
// 				foreach ($group['secondary'] as $secondary) {
// 					SeoSecondaryKeyword::updateOrCreate(
// 						[
// 							'primary_keyword_id' => $seo->id,
// 							'secondary_keyword' => $secondary['secondary_keyword'],
// 						],
// 						['monthly_search_volume' => $secondary['monthly_search_volume']]
// 					);
// 				}
// 			}

// 			// Update Transaction Log
// 			$descArray["Success Count"] += $success;
// 			$descArray["Failed Count"] += $failed;
// 			$descArray["Errors"] = array_merge($descArray["Errors"], $errorArray);

// 			$log->update([
// 				'description' => json_encode($descArray),
// 			]);

// 			DB::commit();
// 		} catch (\Throwable $e) {
// 			DB::rollBack();
// 			logger()->error('Exception occurred in file: ' . $e->getFile() . ' on line: ' . $e->getLine(), [
// 				'message' => $e->getMessage(),
// 				'trace' => $e->getTraceAsString(),
// 			]);
// 		}
// 	}

// 	private function uploadImageFromURL(?string $url, ?string $fileBaseName): ?string
// 	{
// 		$s3Disk = Storage::disk('s3');

// 		/* Validate URL */
// 		if (!filter_var($url, FILTER_VALIDATE_URL)) {
// 			Log::error('Invalid URL provided: ' . $url);
// 			return null;
// 		}

// 		/* Fetch image content */
// 		$imageContents = file_get_contents($url);
// 		if ($imageContents === false || empty($imageContents)) {
// 			Log::error('Failed to download image from URL or content is empty: ' . $url);
// 			return null;
// 		}

// 		$fileExtension = 'webp';
// 		$imageUrl = '';

// 		try {
// 			/* Create image resource from content */
// 			$image = imagecreatefromstring($imageContents);
// 			if (!$image) {
// 				Log::error('Failed to create image from URL: ' . $url);
// 				return null;
// 			}

// 			/* Ensure image is in Truecolor format */
// 			if (imageistruecolor($image) === false) {
// 				imagepalettetotruecolor($image);
// 			}

// 			/* Save original image */
// 			$originalPath = env('STORAGE_ENV')."/seo-detail/{$fileBaseName}.{$fileExtension}";
// 			ob_start();
// 			imagewebp($image);
// 			$originalData = ob_get_clean();
// 			$s3Disk->put($originalPath, $originalData);
// 			$imageUrl = $s3Disk->url($originalPath);
// 			imagedestroy($image);
// 			Log::info('Uploaded Images: ' . $imageUrl);
// 			return $imageUrl;
// 		} catch (\Exception $e) {
// 			Log::error('S3 Upload Error: ' . $e->getMessage());
// 			return null;
// 		}
// 	}

// 	/**
// 	 * Generate schema based on SEO record type
// 	 *
// 	 * @param SeoManagement $seo
// 	 * @return array
// 	 */
// 	private function generateSchema(SeoManagement $seo)
// 	{
// 		// Check if the type is 'Product' and relational_id is available
// 		if ($seo->relational_type === 'App\Models\Product' && $seo->relational_id) {
// 			// Fetch product data from products table
// 			$product = Product::find($seo->relational_id);

// 			if ($product) {
// 				// Fetch currency and brand names using relationships
// 				$currencyName = $product->currency ? $product->currency->title : 'USD'; // Default to 'USD' if no currency found
// 				$brandName = $product->brand ? $product->brand->name : 'Default Brand'; // Default to 'Default Brand' if no brand found

// 				// Generate schema with product-specific details
// 				return [
// 					"@context" => "https://schema.org",
// 					"@type" => "Product",
// 					"url" => $seo->url,
// 					"name" => $seo->meta_title,
// 					"description" => $seo->meta_description,
// 					"keywords" => $seo->tags,
// 					"image" => [
// 						"@type" => "ImageObject",
// 						"url" => $seo->og_image_url,
// 						"name" => $seo->og_image_name,
// 						"description" => $seo->og_image_alt_text
// 					],
// 					"aggregateRating" => [
// 						"@type" => "AggregateRating",
// 						"ratingValue" => $seo->schema_rating,
// 						"reviewCount" => $seo->schema_reviews_count
// 					],
// 					"offers" => [
// 						"@type" => "Offer",
// 						"priceCurrency" => $currencyName,
// 						"price" => $product->price ?? 0, // Default to 0 if no price found
// 						"url" => $seo->url,
// 					],
// 					"sku" => $product->sku ?? null, // SKU if available
// 					"brand" => [
// 						"@type" => "Brand",
// 						"name" => $brandName
// 					],
// 					"availability" => "https://schema.org/" . ($product->availability ?? 'InStock'), // Default to 'InStock' if no availability found
// 				];
// 			}
// 		}

// 		// If not a product, return the generic WebPage schema
// 		return [
// 			"@context" => "https://schema.org",
// 			"@type" => str_replace('App\\Models\\', '', $seo->relational_type) ?? 'WebPage',
// 			"url" => $seo->url,
// 			"name" => $seo->meta_title,
// 			"description" => $seo->meta_description,
// 			"keywords" => $seo->tags,
// 			"image" => [
// 				"@type" => "ImageObject",
// 				"url" => $seo->og_image_url,
// 				"name" => $seo->og_image_name,
// 				"description" => $seo->og_image_alt_text
// 			],
// 			"aggregateRating" => [
// 				"@type" => "AggregateRating",
// 				"ratingValue" => $seo->schema_rating,
// 				"reviewCount" => $seo->schema_reviews_count
// 			]
// 		];
// 	}

// 	/**
// 	 * Handle a job failure.
// 	 */
// 	public function failed(\Throwable $exception): void
// 	{
// 		$error = $exception->getMessage().$exception->getTraceAsString();
// 		logger(__("SEO Import Error").': '.$error);
// 	}
// }


namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Batchable;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

use App\Models\TransactionLog;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Blog;
use App\Models\SeoManagement;
use App\Models\SeoSecondaryKeyword;

class ImportSeoDetailJob implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

	public $timeout = 43200;

	protected $header;
	protected $chunk;
	protected $userId;
	protected $seoFileFormatArray;

	public function __construct(array $data)
	{
		$this->header = $data['header'];
		$this->chunk = $data['chunk'];
		$this->userId = $data['userId'];
		$this->seoFileFormatArray = $data['seoFileFormatArray'];
	}

	public function handle()
	{
		$log = TransactionLog::where('identifier', $this->batch()->id)->first();
		if (!$log) {
			Log::error('Transaction log not found for batch ID: ' . $this->batch()->id);
			return;
		}

		$descArray = json_decode($log->description, true) ?? ["Errors" => [], "Success Count" => 0, "Failed Count" => 0];
		$previousSuccessCount = $descArray["Success Count"] ?? 0;
		$previousFailedCount = $descArray["Failed Count"] ?? 0;

        // Ensure Errors key exists and is an array
        if (!isset($descArray["Errors"]) || !is_array($descArray["Errors"])) {
            $descArray["Errors"] = [];
        }

		$errorArray = [];
		$success = 0;
		$failed = 0;

		$groupedPrimary = [];

		foreach ($this->chunk as $rowIndex => $row) {
			$rowData = [];
			$rowError = [];

			if (count($this->header) === count($row)) {
				$rowData = array_combine($this->header, $row);
			} else {
				$rowError[] = 'The data in this row is not compatible for import.';
				$errorArray[] = [
					"Row Number" => $rowIndex + 2 + $previousSuccessCount + $previousFailedCount,
					"Error" => implode(' | ', $rowError)
				];
				$failed++;
				continue;
			}

			foreach ($this->seoFileFormatArray as $headerKey => $variableName) {
				if (array_key_exists($headerKey, $rowData)) {
					${$variableName} = trim($rowData[$headerKey]);
				} else {
                    ${$variableName} = null; // Initialize undefined variables
                }
			}

			// Required data validation
			if ((empty($relational_id) && empty($relational_name)) || empty($relational_type) || empty($primary_keyword) || empty($primary_monthly_search_volume) || empty($secondary_keyword) || empty($secondary_monthly_search_volume)) {
				$rowError[] = 'Required fields are missing.';
				$errorArray[] = [
					"Row Number" => $rowIndex + 2 + $previousSuccessCount + $previousFailedCount,
					"Error" => implode(' | ', $rowError),
				];
				$failed++;
				continue;
			}

			$fieldsWithLimits = [
				'title_tag' => ['limit' => 70, 'label' => 'Title Tag'],
				'meta_title' => ['limit' => 60, 'label' => 'Meta Title'],
				'meta_description' => ['limit' => 160, 'label' => 'Meta Description'],
				'og_title' => ['limit' => 60, 'label' => 'OG Title'],
				'og_description' => ['limit' => 200, 'label' => 'OG Description'],
			];

			foreach ($fieldsWithLimits as $field => $config) {
				if (!empty($$field) && strlen($$field) > $config['limit']) {
					$rowError[] = "Maximum {$config['limit']} characters allowed in {$config['label']}.";
				}
			}

			if (!empty($rowError)) {
				$errorArray[] = [
					"Row Number" => $rowIndex + 2 + $previousSuccessCount + $previousFailedCount,
					"Error" => implode(' | ', $rowError),
				];
				$failed++;
				continue;
			}

			if (!in_array($relational_type, ['Product', 'Category', 'Brand', 'Blog'])) {
				$rowError[] = "Invalid Relational Type. Must be one of 'Product', 'Category', 'Brand', 'Blog'.";
				$errorArray[] = [
					"Row Number" => $rowIndex + 2 + $previousSuccessCount + $previousFailedCount,
					"Error" => implode(' | ', $rowError),
				];
				$failed++;
				continue;
			}

			$model = match ($relational_type) {
				'Product' => Product::class,
				'Category' => Category::class,
				'Brand' => Brand::class,
				'Blog' => Blog::class,
			};
			$relational_type = $model;

			if (!empty($relational_id)) {
				$exist = $model::find($relational_id);
			} elseif (!empty($relational_name)) {
				$exist = $model::where('name', $relational_name)->first();
			} else {
				$exist = null;
			}

			if ($exist) {
				$relational_id = $exist->id;
			} else {
				$rowError[] = class_basename($relational_type) . " does not exist for the given relational identifier." .
				" [Provided relational_id: " . ($relational_id ?? 'NULL') . ", relational_name: '" . ($relational_name ?? 'NULL') . "']";
				$errorArray[] = [
					"Row Number" => $rowIndex + 2 + $previousSuccessCount + $previousFailedCount,
					"Error" => implode(' | ', $rowError),
				];
				$failed++;
				continue;
			}

			/* Validate OG Image Fields - all or none */
			$primaryKey = $relational_id . '|' . $relational_type;
			$ogFields = [
				'Og Image URL' => $og_image_url ?? null,
				'Og Image Alt Text' => $og_image_alt_text ?? null,
				'Og Image Name' => $og_image_name ?? null,
			];
            
			$ogFilledCount = count(array_filter($ogFields));
			if ($ogFilledCount > 0 && $ogFilledCount < 3) {
				foreach ($ogFields as $label => $value) {
					if (empty($value)) $rowError[] = "{$label} is required when any OG Image field is provided.";
				}
				$errorArray[] = [
					"Row Number" => $rowIndex + 2 + $previousSuccessCount + $previousFailedCount,
					"Error" => implode(' | ', $rowError),
				];
				$failed++;
				continue;
			}
            
            // Initialize ogFieldsStorage if needed
            $ogFieldsStorage = [];
            
			$uploadedUrl = ($ogFilledCount === 3) ? $this->uploadImageFromURL($og_image_url, $og_image_name) : null;
			$ogFieldsProvided = !empty($og_image_url) && !empty($og_image_alt_text) && !empty($og_image_name);
			if ($ogFieldsProvided) {
				$ogFieldsStorage[$primaryKey] = [
					'og_image_url' => $uploadedUrl,
					'og_image_alt_text' => $og_image_alt_text,
					'og_image_name' => $og_image_name,
				];
			}

			if (isset($ogFieldsStorage[$primaryKey])) {
				$og_image_url = $ogFieldsStorage[$primaryKey]['og_image_url'];
				$og_image_alt_text = $ogFieldsStorage[$primaryKey]['og_image_alt_text'];
				$og_image_name = $ogFieldsStorage[$primaryKey]['og_image_name'];
			}

			// Set default values for schema-related fields
			$schema_rating = $schema_rating ?? 5;
			$schema_reviews_count = $schema_reviews_count ?? 0;

			$groupedPrimary[$primaryKey]['primary'] = [
				'relational_id' => $relational_id,
				'relational_name' => $relational_name,
				'relational_type' => $relational_type,
				'url' => $url ?? null,
				'primary_keyword' => $primary_keyword,
				'monthly_search_volume' => $primary_monthly_search_volume,
				'title_tag' => $title_tag ?? null,
				'meta_title' => $meta_title ?? null,
				'meta_description' => $meta_description ?? null,
				'internal_links' => $internal_links ?? null,
				'indexing' => empty($indexing) ? 0 : $indexing,
				'og_title' => $og_title ?? null,
				'og_description' => $og_description ?? null,
				'og_image_url' => $og_image_url ?? null,
				'og_image_alt_text' => $og_image_alt_text ?? null,
				'og_image_name' => $og_image_name ?? null,
				'tags' => $tags ?? null,
				'created_by' => $this->userId,
				'schema_rating' => $schema_rating,
				'schema_reviews_count' => $schema_reviews_count
			];

			$groupedPrimary[$primaryKey]['secondary'][] = [
				'secondary_keyword' => $secondary_keyword,
				'monthly_search_volume' => $secondary_monthly_search_volume,
			];

			$success++;
		}

		DB::beginTransaction();

		try {
			foreach ($groupedPrimary as $group) {
				$primaryData = $group['primary'];
				if (env('APP_WEBSITE') == 'UAE') {
					$pythonScriptPath = base_path('app/Script/main_uae.py');
					$pythonCmd = base_path('venv/bin/python');
				} elseif (env('APP_WEBSITE') == 'US') {
					$pythonScriptPath = base_path('app/Script/main_us_2.py');
					$pythonCmd = base_path('venv/bin/python');
				} else {
					$pythonScriptPath = base_path('app/Script/main_us_2.py');
					$pythonCmd = (env('STORAGE_ENV') == 'tanuj_system') ? 'python' : base_path('venv/bin/python');
				}

				$tempInputPath = storage_path('app/temp_input.json');
				file_put_contents($tempInputPath, json_encode($primaryData));

				// Add debugging output before running the script
				Log::info('Executing Python script', [
				    'pythonCmd' => $pythonCmd,
				    'scriptPath' => $pythonScriptPath,
				    'inputPath' => $tempInputPath
				]);

				$command = "{$pythonCmd} \"{$pythonScriptPath}\" < \"{$tempInputPath}\" 2>> " . storage_path('logs/python_errors.log');
				$outputJson = shell_exec($command);
				
				// Log the output for debugging
				Log::info('Python script output', ['output' => $outputJson]);

				if (file_exists($tempInputPath)) {
				    unlink($tempInputPath);
				}

				// Check for valid JSON in the response
				$decodedOutput = json_decode($outputJson, true);
				if (json_last_error() !== JSON_ERROR_NONE) {
				    Log::error('Invalid JSON response from Python script', [
				        'output' => $outputJson,
				        'error' => json_last_error_msg()
				    ]);
				    throw new \Exception('Invalid JSON response from Python script: ' . json_last_error_msg());
				}

				$primaryData = $decodedOutput;

				unset($primaryData['relational_name']);

				$primaryData['created_at'] = now();
				$primaryData['updated_at'] = now();

				// Add this check before the updateOrCreate
				if (!isset($primaryData['relational_id'])) {
					$primaryData['relational_id'] = $group['primary']['relational_id'];
				}
				if (!isset($primaryData['relational_type'])) {
					$primaryData['relational_type'] = $group['primary']['relational_type'];
				}

				// Create/update the SEO record first
				$seo = SeoManagement::updateOrCreate(
					[
						'relational_id' => $primaryData['relational_id'],
						'relational_type' => $primaryData['relational_type']
					],
					$primaryData
				);

				// Generate schema
				$schema = $this->generateSchema($seo);

				// Add schema to the SEO record in a separate update to ensure it's always generated
				$seo->update(['schema' => json_encode($schema)]);

				// Process secondary keywords
				foreach ($group['secondary'] as $secondary) {
					SeoSecondaryKeyword::updateOrCreate(
						[
							'primary_keyword_id' => $seo->id,
							'secondary_keyword' => $secondary['secondary_keyword'],
						],
						['monthly_search_volume' => $secondary['monthly_search_volume']]
					);
				}
			}

			// Update Transaction Log
			$descArray["Success Count"] += $success;
			$descArray["Failed Count"] += $failed;
			$descArray["Errors"] = array_merge($descArray["Errors"], $errorArray);

			$log->update([
				'description' => json_encode($descArray),
			]);

			DB::commit();
			Log::info('SEO Import Job completed successfully', [
			    'successCount' => $success,
			    'failedCount' => $failed
			]);
			
		} catch (\Throwable $e) {
			DB::rollBack();
			Log::error('Exception in ImportSeoDetailJob', [
				'file' => $e->getFile(),
				'line' => $e->getLine(),
				'message' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
			]);
			
			// Update the transaction log with the error
			if ($log) {
			    $errorMessage = "Job execution failed: " . $e->getMessage();
			    $descArray["Errors"][] = [
			        "General Error" => $errorMessage
			    ];
			    $descArray["Failed Count"] += 1;
			    
			    $log->update([
			        'description' => json_encode($descArray),
			        'status' => 'failed'
			    ]);
			}
			
			// Rethrow to let Laravel handle the job failure
			throw $e;
		}
	}

	private function uploadImageFromURL(?string $url, ?string $fileBaseName): ?string
	{
		if (empty($url) || empty($fileBaseName)) {
		    return null;
		}
		
		$s3Disk = Storage::disk('s3');

		/* Validate URL */
		if (!filter_var($url, FILTER_VALIDATE_URL)) {
			Log::error('Invalid URL provided: ' . $url);
			return null;
		}

		/* Fetch image content */
		try {
		    $imageContents = file_get_contents($url);
		    if ($imageContents === false || empty($imageContents)) {
			    Log::error('Failed to download image from URL or content is empty: ' . $url);
			    return null;
		    }
		} catch (\Exception $e) {
		    Log::error('Error downloading image', [
		        'url' => $url,
		        'error' => $e->getMessage()
		    ]);
		    return null;
		}

		$fileExtension = 'webp';
		$imageUrl = '';

		try {
			/* Create image resource from content */
			$image = imagecreatefromstring($imageContents);
			if (!$image) {
				Log::error('Failed to create image from URL: ' . $url);
				return null;
			}

			/* Ensure image is in Truecolor format */
			if (imageistruecolor($image) === false) {
				imagepalettetotruecolor($image);
			}

			/* Save original image */
			$originalPath = env('STORAGE_ENV')."/seo-detail/{$fileBaseName}.{$fileExtension}";
			ob_start();
			imagewebp($image);
			$originalData = ob_get_clean();
			$s3Disk->put($originalPath, $originalData);
			$imageUrl = $s3Disk->url($originalPath);
			imagedestroy($image);
			Log::info('Uploaded Images: ' . $imageUrl);
			return $imageUrl;
		} catch (\Exception $e) {
			Log::error('S3 Upload Error: ' . $e->getMessage());
			return null;
		}
	}

	/**
	 * Generate schema based on SEO record type
	 *
	 * @param SeoManagement $seo
	 * @return array
	 */
	private function generateSchema(SeoManagement $seo)
	{
		// Check if the type is 'Product' and relational_id is available
		if ($seo->relational_type === 'App\Models\Product' && $seo->relational_id) {
			// Fetch product data from products table
			$product = Product::find($seo->relational_id);

			if ($product) {
				// Fetch currency and brand names using relationships
				$currencyName = $product->currency ? $product->currency->title : 'USD'; // Default to 'USD' if no currency found
				$brandName = $product->brand ? $product->brand->name : 'Default Brand'; // Default to 'Default Brand' if no brand found

				// Generate schema with product-specific details
				return [
					"@context" => "https://schema.org",
					"@type" => "Product",
					"url" => $seo->url,
					"name" => $seo->meta_title,
					"description" => $seo->meta_description,
					"keywords" => $seo->tags,
					"image" => [
						"@type" => "ImageObject",
						"url" => $seo->og_image_url,
						"name" => $seo->og_image_name,
						"description" => $seo->og_image_alt_text
					],
					"aggregateRating" => [
						"@type" => "AggregateRating",
						"ratingValue" => $seo->schema_rating,
						"reviewCount" => $seo->schema_reviews_count
					],
					"offers" => [
						"@type" => "Offer",
						"priceCurrency" => $currencyName,
						"price" => $product->price ?? 0, // Default to 0 if no price found
						"url" => $seo->url,
					],
					"sku" => $product->sku ?? null, // SKU if available
					"brand" => [
						"@type" => "Brand",
						"name" => $brandName
					],
					"availability" => "https://schema.org/" . ($product->availability ?? 'InStock'), // Default to 'InStock' if no availability found
				];
			}
		}

		// If not a product, return the generic WebPage schema
		return [
			"@context" => "https://schema.org",
			"@type" => str_replace('App\\Models\\', '', $seo->relational_type) ?? 'WebPage',
			"url" => $seo->url,
			"name" => $seo->meta_title,
			"description" => $seo->meta_description,
			"keywords" => $seo->tags,
			"image" => [
				"@type" => "ImageObject",
				"url" => $seo->og_image_url,
				"name" => $seo->og_image_name,
				"description" => $seo->og_image_alt_text
			],
			"aggregateRating" => [
				"@type" => "AggregateRating",
				"ratingValue" => $seo->schema_rating,
				"reviewCount" => $seo->schema_reviews_count
			]
		];
	}

	/**
	 * Handle a job failure.
	 */
	public function failed(\Throwable $exception): void
	{
		$error = $exception->getMessage() . "\n" . $exception->getTraceAsString();
		Log::error(__("SEO Import Job failed"), [
		    'error' => $error,
		    'file' => $exception->getFile(),
		    'line' => $exception->getLine()
		]);
		
		// Update the transaction log if possible
		try {
		    $log = TransactionLog::where('identifier', $this->batch()->id)->first();
		    if ($log) {
		        $descArray = json_decode($log->description, true) ?? ["Errors" => [], "Success Count" => 0, "Failed Count" => 0];
		        if (!isset($descArray["Errors"]) || !is_array($descArray["Errors"])) {
                    $descArray["Errors"] = [];
                }
                
		        $descArray["Errors"][] = [
		            "Job Failure" => $exception->getMessage()
		        ];
		        
		        $log->update([
		            'description' => json_encode($descArray),
		            'status' => 'failed'
		        ]);
		    }
		} catch (\Exception $e) {
		    Log::error('Failed to update transaction log after job failure', [
		        'original_error' => $exception->getMessage(),
		        'log_error' => $e->getMessage()
		    ]);
		}
	}
}