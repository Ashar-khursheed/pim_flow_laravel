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
use App\Services\ExcelImporterService;
use App\Repository\ExcelRepository;

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
		if (!auth()->user()->can('list seo mgmt')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}
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
	 *                 @OA\Property(property="secondary_keywords", type="string",  description="JSON string containing array of secondary keywords with search volumes"),
	 * 					@OA\Property(property="paragraph_1", type="string", example="This is the first paragraph."),
	 *					@OA\Property(property="paragraph_2", type="string", example="Second paragraph content."),
	 *					@OA\Property(property="paragraph_3", type="string", example="Another paragraph here."),
	 *					@OA\Property(property="paragraph_4", type="string", example="Final paragraph text."),
	 *					@OA\Property(
	 *						property="popular_tags",
	 *						type="array",
	 *						@OA\Items(type="string", example="tag1"),
	 *						example={"tag1", "tag2", "tag3"},
	 *						description="List of popular tags (stored as JSON array)"
	 *					),
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
		if (!auth()->user()->can('add seo mgmt')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}
		try {
			/* Update the validation rules */
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
				'og_image_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp',
				'secondary_keywords' => 'nullable|json',
				'paragraph_1' => 'nullable|string',
				'paragraph_2' => 'nullable|string',
				'paragraph_3' => 'nullable|string',
				'paragraph_4' => 'nullable|string',
				'popular_tags' => 'nullable',
				'google_shopping_feed_title' => 'nullable|string',
				'google_shopping_feed_description' => 'nullable|string',
				'short_title_variant' => 'nullable|string',
				'gen_type' => 'nullable|integer',
				'cat_desc' => 'nullable|string',
			]);
			

			/* Prepare the data for creating the SEO management record */
			$seoData = collect($validated)->except(['secondary_keywords', 'og_image_file'])->toArray();

			/* Convert indexing boolean */
			$seoData['indexing'] = (int) ($validated['indexing'] == '1' || $validated['indexing'] == 'true' ? 1 : 0);
			/* In your store method */
			if (!empty($validated['popular_tags'])) {
				if (is_string($validated['popular_tags'])) {
					/* Try to decode if it's a JSON string */
					$decoded = json_decode($validated['popular_tags'], true);

					if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
						$seoData['popular_tags'] = $decoded;
					} else {
						/* Fallback: treat it as a plain comma-separated string */
						$seoData['popular_tags'] = array_map('trim', explode(',', $validated['popular_tags']));
					}
				} else {
					$seoData['popular_tags'] = $validated['popular_tags'];
				}
			}
			/* Handle OG image file upload if provided */
			if ($request->hasFile('og_image_file') && $request->file('og_image_file')->isValid()) {
				$storage = app('Illuminate\Support\Facades\Storage');

				/* Define folder path for upload */
				$folderPath = env('STORAGE_ENV', 'default') . "/seo-images";

				/* Store the file */
				$imagePath = $request->file('og_image_file')->store($folderPath, 's3');

				/* Generate URL for the stored file */
				$imageUrl = $storage::disk('s3')->url($imagePath);

				/* Update the og_image_url in the data */
				$seoData['og_image_url'] = $imageUrl;

				/* Update the og_image_name if not provided */
				if (empty($seoData['og_image_name'])) {
					$seoData['og_image_name'] = $request->file('og_image_file')->getClientOriginalName();
				}
			}
			$seoData['schema'] = '{}'; /* or null if the DB allows */
			/* Generate schema and add it to the data (as an array) */
			$schemaArray = $this->generateSchema(new SeoManagement($seoData));

			/* Convert the schema array to a JSON string */
			$seoData['schema'] = json_encode($schemaArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);


			/* Create the SEO record */
			$seo = SeoManagement::create($seoData);

			/* Process secondary keywords */
			if (!empty($validated['secondary_keywords'])) {
				/* Decode the JSON string to array */
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

			/* Generate schema and save */
			$seo->schema = $this->generateSchema($seo);
			$seo->save();

			/* Return response with loaded secondary keywords */
			return response()->json([
				'success' => true,
				'message' => 'SEO record created successfully',
				'data' => $seo->load('secondaryKeywordDetails')
			], 201);
		} catch (\Exception $e) {
			/* Log the error */
			\Log::error('SEO Management creation error: ' . $e->getMessage());

			/* Return a proper JSON error response */
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
	public function show($relation_id, Request $request)
	{
		if (!auth()->user()->can('show seo mgmt')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}
	
		$relationalType = $request->query('relational_type');
	
		$seoRecord = SeoManagement::with('secondaryKeywordDetails')
			->where('relational_id', $relation_id)
			->when($relationalType, function ($query, $relationalType) {
				return $query->where('relational_type', $relationalType);
			})
			->first();
	
		if (!$seoRecord) {
			return response()->json([
				'success' => false,
				'message' => 'SEO record not found for the given relation ID and type.'
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
	 * 					@OA\Property(property="paragraph_1", type="string", example="This is the first paragraph."),
	 *					@OA\Property(property="paragraph_2", type="string", example="Second paragraph content."),
	 *					@OA\Property(property="paragraph_3", type="string", example="Another paragraph here."),
	 *					@OA\Property(property="paragraph_4", type="string", example="Final paragraph text."),
	 *					@OA\Property(
	 *						property="popular_tags",
	 *						type="array",
	 *						@OA\Items(type="string", example="tag1"),
	 *						example={"tag1", "tag2", "tag3"},
	 *						description="List of popular tags"
	 *					),
	 *             ),
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="SEO Record Updated"),
	 *     @OA\Response(response=422, description="Validation error"),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	// public function update(Request $request, $id)
	// {
	// 	if (!auth()->user()->can('update seo mgmt')) {
	// 		return response()->json([
	// 			'success' => false,
	// 			'message' => "You don't have permission to access this module.",
	// 		]);
	// 	}
	// 	try {
	// 		/* Validate the incoming data */
	// 		$validated = $request->validate([
	// 			'relational_id' => 'required|integer',
	// 			'relational_type' => 'required|string',
	// 			'url' => 'required|string',
	// 			'primary_keyword' => 'required|string',
	// 			'monthly_search_volume' => 'required|integer',
	// 			'title_tag' => 'required|string',
	// 			'meta_title' => 'required|string',
	// 			'meta_description' => 'required|string',
	// 			'internal_links' => 'nullable|string',
	// 			'indexing' => 'required|in:0,1,true,false',
	// 			'og_title' => 'nullable|string',
	// 			'og_description' => 'nullable|string',
	// 			'og_image_url' => 'nullable|string',
	// 			'og_image_alt_text' => 'nullable|string',
	// 			'og_image_name' => 'nullable|string',
	// 			'tags' => 'nullable|string',
	// 			'schema_rating' => 'nullable|integer',
	// 			'schema_reviews_count' => 'nullable|integer',
	// 			'created_by' => 'required|integer',
	// 			'updated_by' => 'nullable|integer',
	// 			'og_image_file' => 'nullable|image|mimes:jpeg,png,jpg,webp', // ✅ fixed here
	// 			'secondary_keywords' => 'nullable|string',
	// 			'paragraph_1' => 'nullable|string',
	// 			'paragraph_2' => 'nullable|string',
	// 			'paragraph_3' => 'nullable|string',
	// 			'paragraph_4' => 'nullable|string',
	// 			'popular_tags' => 'nullable|string', /* Expecting array like ["tag1", "tag2"] */
	// 			'google_shopping_feed_title' => 'nullable|string',
	// 			'google_shopping_feed_description' => 'nullable|string',
	// 			'short_title_variant' => 'nullable|string',
	// 			'gen_type' => 'nullable|integer',
				
	// 		]);

	// 		/* Find the existing SEO record by ID */
	// 		$seo = SeoManagement::findOrFail($id);
		

	// 		/* Check if relational_type and relational_id match */
	// 		if ($seo->relational_type !== $validated['relational_type'] || $seo->relational_id != $validated['relational_id']) {
	// 			return response()->json([
	// 				'success' => false,
	// 				'message' => 'The provided relational_type or relational_id does not match the existing record.',
	// 			], 403);
	// 		}
	// 					/* Prepare the data for updating the SEO management record */
	// 		// $seoData = collect($validated)->except(['secondary_keywords', 'og_image_file'])->toArray();
	// 		$seoData = $validated;

	// 		$optionalParagraphs = ['paragraph_1', 'paragraph_2', 'paragraph_3', 'paragraph_4'];
			
	// 		foreach ($optionalParagraphs as $field) {
	// 			if (!$request->has($field)) {
	// 				$seoData[$field] = ''; // force empty if not sent at all
	// 			}
	// 		}
			
	// 		// Remove any keys we still want to skip
	// 		$seoData = collect($seoData)->except(['secondary_keywords', 'og_image_file'])->toArray();
			

	// 		/* Convert indexing boolean */
	// 		$seoData['indexing'] = (int) ($validated['indexing'] == '1' || $validated['indexing'] == 'true' ? 1 : 0);
	// 		if (!empty($validated['popular_tags'])) {
	// 			if (is_string($validated['popular_tags'])) {
	// 				/* Try to decode if it's a JSON string */
	// 				$decoded = json_decode($validated['popular_tags'], true);

	// 				if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
	// 					$seoData['popular_tags'] = $decoded;
	// 				} else {
	// 					/* Fallback: treat it as a plain comma-separated string */
	// 					$seoData['popular_tags'] = array_map('trim', explode(',', $validated['popular_tags']));
	// 				}
	// 			} else {
	// 				$seoData['popular_tags'] = $validated['popular_tags'];
	// 			}
	// 		}

	// 		/* Handle OG image file upload if provided */
	// 		if ($request->hasFile('og_image_file') && $request->file('og_image_file')->isValid()) {
	// 			$storage = app('Illuminate\Support\Facades\Storage');

	// 			/* Define folder path for upload */
	// 			$folderPath = env('STORAGE_ENV', 'default') . "/seo-images";

	// 			/* Store the file */
	// 			$imagePath = $request->file('og_image_file')->store($folderPath, 's3');

	// 			/* Generate URL for the stored file */
	// 			$imageUrl = $storage::disk('s3')->url($imagePath);

	// 			/* Update the og_image_url in the data if provided */
	// 			$seoData['og_image_url'] = $imageUrl;

	// 			/* Update the og_image_name if not provided */
	// 			if (empty($seoData['og_image_name'])) {
	// 				$seoData['og_image_name'] = $request->file('og_image_file')->getClientOriginalName();
	// 			}
	// 		}

	// 	/* Update the SEO record if there is any change */
	// 	foreach ($seoData as $key => $value) {
	// 		// Overwrite even if empty string — but skip only if it's explicitly null
	// 		$seo->$key = $value ?? '';
	// 	}
		


	// 		/* Generate schema and add it to the data (as an array) */
	// 		$schemaArray = $this->generateSchema($seo);
	// 		$seo->schema = json_encode($schemaArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

	// 		/* Save the updated SEO record */
	// 		$seo->save();

	// 		/* Process secondary keywords if provided */
	// 		/* In the update function, replace the secondary keywords section with: */
	// 		if (!empty($validated['secondary_keywords'])) {
	// 			/* First delete existing secondary keywords */
	// 			SeoSecondaryKeyword::where('primary_keyword_id', $seo->id)->delete();

	// 			/* Parse the secondary keywords - handle both string and array inputs */
	// 			$secondaryKeywords = is_string($validated['secondary_keywords'])
	// 			? json_decode($validated['secondary_keywords'], true)
	// 			: $validated['secondary_keywords'];

	// 			if (is_array($secondaryKeywords)) {
	// 				foreach ($secondaryKeywords as $keyword) {
	// 					if (isset($keyword['secondary_keyword']) && isset($keyword['monthly_search_volume'])) {
	// 						SeoSecondaryKeyword::create([
	// 							'primary_keyword_id' => $seo->id,
	// 							'secondary_keyword' => $keyword['secondary_keyword'],
	// 							'monthly_search_volume' => $keyword['monthly_search_volume'],
	// 						]);
	// 					}
	// 				}
	// 			}
	// 		}

	// 		/* Return response with updated SEO record */
	// 		return response()->json([
	// 			'success' => true,
	// 			'message' => 'SEO record updated successfully',
	// 			'data' => $seo->load('secondaryKeywordDetails')
	// 		], 200);

	// 	} catch (\Exception $e) {
	// 		/* Log the error */
	// 		\Log::error('SEO Management update error: ' . $e->getMessage());

	// 		/* Return a proper JSON error response */
	// 		return response()->json([
	// 			'success' => false,
	// 			'message' => 'Failed to update SEO record',
	// 			'error' => $e->getMessage()
	// 		], 422);
	// 	}
	// }
	public function update(Request $request, $relational_type, $id)
	{
		if (!auth()->user()->can('update seo mgmt')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}

		try {
			$validated = $request->validate([
				'relational_id' => 'required|integer',
				// 'relational_type' => 'required|string', // ❌ removed from validation
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
				'og_image_file' => 'nullable|image|mimes:jpeg,png,jpg,webp',
				'secondary_keywords' => 'nullable|string',
				'paragraph_1' => 'nullable|string',
				'paragraph_2' => 'nullable|string',
				'paragraph_3' => 'nullable|string',
				'paragraph_4' => 'nullable|string',
				'popular_tags' => 'nullable|string',
				'google_shopping_feed_title' => 'nullable|string',
				'google_shopping_feed_description' => 'nullable|string',
				'short_title_variant' => 'nullable|string',
				'gen_type' => 'nullable|integer',
				'cat_desc' => 'nullable|string',
			]);

			$seo = SeoManagement::findOrFail($id);

			// ✅ Validate relational_type and relational_id
			if ($seo->relational_type !== $relational_type || $seo->relational_id != $validated['relational_id']) {
				return response()->json([
					'success' => false,
					'message' => 'The provided relational_type or relational_id does not match the existing record.',
				], 403);
			}

			$seoData = $validated;

			// Optional paragraphs
			foreach (['paragraph_1', 'paragraph_2', 'paragraph_3', 'paragraph_4'] as $field) {
				if (!$request->has($field)) {
					$seoData[$field] = '';
				}
			}

			$seoData = collect($seoData)->except(['secondary_keywords', 'og_image_file'])->toArray();
			$seoData['indexing'] = (int) ($validated['indexing'] == '1' || $validated['indexing'] == 'true' ? 1 : 0);

			if (!empty($validated['popular_tags'])) {
				if (is_string($validated['popular_tags'])) {
					$decoded = json_decode($validated['popular_tags'], true);
					if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
						$seoData['popular_tags'] = $decoded;
					} else {
						$seoData['popular_tags'] = array_map('trim', explode(',', $validated['popular_tags']));
					}
				} else {
					$seoData['popular_tags'] = $validated['popular_tags'];
				}
			}

			if ($request->hasFile('og_image_file') && $request->file('og_image_file')->isValid()) {
				$storage = app('Illuminate\Support\Facades\Storage');
				$folderPath = env('STORAGE_ENV', 'default') . "/seo-images";
				$imagePath = $request->file('og_image_file')->store($folderPath, 's3');
				$seoData['og_image_url'] = $storage::disk('s3')->url($imagePath);
				if (empty($seoData['og_image_name'])) {
					$seoData['og_image_name'] = $request->file('og_image_file')->getClientOriginalName();
				}
			}

			foreach ($seoData as $key => $value) {
				$seo->$key = $value ?? '';
			}

			$seo->schema = json_encode($this->generateSchema($seo), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
			$seo->save();

			if (!empty($validated['secondary_keywords'])) {
				SeoSecondaryKeyword::where('primary_keyword_id', $seo->id)->delete();

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

			return response()->json([
				'success' => true,
				'message' => 'SEO record updated successfully',
				'data' => $seo->load('secondaryKeywordDetails')
			], 200);

		} catch (\Exception $e) {
			\Log::error('SEO Management update error: ' . $e->getMessage());

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
		if (!auth()->user()->can('delete seo mgmt')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}
		$seo = SeoManagement::findOrFail($id);
		$seo->secondaryKeywordDetails()->delete();
		$seo->delete();

		return response()->json(['message' => 'Deleted successfully']);
	}

	private function generateSchema(SeoManagement $seo)
	{
		/* Check if the type is 'Product' and relational_id is available */
		if ($seo->relational_type === 'Product' && $seo->relational_id) {
			/* Fetch product data from 'ec_products' table */
			$product = Product::find($seo->relational_id);

			if ($product) {
				/* Fetch currency and brand names using relationships */
				$currencyName = $product->currency ? $product->currency->title : 'USD'; /* Default to 'USD' if no currency found */
				$brandName = $product->brand ? $product->brand->name : 'Default Brand'; /* Default to 'Default Brand' if no brand found */

				/* Generate schema with product-specific details */
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
						"price" => $product->price ?? 0, /* Default to 0 if no price found */
						"url" => $seo->url,
					],
					"sku" => $product->sku ?? null, /* SKU if available */
					"brand" => [
						"@type" => "Brand",
						"name" => $brandName
					],
					"availability" => "https://schema.org/" . ($product->availability ?? 'InStock'), /* Default to 'InStock' if no availability found */
				];
			}
		}

		/* If not a product, return the generic WebPage schema */
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
	 *                 @OA\Property(property="upload_file", type="string", format="binary", description="xlsx file (.xlsx) max 2MB"),
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function import(Request $request, ExcelImporterService $excelImporter)
	{
		if (!auth()->user()->can('import seo mgmt')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}

		/* Validate request data */
		$request->validate([
			'upload_file' => 'required|file|mimes:xlsx|max:2048',
		]);
		try {
			$seoFileFormatArray = seo_import_constants('ALL_FIELDS');

			$excelImporter->processExcelImport(
				$request->file('upload_file'),
				$seoFileFormatArray,
				'SEO Management', /* Module name */
				'JOB_SEO_MGMT', /* Job name */
				'Import SEO Management', /* Batch name */
				ImportSeoDetailJob::class
			);

			return response()->json([
				'success' => true,
				'message' => 'The import process has been scheduled successfully. Please track it under import log.'
			]);
		} catch(\Exception $exception) {
			$error[] = 'Error: ' . $exception->getMessage();
			$error[] = 'File: ' . $exception->getFile();
			$error[] = 'Line: ' . $exception->getLine();
			return response()->json([
				'success' => false,
				'message' => $error
			]);
		}
	}

	/**
	 * @OA\Post(
	 *     path="/api/seo-management/export",
	 *     summary="Export SEO data to Excel",
	 *     tags={"SEO Management"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"relational_type", "range_from", "range_to"},
	 *             @OA\Property(property="relational_type", type="string", enum={"Product", "Category", "Brand", "Blog"}, example="Product", description="Type of relational entity"),
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
	public function export(Request $request, ExcelRepository $excelRepo)
	{
		if (!auth()->user()->can('export seo mgmt')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}

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

		$spreadsheet = $excelRepo->newSpreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setTitle('SEO Data');

		/* Define headers */
		$headers = [
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
			'Tags(Separated By |)'
		];
		$excelRepo->setHeader($sheet, $headers);

		$rowIndex = 2;
		foreach ($records as $record) {
			/* Convert full model class to base name (e.g., App\Models\Category → Category) */
			$relationalTypeName = class_basename($record->relational_type);

			/* Fetch the relational name based on relational_type and relational_id */
			$relationalName = $modelClass::find($record->relational_id)->name ?? 'N/A';

			/* Process secondary keywords */
			if ($record->secondaryKeywordDetails->isNotEmpty()) {
				foreach ($record->secondaryKeywordDetails as $secondary) {
					$row = [
						$relationalName,
						$record->relational_id,
						$relationalTypeName,
						$record->url,
						$record->primary_keyword,
						$record->monthly_search_volume,
						$secondary->secondary_keyword,
						$secondary->monthly_search_volume,
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
						$record->tags
						// $record->paragraph_1,
						// $record->paragraph_2,
						// $record->paragraph_3,
						// $record->paragraph_4,
						// $record->popular_tags,
					];
					$excelRepo->writeRow($sheet, $row, $rowIndex++);
				}
			} else {
				/* If no secondary keywords, write a single line with primary data */
				$row = [
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
					$record->tags
					// $record->paragraph_1,
					// $record->paragraph_2,
					// $record->paragraph_3,
					// $record->paragraph_4,
					// $record->popular_tags,
				];
				$excelRepo->writeRow($sheet, $row, $rowIndex++);
			}
		}

		$fileName = 'seo_management_' . $request->relational_type . '_' . $request->range_from . '-' . $request->range_to . '_' . now()->format('Y-m-d_H-i-s') . '.xlsx';

		return $excelRepo->downloadFile($fileName, $spreadsheet);
	}

}
