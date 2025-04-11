<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

use Illuminate\Support\Facades\Bus;
use Illuminate\Bus\Batch;
use App\Models\Product;
use App\Models\SeoManagement;
use App\Models\TransactionLog;
use App\Models\SeoSecondaryKeyword;
use App\Jobs\ImportSeoDetailJob;

class SeoManagementController extends Controller
{
	/**
	 * @OA\Get(
	 *     path="/api/seo-management",
	 *     summary="List all SEO records",
	 *     tags={"SEO Management"},
	 *     @OA\Response(response=200, description="List of SEO records"),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function index()
	{
		return SeoManagement::with('secondaryKeywordDetails')->get();
	}

/**
 * @OA\Post(
 *     path="/api/seo-management",
 *     summary="Create a new SEO record",
 *     tags={"SEO Management"},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="multipart/form-data",
 *             @OA\Schema(
 *                 required={"relational_id", "relational_type", "url", "primary_keyword", "monthly_search_volume", "title_tag", "meta_title", "meta_description", "indexing", "created_by"},
 *                 @OA\Property(property="relational_id", type="integer", example=1),
 *                 @OA\Property(property="relational_type", type="string", example="Product"),
 *                 @OA\Property(property="url", type="string", example="https://example.com/page"),
 *                 @OA\Property(property="primary_keyword", type="string", example="best product"),
 *                 @OA\Property(property="monthly_search_volume", type="integer", example=1000),
 *                 @OA\Property(property="title_tag", type="string", example="Awesome Product Title Tag"),
 *                 @OA\Property(property="meta_title", type="string", example="Meta Title Example"),
 *                 @OA\Property(property="meta_description", type="string", example="This is a meta description."),
 *                 @OA\Property(property="internal_links", type="string", example="https://example.com/internal1,https://example.com/internal2"),
 *                 @OA\Property(property="indexing", type="boolean", example=true, description="Whether the page should be indexed (accepts boolean or integer 0/1)"),
 *                 @OA\Property(property="og_title", type="string", example="Open Graph Title"),
 *                 @OA\Property(property="og_description", type="string", example="Open Graph Description"),
 *                 @OA\Property(property="og_image_url", type="string", example="https://example.com/image.jpg"),
 *                 @OA\Property(property="og_image_name", type="string", example="image.jpg"),
 *                 @OA\Property(property="og_image_alt_text", type="string", example="Image alt text"),
 *                 @OA\Property(property="tags", type="string", example="tag1, tag2, tag3"),
 *                 @OA\Property(property="schema_rating", type="integer", example=5),
 *                 @OA\Property(property="schema_reviews_count", type="integer", example=42),
 *                 @OA\Property(property="created_by", type="integer", example=1),
 *                 @OA\Property(property="updated_by", type="integer", example=2),
 *                 @OA\Property(property="og_image_file", type="string", format="binary", description="OG Image File"),
 *                 @OA\Property(property="secondary_keywords", type="string",  description="JSON string containing array of secondary keywords with search volumes")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=201, description="SEO Record Created"),
 *     @OA\Response(response=422, description="Validation error"),
 *     security={{"bearerAuth":{}}}
 * )
 */

	public function store(Request $request)
	{
		try {
		 // Update the validation rules
			$validated = $request->validate([
				'relational_id' => 'required|integer',
				'relational_type' => 'required|string',
				'url' => 'required|string',
				'primary_keyword' => 'required|string',
				'monthly_search_volume' => 'required|integer',
				'title_tag' => 'required|string',
				'meta_title' => 'required|string',
				'meta_description' => 'required|string',
				'internal_links' => 'nullable|string',
				'indexing' => 'required|in:0,1,true,false',
			 'og_title' => 'nullable|string',
			 'og_description' => 'nullable|string',
			 'og_image_url' => 'nullable|string',
			 'og_image_alt_text' => 'nullable|string',
			 'og_image_name' => 'nullable|string',
			 'tags' => 'nullable|string',
			 'schema_rating' => 'nullable|integer',
			 'schema_reviews_count' => 'nullable|integer',
			 'created_by' => 'required|integer',
			 'updated_by' => 'nullable|integer',
			 'og_image_file' => 'nullable|file|image|mimes:jpeg,png,jpg,gif|max:2048',
				'secondary_keywords' => 'nullable|json',  // Accepts a JSON string that will be decoded
			]);

		 // Prepare the data for creating the SEO management record
			$seoData = collect($validated)->except(['secondary_keywords', 'og_image_file'])->toArray();

		 // Convert indexing boolean
		 $seoData['indexing'] = filter_var($validated['indexing'], FILTER_VALIDATE_BOOLEAN);

		 // Handle OG image file upload if provided
			if ($request->hasFile('og_image_file') && $request->file('og_image_file')->isValid()) {
				$storage = app('Illuminate\Support\Facades\Storage');

			 // Define folder path for upload
				$folderPath = env('STORAGE_ENV', 'default') . "/seo-images";

			 // Store the file
				$imagePath = $request->file('og_image_file')->store($folderPath, 's3');

			 // Generate URL for the stored file
				$imageUrl = $storage::disk('s3')->url($imagePath);

			 // Update the og_image_url in the data
				$seoData['og_image_url'] = $imageUrl;

			 // Update the og_image_name if not provided
				if (empty($seoData['og_image_name'])) {
					$seoData['og_image_name'] = $request->file('og_image_file')->getClientOriginalName();
				}
			}
		 $seoData['schema'] = '{}'; // or null if the DB allows
		  // Generate schema and add it to the data (as an array)
		 $schemaArray = $this->generateSchema(new SeoManagement($seoData));

		  // Convert the schema array to a JSON string
		 $seoData['schema'] = json_encode($schemaArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);


		 // Create the SEO record
		 $seo = SeoManagement::create($seoData);

		 // Process secondary keywords
		 if (!empty($validated['secondary_keywords'])) {
			 // Decode the JSON string to array
		 	$secondaryKeywords = json_decode($validated['secondary_keywords'], true);

		 	if (is_array($secondaryKeywords)) {
		 		foreach ($secondaryKeywords as $keyword) {
		 			if (isset($keyword['secondary_keyword']) && isset($keyword['monthly_search_volume'])) {
		 				SeoSecondaryKeyword::create([
		 					'primary_keyword_id' => $seo->id,
		 					'secondary_keyword' => $keyword['secondary_keyword'],
		 					'monthly_search_volume' => $keyword['monthly_search_volume'],
		 				]);
		 			}
		 		}
		 	}
		 }

		 // Generate schema and save
		 $seo->schema = $this->generateSchema($seo);
		 $seo->save();

		 // Return response with loaded secondary keywords
		 return response()->json([
		 	'success' => true,
		 	'message' => 'SEO record created successfully',
		 	'data' => $seo->load('secondaryKeywordDetails')
		 ], 201);
		} catch (\Exception $e) {
		 // Log the error
			\Log::error('SEO Management creation error: ' . $e->getMessage());

		 // Return a proper JSON error response
			return response()->json([
				'success' => false,
				'message' => 'Failed to create SEO record',
				'error' => $e->getMessage()
			], 422);
		}
	}

	/**
 * @OA\Get(
 *     path="/api/seo-management/{relation_id}",
 *     summary="Get a specific SEO record by product relation ID",
 *     tags={"SEO Management"},
 *     @OA\Parameter(
 *         name="relation_id",
 *         in="path",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(response=200, description="Single SEO Record"),
 *     @OA\Response(response=404, description="Not found"),
 *     security={{"bearerAuth":{}}}
 * )
 */
public function show($relation_id)
{
    $seoRecord = SeoManagement::with('secondaryKeywordDetails')
        ->where('relational_id', $relation_id)
        ->first();

    if (!$seoRecord) {
        return response()->json([
            'success' => false,
            'message' => 'SEO record not found for the given relation ID.'
        ], 404);
    }

    $schema = [
        "@context" => "https://schema.org",
        "@type" => "Product",
        "url" => "my-product",
        "name" => "meta title",
        "description" => "This is a description",
        "keywords" => "Danish|Rishi",
        "image" => [
            "@type" => "ImageObject",
            "url" => "",
            "name" => "",
            "description" => ""
        ],
        "aggregateRating" => [
            "@type" => "AggregateRating",
            "ratingValue" => 5,
            "reviewCount" => 0
        ],
        "offers" => [
            "@type" => "Offer",
            "priceCurrency" => "USD",
            "price" => 0,
            "url" => "my-product"
        ],
        "sku" => "Fridge 346",
        "brand" => [
            "@type" => "Brand",
            "name" => "Default Brand"
        ],
        "availability" => "https://schema.org/InStock"
    ];

    // Convert the schema array to a JSON string (clean format)
    $cleanedSchema = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    return response()->json([
        'success' => true,
        'data' => $seoRecord,
        'schema' => $cleanedSchema
    ], 200);
}


	/**
		 * @OA\Post(
		 *     path="/api/seo-management/{id}",
		 *     summary="Update an existing SEO record",
		 *     tags={"SEO Management"},
		 *     @OA\Parameter(
		 *         name="id",
		 *         in="path",
		 *         required=true,
		 *         @OA\Schema(type="integer", example=1),
		 *         description="The ID of the SEO record to update"
		 *     ),
		 *     @OA\RequestBody(
		 *         required=true,
		 *         @OA\MediaType(
		 *             mediaType="multipart/form-data",
		 *             @OA\Schema(
		 *                 required={"relational_id", "relational_type", "url", "primary_keyword", "monthly_search_volume", "title_tag", "meta_title", "meta_description", "indexing", "created_by"},
		 *                 @OA\Property(property="relational_id", type="integer", example=1),
		 *                 @OA\Property(property="relational_type", type="string", example="Product"),
		 *                 @OA\Property(property="url", type="string", example="https://example.com/page"),
		 *                 @OA\Property(property="primary_keyword", type="string", example="best product"),
		 *                 @OA\Property(property="monthly_search_volume", type="integer", example=1000),
		 *                 @OA\Property(property="title_tag", type="string", example="Awesome Product Title Tag"),
		 *                 @OA\Property(property="meta_title", type="string", example="Meta Title Example"),
		 *                 @OA\Property(property="meta_description", type="string", example="This is a meta description."),
		 *                 @OA\Property(property="internal_links", type="string", example="https://example.com/internal1,https://example.com/internal2"),
		*                     @OA\Property(
		*                    property="indexing", 
		*                      type="boolean", 
		*                     example=true, 
		*                        description="Whether the page should be indexed (accepts boolean or integer 0/1)"
		* 						),	 
		*                 @OA\Property(property="og_title", type="string", example="Open Graph Title"),
		*                 @OA\Property(property="og_description", type="string", example="Open Graph Description"),
		*                 @OA\Property(property="og_image_url", type="string", example="https://example.com/image.jpg"),
		*                 @OA\Property(property="og_image_name", type="string", example="image.jpg"),
		*                 @OA\Property(property="og_image_alt_text", type="string", example="Image alt text"),
		*                 @OA\Property(property="tags", type="string", example="tag1, tag2, tag3"),
		*                 @OA\Property(property="schema_rating", type="integer", example=5),
		*                 @OA\Property(property="schema_reviews_count", type="integer", example=42),
		*                 @OA\Property(property="created_by", type="integer", example=1),
		*                 @OA\Property(property="updated_by", type="integer", example=2),
		*                 @OA\Property(property="og_image_file", type="string", format="binary", description="OG Image File"),
		*                 @OA\Property(property="secondary_keywords", 
		*                          type="string", 
		*                            description="JSON string containing array of secondary keywords with search volumes" ),
		*             )
		*         )
		*     ),
		*     @OA\Response(response=200, description="SEO Record Updated"),
		*     @OA\Response(response=422, description="Validation error"),
		*     security={{"bearerAuth":{}}}
		* )
	 */

	public function update(Request $request, $id)
	{
		try {
		 // Validate the incoming data
			$validated = $request->validate([
				'relational_id' => 'required|integer',
				'relational_type' => 'required|string',
				'url' => 'required|string',
				'primary_keyword' => 'required|string',
				'monthly_search_volume' => 'required|integer',
				'title_tag' => 'required|string',
				'meta_title' => 'required|string',
				'meta_description' => 'required|string',
				'internal_links' => 'nullable|string',
				'indexing' => 'required|in:0,1,true,false',
				'og_title' => 'nullable|string',
				'og_description' => 'nullable|string',
				'og_image_url' => 'nullable|string',
				'og_image_alt_text' => 'nullable|string',
				'og_image_name' => 'nullable|string',
				'tags' => 'nullable|string',
				'schema_rating' => 'nullable|integer',
				'schema_reviews_count' => 'nullable|integer',
				'created_by' => 'required|integer',
				'updated_by' => 'nullable|integer',
				'og_image_file' => 'nullable|file|image|mimes:jpeg,png,jpg,gif|max:2048',
				'secondary_keywords' => 'nullable|string',
			]);

		 // Find the existing SEO record by ID
			$seo = SeoManagement::findOrFail($id);

		 // Prepare the data for updating the SEO management record
			$seoData = collect($validated)->except(['secondary_keywords', 'og_image_file'])->toArray();

		 // Convert indexing boolean
		 $seoData['indexing'] = filter_var($validated['indexing'], FILTER_VALIDATE_BOOLEAN);

		 // Handle OG image file upload if provided
			if ($request->hasFile('og_image_file') && $request->file('og_image_file')->isValid()) {
				$storage = app('Illuminate\Support\Facades\Storage');

			 // Define folder path for upload
				$folderPath = env('STORAGE_ENV', 'default') . "/seo-images";

			 // Store the file
				$imagePath = $request->file('og_image_file')->store($folderPath, 's3');

			 // Generate URL for the stored file
				$imageUrl = $storage::disk('s3')->url($imagePath);

			 // Update the og_image_url in the data if provided
				$seoData['og_image_url'] = $imageUrl;

			 // Update the og_image_name if not provided
				if (empty($seoData['og_image_name'])) {
					$seoData['og_image_name'] = $request->file('og_image_file')->getClientOriginalName();
				}
			}

		 // Update the SEO record if there is any change
			foreach ($seoData as $key => $value) {
				if (!empty($value)) {
					$seo->$key = $value;
				}
			}

		 // Generate schema and add it to the data (as an array)
			$schemaArray = $this->generateSchema($seo);
			$seo->schema = json_encode($schemaArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

		 // Save the updated SEO record
			$seo->save();

		 // Process secondary keywords if provided
			// In the update function, replace the secondary keywords section with:
			if (!empty($validated['secondary_keywords'])) {
				// First delete existing secondary keywords
				SeoSecondaryKeyword::where('primary_keyword_id', $seo->id)->delete();
				
				// Parse the secondary keywords - handle both string and array inputs
				$secondaryKeywords = is_string($validated['secondary_keywords']) 
					? json_decode($validated['secondary_keywords'], true)
					: $validated['secondary_keywords'];
				
				if (is_array($secondaryKeywords)) {
					foreach ($secondaryKeywords as $keyword) {
						if (isset($keyword['secondary_keyword']) && isset($keyword['monthly_search_volume'])) {
							SeoSecondaryKeyword::create([
								'primary_keyword_id' => $seo->id,
								'secondary_keyword' => $keyword['secondary_keyword'],
								'monthly_search_volume' => $keyword['monthly_search_volume'],
							]);
						}
					}
				}
			}

		 // Return response with updated SEO record
			return response()->json([
				'success' => true,
				'message' => 'SEO record updated successfully',
				'data' => $seo->load('secondaryKeywordDetails')
			], 200);

		} catch (\Exception $e) {
		 // Log the error
			\Log::error('SEO Management update error: ' . $e->getMessage());

		 // Return a proper JSON error response
			return response()->json([
				'success' => false,
				'message' => 'Failed to update SEO record',
				'error' => $e->getMessage()
			], 422);
		}
	}

	/**
	 * @OA\Delete(
	 *     path="/api/seo-management/{id}",
	 *     summary="Delete an SEO record",
	 *     tags={"SEO Management"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         required=true,
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\Response(response=200, description="SEO Record Deleted"),
	 *     @OA\Response(response=404, description="Not found"),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */

	public function destroy($id)
	{
		$seo = SeoManagement::findOrFail($id);
		$seo->secondaryKeywordDetails()->delete();
		$seo->delete();

		return response()->json(['message' => 'Deleted successfully']);
	}

	private function generateSchema(SeoManagement $seo)
	{
		// Check if the type is 'Product' and relational_id is available
		if ($seo->relational_type === 'Product' && $seo->relational_id) {
			// Fetch product data from 'ec_products' table
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
			"@type" => $seo->relational_type ?? 'WebPage',
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
	 * @OA\Post(
	 *     path="/api/seo-management/import",
	 *     summary="Import seo details from an Excel file",
	 *     tags={"SEO Management"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="multipart/form-data",
	 *             @OA\Schema(
	 *                 required={"upload_file"},
	 *                 @OA\Property(property="upload_file", type="string", format="binary", description="CSV file (.csv) max 5MB")
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function import(Request $request)
	{
		try {
			/* Validate request data */
			$request->validate([
				'upload_file' => 'required|file|mimes:csv,txt|max:5120',
			]);

			$file = $request->file('upload_file');

			$seoFileFormatArray = [
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
			];

			$requiredRowCount = count($seoFileFormatArray);

			$data = [];
			/* Open the CSV file and read its content */
			$rowIndex = 1;
			if (($handle = fopen($file, "r")) !== false) {
				while (($row = fgetcsv($handle, 0, ",", '"', "\\")) !== false) {
					/* Fix unquoted fields and escape special characters */
					$row = array_map(function ($value) {
						/* Add quotes around multiline fields */
						if (strpos($value, "\n") !== false || strpos($value, "\r") !== false) {
							$value = '"' . str_replace('"', '""', $value) . '"';
						}

						/* Check if the value is UTF-8 encoded */
						if (!mb_check_encoding($value, 'UTF-8')) {
							/* Attempt to convert to UTF-8, fallback to ISO-8859-1 if detection fails */
							$value = @mb_convert_encoding($value, 'UTF-8', 'auto') ?: utf8_encode($value);
						}

						/* Remove invalid characters and trim spaces */
						$value = preg_replace('/[^\x20-\x7E\xA0-\xFF]/u', '', $value);
						return trim($value);
					}, $row);

					/* Skip blank rows */
					if (array_filter($row)) {
						if (count($row) != $requiredRowCount) {
							$message = "The data in row $rowIndex is not compatible for import.";

							session()->put('error', $message);
							return back();
						}
						$data[] = $row;
					}
					$rowIndex++;
				}
				fclose($handle);
			}

			/* Remove the header row */
			$header = array_shift($data);

			$requiredHeaderArray = array_keys($seoFileFormatArray);

			if ($missingColumns = array_diff($requiredHeaderArray, $header)) {
				$columns = implode(', ', array_values($missingColumns));
				$missingCount = count($missingColumns);
				return response()->json([
					'success' => true,
					'message' => $missingCount > 1 ? "The uploaded file has an incorrect header. $columns columns are missing." : "The uploaded file has an incorrect header. $columns column is missing."
				]);
			}

			/* Get the total record count */
			$totalRecords = count($data);
			if ($totalRecords == 0) {
				return response()->json([
					'success' => true,
					'message' => "The uploaded CSV file does not contain any records. Please ensure the file has valid data and try again."
				]);
			}

			/* Chunk the data into manageable portions (e.g., 100 rows per chunk) */
			$chunkSize = 100;
			$chunks = array_chunk($data, $chunkSize);

			/* Start import process */
			$batch = Bus::batch([])
			->before(function (Batch $batch) use ($totalRecords) {
				$descArray = [
					"Total Count" => $totalRecords,
					"Success Count" => 0,
					"Failed Count" => 0,
					"Errors" => []
				];
				/* Save transaction log */
				$log = new TransactionLog();
				$log->module = "SEO Management";
				$log->action = "Import";
				$log->identifier = $batch->id;
				$log->status = 'In-progress';
				$log->description = json_encode($descArray, JSON_UNESCAPED_UNICODE);
				$log->created_by = auth()->id() ?? null;
				$log->created_at = now();
				$log->save();
			})
			->finally(function (Batch $batch) {
				$log = TransactionLog::where('identifier', $batch->id)->first();
				TransactionLog::where('id', $log->id)->update([
					'status' => 'Completed',
				]);
			})
			->name("SEO Management Import")
			->dispatch();

			/* Add jobs to the batch for processing chunks */
			foreach ($chunks as $chunk) {
				$data = [
					'seoFileFormatArray' => $seoFileFormatArray,
					'header' => $header,
					'chunk' => $chunk,
					'userId' => auth()->id()
				];
				$batch->add(new ImportSeoDetailJob($data));
			}

			return response()->json([
				'success' => true,
				'message' => 'The import process has been scheduled successfully. Please track it under import log.'
			]);
		} catch(\Exception $exception) {
			return response()->json([
				'success' => false,
				'message' => $exception->getMessage()
			]);
		}
	}
    



	/**
	 * @OA\Post(
	 *     path="/api/seo-management/export",
	 *     summary="Export SEO data to CSV",
	 *     tags={"SEO Management"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"relational_type", "range_from", "range_to"},
	 *             @OA\Property(property="relational_type", type="string", enum={"Product", "Category", "Brand", "Blog"}, example="Product", description="Type of relational entity"),
	 *             @OA\Property(property="relational_id", type="integer", nullable=true, example=5, description="ID of the related entity (optional)"),
	 *             @OA\Property(property="range_from", type="integer", minimum=1, example=1, description="Starting range (must be >= 1)"),
	 *             @OA\Property(property="range_to", type="integer", example=50, description="Ending range (must be >= range_from and at most 2000 more)")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Success",
	 *         @OA\MediaType(mediaType="application/json")
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function export(Request $request)
	{
		/* Validate request data */
		$request->validate([
			'relational_type' => 'required|in:Product,Category,Brand,Blog',
			'range_from' => 'required|integer|min:1',
			'range_to' => 'required|integer|gte:range_from|max:' . ($request->range_from + 2000),
		]);

		/* Determine the full class name based on relational_type */
		$modelClass = 'App\\Models\\' . $request->relational_type;

		/* Fetch records with related secondary keywords */
		$records = SeoManagement::with('secondaryKeywordDetails')
		->where('relational_type', $modelClass)
		->offset($request->range_from - 1)
		->limit($request->range_to - $request->range_from + 1)
		->orderBy('id', 'asc')
		->get();

		/* Define CSV headers */
		$csvHeaders = [
			'Relational Name',
			'Relational ID',
			'Relational Type',
			'URL',
			'Primary Keyword',
			'Primary Monthly Search Volume',
			'Secondary Keyword',
			'Secondary Monthly Search Volume',
			'Title Tag',
			'Meta Title',
			'Meta Description',
			'Internal Links(Separated By |)',
			'Indexing',
			'Og Title',
			'Og Description',
			'Og Image URL',
			'Og Image Alt Text',
			'Og Image Name',
			'Tags(Separated By |)',
		];

		/* Create a StreamedResponse for efficient memory usage */
		$response = new StreamedResponse(function () use ($records, $csvHeaders, $modelClass) {
			$handle = fopen('php://output', 'w');
			fputcsv($handle, $csvHeaders);

			foreach ($records as $record) {
				/* Fetch the relational name based on relational_type and relational_id */
				$relationalName = $modelClass::find($record->relational_id)->name ?? 'N/A';

				/* Process secondary keywords */
				if ($record->secondaryKeywordDetails->isNotEmpty()) {
					foreach ($record->secondaryKeywordDetails as $secondaryKeyword) {
						fputcsv($handle, [
							$relationalName,
							$record->relational_id,
							$record->relational_type,
							$record->url,
							$record->primary_keyword,
							$record->monthly_search_volume,
							$secondaryKeyword->keyword,
							$secondaryKeyword->monthly_search_volume,
							$record->title_tag,
							$record->meta_title,
							$record->meta_description,
							$record->internal_links,
							$record->indexing,
							$record->og_title,
							$record->og_description,
							$record->og_image_url,
							$record->og_image_alt_text,
							$record->og_image_name,
							$record->tags,
						]);
					}
				} else {
					/* If no secondary keywords, write a single line with primary data */
					fputcsv($handle, [
						$relationalName,
						$record->relational_id,
						$record->relational_type,
						$record->url,
						$record->primary_keyword,
						$record->monthly_search_volume,
						'',
						'',
						$record->title_tag,
						$record->meta_title,
						$record->meta_description,
						$record->internal_links,
						$record->indexing,
						$record->og_title,
						$record->og_description,
						$record->og_image_url,
						$record->og_image_alt_text,
						$record->og_image_name,
						$record->tags,
					]);
				}
			}

			fclose($handle);
		});

		$fileName = sprintf(
			'%s_%d-%d_%s.csv',
			$request->relational_type,
			$request->range_from,
			$request->range_to,
			now()->format('Y-m-d')
		);
		$response->headers->set('Content-Type', 'text/csv');
		$response->headers->set('Content-Disposition', 'attachment; filename="' . $fileName . '"');

		return $response;
	}
}
