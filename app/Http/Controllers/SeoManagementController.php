<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Bus;
use Illuminate\Bus\Batch;
use App\Models\Product;
use App\Models\Category;
use App\Models\Blog;
use App\Models\Brand;
use App\Models\SeoManagement;
use App\Models\TransactionLog;
use App\Models\SeoSecondaryKeyword;
use App\Jobs\ImportSeoDetailJob;
use App\Services\ExcelImporterService;
use App\Repository\ExcelRepository;
use Illuminate\Support\Facades\Storage;
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
	// public function store(Request $request)
	// {
	// 	if (!auth()->user()->can('add seo mgmt')) {
	// 		return response()->json([
	// 			'success' => false,
	// 			'message' => "You don't have permission to access this module.",
	// 		]);
	// 	}
	// 	try {
	// 		/* Update the validation rules */
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
	// 			'og_image_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp',
	// 			'secondary_keywords' => 'nullable|json',
	// 			'paragraph_1' => 'nullable|string',
	// 			'paragraph_2' => 'nullable|string',
	// 			'paragraph_3' => 'nullable|string',
	// 			'paragraph_4' => 'nullable|string',
	// 			'popular_tags' => 'nullable',
	// 			'google_shopping_feed_title' => 'nullable|string',
	// 			'google_shopping_feed_description' => 'nullable|string',
	// 			'short_title_variant' => 'nullable|string',
	// 			'gen_type' => 'nullable|integer',
	// 			'cat_desc' => 'nullable|string',
	// 			'banner_image_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp',
	// 			'banner_image_alt_text' => 'nullable|string',
	// 			'banner_slug' => 'nullable|string', // ✅ NEW
	// 			'popularTag_details' => 'nullable|json', // ✅ NEW


	// 		]);


	// 		/* Prepare the data for creating the SEO management record */
	// 		$seoData = collect($validated)->except(['secondary_keywords', 'og_image_file'])->toArray();

	// 		/* Convert indexing boolean */
	// 		$seoData['indexing'] = (int) ($validated['indexing'] == '1' || $validated['indexing'] == 'true' ? 1 : 0);
	// 		/* In your store method */
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

	// 			/* Update the og_image_url in the data */
	// 			$seoData['og_image_url'] = $imageUrl;

	// 			/* Update the og_image_name if not provided */
	// 			if (empty($seoData['og_image_name'])) {
	// 				$seoData['og_image_name'] = $request->file('og_image_file')->getClientOriginalName();
	// 			}
	// 		}

	// 		if ($request->hasFile('banner_image_file') && $request->file('banner_image_file')->isValid()) {
	// 			$storage = app('Illuminate\Support\Facades\Storage');

	// 			// Define folder path for upload
	// 			$folderPath = env('STORAGE_ENV', 'default') . "/seo-banners";

	// 			// Store the file on S3
	// 			$bannerImagePath = $request->file('banner_image_file')->store($folderPath, 's3');

	// 			// Generate S3 URL
	// 			$bannerImageUrl = $storage::disk('s3')->url($bannerImagePath);

	// 			// Store in DB fields
	// 			$seoData['banner_image_file'] = $bannerImageUrl;
	// 		}
	// 		$seoData['schema'] = '{}'; /* or null if the DB allows */
	// 		/* Generate schema and add it to the data (as an array) */
	// 		$schemaArray = $this->generateSchema(new SeoManagement($seoData));

	// 		/* Convert the schema array to a JSON string */
	// 		$seoData['schema'] = json_encode($schemaArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);


	// 		/* Create the SEO record */
	// 		$seo = SeoManagement::create($seoData);

	// 		/* Process secondary keywords */
	// 		if (!empty($validated['secondary_keywords'])) {
	// 			/* Decode the JSON string to array */
	// 			$secondaryKeywords = json_decode($validated['secondary_keywords'], true);

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

	// 		/* Generate schema and save */
	// 		$seo->schema = $this->generateSchema($seo);
	// 		$seo->save();

	// 		/* Return response with loaded secondary keywords */
	// 		return response()->json([
	// 			'success' => true,
	// 			'message' => 'SEO record created successfully',
	// 			'data' => $seo->load('secondaryKeywordDetails')
	// 		], 201);
	// 	} catch (\Exception $e) {
	// 		/* Log the error */
	// 		\Log::error('SEO Management creation error: ' . $e->getMessage());

	// 		/* Return a proper JSON error response */
	// 		return response()->json([
	// 			'success' => false,
	// 			'message' => 'Failed to create SEO record',
	// 			'error' => $e->getMessage()
	// 		], 422);
	// 	}
	// }
	public function store(Request $request)
	{
		if (!auth()->user()->can('add seo mgmt')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}

		try {
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
				'banner_image_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp',
				'banner_image_alt_text' => 'nullable|string',
				'banner_slug' => 'nullable|string', // ✅ NEW
				'popularTag_details' => 'nullable|json', // ✅ NEW
			]);

			$seoData = collect($validated)->except(['secondary_keywords', 'og_image_file', 'banner_image_file'])->toArray();

			$seoData['indexing'] = (int) ($validated['indexing'] == '1' || $validated['indexing'] == 'true' ? 1 : 0);


			// Create/update the SEO record first
			$seoRecord = SeoManagement::where('url', $request->url)
			->where(function ($query) use ($request) {
				$query->where('relational_id', '!=', $request->relational_id)
				->orWhere('relational_type', '!=', $request->relational_type);
			})
			->first();

			if ($seoRecord) {
				return response()->json([
					'success' => false,
					'message' => "The URL '{$request->url}' is already assigned to {$seoRecord->relational_type} '{$seoRecord->relational->name}'.",
				], 403);
			}

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

			// ✅ Handle popularTag_details JSON
			if (!empty($validated['popularTag_details'])) {
				if (is_string($validated['popularTag_details'])) {
					$seoData['popularTag_details'] = json_decode($validated['popularTag_details'], true);
				} else {
					$seoData['popularTag_details'] = $validated['popularTag_details'];
				}
			}

			if ($request->hasFile('og_image_file') && $request->file('og_image_file')->isValid()) {
				$storage = app('Illuminate\Support\Facades\Storage');
				$folderPath = env('STORAGE_ENV', 'default') . "/seo-images";
				$imagePath = $request->file('og_image_file')->store($folderPath, 's3');
				$imageUrl = $storage::disk('s3')->url($imagePath);
				$seoData['og_image_url'] = $imageUrl;
				if (empty($seoData['og_image_name'])) {
					$seoData['og_image_name'] = $request->file('og_image_file')->getClientOriginalName();
				}
			}

			if ($request->hasFile('banner_image_file') && $request->file('banner_image_file')->isValid()) {
				$storage = app('Illuminate\Support\Facades\Storage');
				$folderPath = env('STORAGE_ENV', 'default') . "/seo-banners";
				$bannerImagePath = $request->file('banner_image_file')->store($folderPath, 's3');
				$bannerImageUrl = $storage::disk('s3')->url($bannerImagePath);
				$seoData['banner_image_file'] = $bannerImageUrl;
			}

			$seoData['schema'] = '{}';
			$schemaArray = $this->generateSchema(new SeoManagement($seoData));
			$seoData['schema'] = json_encode($schemaArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

			$seo = SeoManagement::create($seoData);
			if (in_array(config('app.website'), ['UAE', 'UAE_T', 'SA'])) {
				$seo->translateOrNew('en')->primary_keyword_tr = $seo->primary_keyword;
				$seo->translateOrNew('en')->title_tag_tr = $seo->title_tag;
				$seo->translateOrNew('en')->meta_title_tr = $seo->meta_title;
				$seo->translateOrNew('en')->meta_description_tr = $seo->meta_description;
				$seo->translateOrNew('en')->og_title_tr = $seo->og_title;
				$seo->translateOrNew('en')->og_description_tr = $seo->og_description;
				$seo->translateOrNew('en')->og_image_url_tr = $seo->og_image_url;
				$seo->translateOrNew('en')->og_image_alt_text_tr = $seo->og_image_alt_text;
				$seo->translateOrNew('en')->og_image_name_tr = $seo->og_image_name;
				$seo->translateOrNew('en')->paragraph_1_tr = $seo->paragraph_1;
				$seo->translateOrNew('en')->paragraph_2_tr = $seo->paragraph_2;
				$seo->translateOrNew('en')->paragraph_3_tr = $seo->paragraph_3;
				$seo->translateOrNew('en')->paragraph_4_tr = $seo->paragraph_4;
				$seo->translateOrNew('en')->banner_image_file_tr = $seo->banner_image_file;
				$seo->translateOrNew('en')->banner_image_alt_text_tr = $seo->banner_image_alt_text;
				$seo->save();
			}

			if (!empty($validated['secondary_keywords'])) {
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

			$seo->schema = $this->generateSchema($seo);
			$seo->save();

			return response()->json([
				'success' => true,
				'message' => 'SEO record created successfully',
				'data' => $seo->load('secondaryKeywordDetails')
			], 201);

		} catch (\Exception $e) {

			return response()->json([
				'success' => false,
				'message' => 'Failed to create SEO record',
				'error' => $e->getMessage(),
				'file' => $e->getFile(),
				'line' => $e->getLine(),
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
	 *     @OA\Parameter(
	 *         name="relational_type",
	 *         in="query",
	 *         required=true
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

	public function update(Request $request,$relational_type, $id)
	{   //$relational_type
		if (!auth()->user()->can('update seo mgmt')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}

		try {
			$rules = [
				'relational_id' => 'required|integer',
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
				'schema_rating' => 'nullable|integer|min:1|max:5',
				'schema_reviews_count' => 'nullable|integer|min:0',
				'schema' => 'nullable|json', // ✅ allow full schema JSON
				'created_by' => 'required|integer',
				'updated_by' => 'nullable|integer',
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
				'banner_image_file' => 'nullable',
				'banner_image_alt_text' => 'nullable|string',
				'banner_slug' => 'nullable|string',
				'popularTag_details' => 'nullable|json',
			];

			if ($request->hasFile('og_image_file') && $request->file('og_image_file')->isValid()) {
				$rules['og_image_file'] = 'image|mimes:jpeg,png,jpg,webp|max:2048';
			}

			if ($request->hasFile('banner_image_file') && $request->file('banner_image_file')->isValid()) {
				$rules['banner_image_file'] = 'image|mimes:jpeg,png,jpg,gif,webp';
			}

			$validated = $request->validate($rules);

			$seo = SeoManagement::findOrFail($id);

			// Create/update the SEO record first
			// $seoRecord = SeoManagement::where('url', $request->url)
			// 	->where(function ($query) use ($request) {
			// 		$query->where('relational_id', '!=', $request->relational_id)
			// 			->orWhere('relational_type', '!=', $request->relational_type);
			// 	})
			// 	->first();

			// if ($seoRecord) {
			// 	return response()->json([
			// 		'success' => false,
			// 		'message' => "The URL '{$request->url}' is already assigned to {$seoRecord->relational_type} '{$seoRecord->relational->name}'.",
			// 	], 403);
			// }
			$seoRecord = SeoManagement::where('url', $request->url)
			->where('id', '!=', $id)
			->first();

			if ($seoRecord) {
				$typeNameMap = [
					'App\\Models\\Category' => 'Category',
					'App\\Models\\Product'    => 'Product',
					'App\\Models\\Brand'    => 'Brand',
					'App\\Models\\Blog'     => 'Blog',
				];

				$typeName = $typeNameMap[$seoRecord->relational_type] ?? $seoRecord->relational_type;
				$relatedName = $seoRecord->relational->name
				?? $seoRecord->relational->title
				?? $seoRecord->relational->slug
				?? 'Unknown';

				return response()->json([
					'success' => false,
					'message' => "The URL '{$request->url}' is already assigned to {$typeName} '{$relatedName}'.",
				], 403);
			}
			 //  $relational_type =$request->relational_type;
			if ($seo->relational_type !== $relational_type || $seo->relational_id != $validated['relational_id']) {
				return response()->json([
					'success' => false,
					'message' => 'The provided relational_type or relational_id does not match the existing record.',
				], 403);
			}

			 $seoData = collect($validated)->except(['secondary_keywords', 'og_image_file', 'banner_image_file'])->toArray();

			foreach (['paragraph_1', 'paragraph_2', 'paragraph_3', 'paragraph_4'] as $field) {
				if (!$request->has($field)) {
					$seoData[$field] = '';
				}
			}

			$seoData['indexing'] = (int) ($validated['indexing'] == '1' || $validated['indexing'] == 'true' ? 1 : 0);

			if (isset($validated['url'])) {
				$seoData['url'] = $validated['url'];
			}
			if (isset($validated['schema_rating'])) {
				$seoData['schema_rating'] = (int) $validated['schema_rating'];
			}

			if (isset($validated['schema_reviews_count'])) {
				$seoData['schema_reviews_count'] = (int) $validated['schema_reviews_count'];
			}

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

			if (!empty($validated['popularTag_details'])) {
				if (is_string($validated['popularTag_details'])) {
					$seoData['popularTag_details'] = json_decode($validated['popularTag_details'], true);
				} else {
					$seoData['popularTag_details'] = $validated['popularTag_details'];
				}
			}

			// ✅ Save full schema JSON if provided
			if (!empty($validated['schema'])) {
				$seoData['schema'] = $validated['schema'];
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

			if ($request->hasFile('banner_image_file') && $request->file('banner_image_file')->isValid()) {
				$folderPath = env('STORAGE_ENV', 'default') . "/seo-banners";
				$bannerImagePath = $request->file('banner_image_file')->store($folderPath, 's3');
				$bannerImageUrl = \Storage::disk('s3')->url($bannerImagePath);
				$seoData['banner_image_file'] = $bannerImageUrl;
			}

			// $schemaArray = $this->generateSchema($seo);
			// $seoData['schema'] = json_encode($schemaArray);
			$seo->update($seoData);
			if (!empty($validated['secondary_keywords'])) {

                $secondaryKeywords = json_decode($validated['secondary_keywords'], true);

                if (is_array($secondaryKeywords)) {
                    $incomingKeywords = [];

                    foreach ($secondaryKeywords as $keyword) {

                        if (!empty($keyword['secondary_keyword']) && !empty($keyword['monthly_search_volume'])) {

                            $secondaryKeyword = trim($keyword['secondary_keyword']);
                            $incomingKeywords[] = $secondaryKeyword;
                            SeoSecondaryKeyword::updateOrCreate(
                                [
                                    'primary_keyword_id' => $seo->id,
                                    'secondary_keyword'  => $secondaryKeyword,
                                ],
                                [
                                    'monthly_search_volume' => $keyword['monthly_search_volume'],
                                ]
                            );
                        }
                    }
                    SeoSecondaryKeyword::where('primary_keyword_id', $seo->id)
                        ->whereNotIn('secondary_keyword', $incomingKeywords)
                        ->delete();
                }
            }
			if (in_array(config('app.website'), ['UAE', 'UAE_T', 'SA'])) {
				$seo->translateOrNew('en')->primary_keyword_tr = $seo->primary_keyword;
				$seo->translateOrNew('en')->title_tag_tr = $seo->title_tag;
				$seo->translateOrNew('en')->meta_title_tr = $seo->meta_title;
				$seo->translateOrNew('en')->meta_description_tr = $seo->meta_description;
				$seo->translateOrNew('en')->og_title_tr = $seo->og_title;
				$seo->translateOrNew('en')->og_description_tr = $seo->og_description;
				$seo->translateOrNew('en')->og_image_url_tr = $seo->og_image_url;
				$seo->translateOrNew('en')->og_image_alt_text_tr = $seo->og_image_alt_text;
				$seo->translateOrNew('en')->og_image_name_tr = $seo->og_image_name;
				$seo->translateOrNew('en')->paragraph_1_tr = $seo->paragraph_1;
				$seo->translateOrNew('en')->paragraph_2_tr = $seo->paragraph_2;
				$seo->translateOrNew('en')->paragraph_3_tr = $seo->paragraph_3;
				$seo->translateOrNew('en')->paragraph_4_tr = $seo->paragraph_4;
				$seo->translateOrNew('en')->banner_image_file_tr = $seo->banner_image_file;
				$seo->translateOrNew('en')->banner_image_alt_text_tr = $seo->banner_image_alt_text;
				$seo->save();
			}
			$seo->refresh();

			return response()->json([
				'success' => true,
				'message' => 'SEO record updated successfully',
				'data' => $seo
			], 200);

		} catch (\Illuminate\Validation\ValidationException $e) {
			return response()->json([
				'success' => false,
				'message' => 'Validation failed',
				'errors' => $e->errors()
			], 422);
		} catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
			return response()->json([
				'success' => false,
				'message' => 'SEO record not found'
			], 404);
		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Failed to update SEO record',
				'error' => $e->getMessage()
			], 422);
		}
	}


	// public function update(Request $request, $relational_type, $id)
	// {
	// 	if (!auth()->user()->can('update seo mgmt')) {
	// 		return response()->json([
	// 			'success' => false,
	// 			'message' => "You don't have permission to access this module.",
	// 		]);
	// 	}

	// 	try {
	// 		$rules = [
	// 			'relational_id' => 'required|integer',
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
	// 			'schema_rating' => 'nullable|integer|min:1|max:5',
	// 			'schema_reviews_count' => 'nullable|integer|min:0',
	// 			'created_by' => 'required|integer',
	// 			'updated_by' => 'nullable|integer',
	// 			'secondary_keywords' => 'nullable|string',
	// 			'paragraph_1' => 'nullable|string',
	// 			'paragraph_2' => 'nullable|string',
	// 			'paragraph_3' => 'nullable|string',
	// 			'paragraph_4' => 'nullable|string',
	// 			'popular_tags' => 'nullable|string',
	// 			'google_shopping_feed_title' => 'nullable|string',
	// 			'google_shopping_feed_description' => 'nullable|string',
	// 			'short_title_variant' => 'nullable|string',
	// 			'gen_type' => 'nullable|integer',
	// 			'cat_desc' => 'nullable|string',
	// 			'banner_image_file' => 'nullable',
	// 			'banner_image_alt_text' => 'nullable|string',
	// 			'banner_slug' => 'nullable|string',
	// 			'popularTag_details' => 'nullable|json',
	// 		];

	// 		// Only validate og_image_file if present
	// 		if ($request->hasFile('og_image_file') && $request->file('og_image_file')->isValid()) {
	// 			$rules['og_image_file'] = 'image|mimes:jpeg,png,jpg,webp|max:2048';
	// 		}

	// 		if ($request->hasFile('banner_image_file') && $request->file('banner_image_file')->isValid()) {
	// 			$rules['banner_image_file'] = 'image|mimes:jpeg,png,jpg,gif,webp';
	// 		}

	// 		$validated = $request->validate($rules);

	// 		$seo = SeoManagement::findOrFail($id);

	// 		if ($seo->relational_type !== $relational_type || $seo->relational_id != $validated['relational_id']) {
	// 			return response()->json([
	// 				'success' => false,
	// 				'message' => 'The provided relational_type or relational_id does not match the existing record.',
	// 			], 403);
	// 		}

	// 		// Prepare SEO data - exclude files but keep schema fields
	// 		$seoData = collect($validated)->except(['secondary_keywords', 'og_image_file', 'banner_image_file'])->toArray();

	// 		// Handle paragraph fields - set to empty string if not provided
	// 		foreach (['paragraph_1', 'paragraph_2', 'paragraph_3', 'paragraph_4'] as $field) {
	// 			if (!$request->has($field)) {
	// 				$seoData[$field] = '';
	// 			}
	// 		}

	// 		// Convert indexing to integer
	// 		$seoData['indexing'] = (int) ($validated['indexing'] == '1' || $validated['indexing'] == 'true' ? 1 : 0);

	// 		// Handle schema fields explicitly
	// 		if (isset($validated['schema_rating'])) {
	// 			$seoData['schema_rating'] = (int) $validated['schema_rating'];
	// 		}

	// 		if (isset($validated['schema_reviews_count'])) {
	// 			$seoData['schema_reviews_count'] = (int) $validated['schema_reviews_count'];
	// 		}

	// 		// Handle popular_tags
	// 		if (!empty($validated['popular_tags'])) {
	// 			if (is_string($validated['popular_tags'])) {
	// 				$decoded = json_decode($validated['popular_tags'], true);
	// 				if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
	// 					$seoData['popular_tags'] = $decoded;
	// 				} else {
	// 					$seoData['popular_tags'] = array_map('trim', explode(',', $validated['popular_tags']));
	// 				}
	// 			} else {
	// 				$seoData['popular_tags'] = $validated['popular_tags'];
	// 			}
	// 		}

	// 		// Handle popularTag_details
	// 		if (!empty($validated['popularTag_details'])) {
	// 			if (is_string($validated['popularTag_details'])) {
	// 				$seoData['popularTag_details'] = json_decode($validated['popularTag_details'], true);
	// 			} else {
	// 				$seoData['popularTag_details'] = $validated['popularTag_details'];
	// 			}
	// 		}

	// 		// Handle OG image file upload
	// 		if ($request->hasFile('og_image_file') && $request->file('og_image_file')->isValid()) {
	// 			$storage = app('Illuminate\Support\Facades\Storage');
	// 			$folderPath = env('STORAGE_ENV', 'default') . "/seo-images";
	// 			$imagePath = $request->file('og_image_file')->store($folderPath, 's3');
	// 			$seoData['og_image_url'] = $storage::disk('s3')->url($imagePath);
	// 			if (empty($seoData['og_image_name'])) {
	// 				$seoData['og_image_name'] = $request->file('og_image_file')->getClientOriginalName();
	// 			}
	// 		}

	// 		// Handle banner image file upload
	// 		if ($request->hasFile('banner_image_file') && $request->file('banner_image_file')->isValid()) {
	// 			$folderPath = env('STORAGE_ENV', 'default') . "/seo-banners";
	// 			$bannerImagePath = $request->file('banner_image_file')->store($folderPath, 's3');
	// 			$bannerImageUrl = \Storage::disk('s3')->url($bannerImagePath);
	// 			$seoData['banner_image_file'] = $bannerImageUrl;
	// 		}

	// 		// Update the SEO record
	// 		$seo->update($seoData);

	// 		// Refresh the model to get updated data
	// 		$seo->refresh();

	// 		return response()->json([
	// 			'success' => true,
	// 			'message' => 'SEO record updated successfully',
	// 			'data' => $seo
	// 		], 200);

	// 	} catch (\Illuminate\Validation\ValidationException $e) {
	// 		return response()->json([
	// 			'success' => false,
	// 			'message' => 'Validation failed',
	// 			'errors' => $e->errors()
	// 		], 422);
	// 	} catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
	// 		return response()->json([
	// 			'success' => false,
	// 			'message' => 'SEO record not found'
	// 		], 404);
	// 	} catch (\Exception $e) {
	// 		return response()->json([
	// 			'success' => false,
	// 			'message' => 'Failed to update SEO record',
	// 			'error' => $e->getMessage()
	// 		], 422);
	// 	}
	// }

	/**
	 * @OA\Post(
	 *     path="/api/seoManagement/schema-update/{seo_id}",
	 *     summary="Update an existing SEO schema entry",
	 *     description="This endpoint updates the details of an existing SEO schema record based on the provided SEO ID.",
	 *     tags={"SEO Management"},
	 *     @OA\Parameter(
	 *         name="seo_id",
	 *         in="path",
	 *         required=true,
	 *         @OA\Schema(type="integer", example=35866),
	 *         description="The unique ID of the SEO schema to update"
	 *     ),
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="multipart/form-data",
	 *             @OA\Schema(
	 *                 required={"relational_id", "relational_type"},
	 *                 @OA\Property(property="relational_id", type="integer", example=1915, description="The related entity ID"),
	 *                 @OA\Property(property="relational_type", type="string", example="Product", description="Type of relation, e.g., Product")
	 *
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="SEO schema updated successfully",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="status", type="boolean", example=true),
	 *             @OA\Property(property="message", type="string", example="SEO schema updated successfully."),
	 *             @OA\Property(property="data", type="object")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=422,
	 *         description="Validation error"
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */

	public function schemaUpdate(Request $request, $seo_id)
	{
		$validated = $request->validate([
			'relational_id' => 'required|integer|exists:seo_management,relational_id',
			'relational_type' => 'required|string',

		]);

		$relational_type = $request->relational_type;

		$relational_id = $request->relational_id;

		$seo = SeoManagement::findOrFail($seo_id);

		if ($seo->relational_type !== $relational_type || $seo->relational_id != $validated['relational_id']) {
			return response()->json([
				'success' => false,
				'message' => 'The provided relational_type or relational_id does not match the existing record.',
			], 403);
		}

		try {

			$schemaArray = $this->generateSchema($seo);
			$seo->schema = json_encode($schemaArray);
			$seo->save();

			return response()->json([
				'success' => true,
				'message' => 'SEO record updated successfully',
				'data' => $seo
			], 200);

		} catch (\Illuminate\Validation\ValidationException $e) {
			return response()->json([
				'success' => false,
				'message' => 'Validation failed',
				'errors' => $e->errors()
			], 422);
		} catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
			return response()->json([
				'success' => false,
				'message' => 'SEO record not found'
			], 404);
		} catch (\Exception $e) {
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

		/* Proceed with deletion */
		if (method_exists($seo, 'translations')) {
			$seo->translations()->delete();
		}
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
				$firstSupplier = $product->productSuppliers->first();
				$url = $product->parent_category_url() . '/' .
				$product->category_url() . '/' .
				($product->seoProductUrl->url ?? "");

				/* Generate schema with product-specific details */
				return [
					"@context" => "https://schema.org",
					"@type" => "Product",
					"url" => $url,
					"name" => $seo->meta_title,
					"keywords" => $seo->tags,
					"description" => $seo->meta_description,
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
						"price" => $firstSupplier->price ?? 0, /* Default to 0 if no price found */
						"url" => $url,
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

		if ($seo->relational_type === 'Category' && $seo->relational_id) {

			$category = Category::with(['parent.parent.parent'])
			->where('id', $seo->relational_id)
			->first(['id', 'name', 'slug', 'order', 'parent_id']);

			$url = null;
			if ($category) {
				$url = $this->getCategoryPath($category);
			}


			/* If not a product, return the generic WebPage schema */
			// return [
			// 	"@context" => "https://schema.org",
			// 	"@type" => $seo->relational_type ?? 'WebPage',
			// 	"url" => $url,
			// 	"name" => $seo->meta_title,
			// 	"description" => $seo->meta_description,
			// 	"keywords" => $seo->tags,
			// 	"image" => [
			// 		"@type" => "ImageObject",
			// 		"url" => $seo->og_image_url,
			// 		"name" => $seo->og_image_name,
			// 		"description" => $seo->og_image_alt_text
			// 	],
			// 	"aggregateRating" => [
			// 		"@type" => "AggregateRating",
			// 		"ratingValue" => $seo->schema_rating,
			// 		"reviewCount" => $seo->schema_reviews_count
			// 	]
			// ];

			return [
				[
					"@context" => "https://schema.org",
					"@type" => $seo->relational_type ?? "CollectionPage",
					"name" => $seo->meta_title,
					"description" => $seo->meta_description,
					"url" => $url,
					"mainEntity" => [
						"@type" => "ItemList",
						"name" => $seo->primary_keyword,
						"description" => $seo->meta_description,
						"itemListElement" => [],
					],
					"image" => [
						$seo->og_image_url,
					],
				],
				[
					"@context" => "https://schema.org",
					"@type" => "BreadcrumbList",
					"itemListElement" => [
						[
							"@type" => "ListItem",
							"position" => 1,
							"name" => "Home",
							"item" => config('app.url'),
						],
						[
							"@type" => "ListItem",
							"position" => 2,
							"name" => $seo->meta_title,
							"item" => config('app.url').'/'.$url,
						],
					],
				],
			];

		}
		if ($seo->relational_type === 'Brand' && $seo->relational_id) {

			$brand = Brand::findOrFail($seo->relational_id);
			$url = null;

			if($brand){
				$url = 'brands/'.$brand->seoUrl->url;
			}

			/* If not a Brand, return the generic WebPage schema */
			return [
				"@context" => "https://schema.org",
				"@type" => $seo->relational_type ?? 'WebPage',
				"url" => $url,
				"name" => $seo->meta_title,
				"description" => $seo->meta_description,
				"keywords" => $seo->tags,
				"sameAs"=>[],
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

		if ($seo->relational_type === 'Blog' && $seo->relational_id) {

			$blog = Blog::findOrFail($seo->relational_id);
			$url = null;

			if($blog){
				$url = '/blog/'.$blog->slug;
			}

			/* If not a Blog, return the generic WebPage schema */
			return [
				"@context" => "https://schema.org",
				"@type" => $seo->relational_type ?? 'WebPage',
				"url" => $url,
				"name" => $seo->meta_title,
				"headline" => $seo->primary_keyword,
				"description" => $seo->meta_description,
				"keywords" => $seo->tags,
				"mainEntityOfPage" => [
					"@type" => "WebPage",
					"@id" => $url,
				],
				"author" => [
					"@type" => "Person",
					"name" => "Asha Verma",
					"url" => config('app.url'),
				],
				"publisher" => [
					"@type" => "Organization",
					"name" => "Horeca Store",
					"logo" => [
						"@type" => "ImageObject",
						"url" => $seo->og_image_url,
					],
				],
				"image" => [
					"@type" => "ImageObject",
					"url" => $seo->og_image_url,
					"name" => $seo->og_image_name,
					"description" => $seo->og_image_alt_text,
				],
				"aggregateRating" => [
					"@type" => "AggregateRating",
					"ratingValue" => $seo->schema_rating,
					"reviewCount" => $seo->schema_reviews_count,
				],
			];


		}



		/* If not a product, return the generic WebPage schema */
		// return [
		// 	"@context" => "https://schema.org",
		// 	"@type" => $seo->relational_type ?? 'WebPage',
		// 	"url" => config('app.url').'/'.$seo->url,
		// 	"name" => $seo->meta_title,
		// 	"description" => $seo->meta_description,
		// 	"keywords" => $seo->tags,
		// 	"image" => [
		// 		"@type" => "ImageObject",
		// 		"url" => $seo->og_image_url,
		// 		"name" => $seo->og_image_name,
		// 		"description" => $seo->og_image_alt_text
		// 	],
		// 	"aggregateRating" => [
		// 		"@type" => "AggregateRating",
		// 		"ratingValue" => $seo->schema_rating,
		// 		"reviewCount" => $seo->schema_reviews_count
		// 	]
		// ];
	}

	// Helper method to build category path
	private function getCategoryPath($category)
	{
		$path = [$category->slug];
		$parent = $category->parent;

		while ($parent) {
			array_unshift($path, $parent->slug);
			$parent = $parent->parent;
		}

		return implode('/', $path);
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
				config('app.website') . '_SEO_MGMT', /* Job name */
				'Import SEO Management', /* Batch name */
				ImportSeoDetailJob::class
			);

			return response()->json([
				'success' => true,
				'message' => 'The import process has been scheduled successfully. Please track it under import log.'
			]);
		} catch (\Exception $exception) {
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
		$modelClass = Str::startsWith($request->relational_type, 'App\\Models\\') ? $request->relational_type : 'App\\Models\\' . $request->relational_type;

		/* Fetch records with related secondary keywords */
		$records = SeoManagement::with('secondaryKeywordDetails')
		->where('relational_type', $request->relational_type)
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
			$relationalTypeName = Str::startsWith($record->relational_type, 'App\\Models\\') ? class_basename($record->relational_type) : $record->relational_type;

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

	/**
	 * @OA\Get(
	 *     path="/api/seo-management/check-url",
	 *     summary="Check URL slug availability and existence",
	 *     description="Validates if a URL slug is available or already exists for SEO management across different content types",
	 *     operationId="checkUrlSlugAvailability",
	 *     tags={"SEO Management"},
	 *     @OA\Parameter(name="type", in="query", required=true, @OA\Schema(type="string", enum={"Product", "Category", "Brand", "Blog"}, example="Product")),
	 *     @OA\Parameter(name="url", in="query", required=true, @OA\Schema(type="string", example="kitchen-equipment")),
	 *     @OA\Response(response=200, description="URL availability checked successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function checkURL(Request $request)
	{
		$request->validate([
			'type' => 'required|string|in:Product,Category,Brand,Blog',
			'url' => 'required|string'
		]);

		$seoRecord = SeoManagement::where('relational_type', $request->type)->where('url', $request->url)->first();
		if ($seoRecord) {
			return response()->json([
				'success' => false,
				'data' => "The URL '{$request->url}' is already assigned to {$request->type} '{$seoRecord->relational->name}'.",
			]);
		}

		return response()->json([
			'success' => true,
			'data' => "The URL '{$request->url}' is available and not assigned to any {$request->type}.",
		], 200);
	}

	/**
	 * @OA\Post(
	 *     path="/api/seo-management/save-translation",
	 *     summary="Save or update SEO translation",
	 *     description="This endpoint saves or updates translations for SEO management.",
	 *     tags={"SEO Management"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="multipart/form-data",
	 *             @OA\Schema(
	 *                 required={"id", "locale", "primary_keyword", "title_tag", "meta_title", "meta_description"},
	 *                 @OA\Property(property="id", type="integer", example=1, description="ID of the SEO record to translate"),
	 *                 @OA\Property(property="locale", type="string", example="ar", description="Locale code for translation (e.g. ar)"),
	 *                 @OA\Property(property="primary_keyword", type="string", example="best restaurant equipment", description="Primary keyword"),
	 *                 @OA\Property(property="title_tag", type="string", example="Restaurant Equipment | HorecaStore", description="Title tag"),
	 *                 @OA\Property(property="meta_title", type="string", example="Best Restaurant Equipment", description="Meta title"),
	 *                 @OA\Property(property="meta_description", type="string", example="Find the best restaurant equipment", description="Meta description"),
	 *                 @OA\Property(property="og_title", type="string", example="Restaurant Equipment", description="OG title"),
	 *                 @OA\Property(property="og_description", type="string", example="Quality equipment for restaurants", description="OG description"),
	 *                 @OA\Property(property="og_image_file", type="string", format="binary", description="OG image file"),
	 *                 @OA\Property(property="og_image_alt_text", type="string", example="Restaurant equipment image", description="OG image alt text"),
	 *                 @OA\Property(property="og_image_name", type="string", example="equipment.jpg", description="OG image name"),
	 *                 @OA\Property(property="paragraph_1", type="string", example="First paragraph content", description="Paragraph 1"),
	 *                 @OA\Property(property="paragraph_2", type="string", example="Second paragraph content", description="Paragraph 2"),
	 *                 @OA\Property(property="paragraph_3", type="string", example="Third paragraph content", description="Paragraph 3"),
	 *                 @OA\Property(property="paragraph_4", type="string", example="Fourth paragraph content", description="Paragraph 4"),
	 *                 @OA\Property(property="banner_image_file", type="string", format="binary", description="Banner image file"),
	 *                 @OA\Property(property="banner_image_alt_text", type="string", example="Banner image", description="Banner image alt text")
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function saveTranslation(Request $request)
	{
		/* Validate request data */
		$validated = $request->validate([
			'id' => 'required|exists:seo_management,id',
			'locale' => 'required|string|in:ar',
			'primary_keyword' => 'required|string',
			'title_tag' => 'required|string',
			'meta_title' => 'required|string',
			'meta_description' => 'required|string',
			'og_title' => 'nullable|string',
			'og_description' => 'nullable|string',
			'og_image_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp',
			'og_image_alt_text' => 'nullable|string',
			'og_image_name' => 'nullable|string',
			'paragraph_1' => 'nullable|string',
			'paragraph_2' => 'nullable|string',
			'paragraph_3' => 'nullable|string',
			'paragraph_4' => 'nullable|string',
			'banner_image_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp',
			'banner_image_alt_text' => 'nullable|string',
		]);

		$seo = SeoManagement::find($validated['id']);

		if (!$seo) {
			return response()->json([
				'success' => false,
				'message' => __("SEO record not found"),
			], 404);
		}

		DB::beginTransaction();
		try {
			$locale = $validated['locale'];

			/* Handle banner image upload if provided */
			if ($request->hasFile('banner_image_file')) {
				$bannerImage = $request->file('banner_image_file');
				$bannerImagePath = $bannerImage->store('seo/banners', 'public');
				$validated['banner_image_file'] = $bannerImagePath;
			}

			$validated['og_image_url'] = uploadImageToWebpS3FromFile($request, 'og_image_file', env('STORAGE_ENV') . '/seo/og_img');
			$validated['banner_image_file'] = uploadImageToWebpS3FromFile($request, 'banner_image_file', env('STORAGE_ENV') . '/seo/banners');

			/* Update SEO translation - required fields */
			$seo->translateOrNew($locale)->primary_keyword_tr = $validated['primary_keyword'];
			$seo->translateOrNew($locale)->title_tag_tr = $validated['title_tag'];
			$seo->translateOrNew($locale)->meta_title_tr = $validated['meta_title'];
			$seo->translateOrNew($locale)->meta_description_tr = $validated['meta_description'];

			/* Update SEO translation - optional fields */
			if (isset($validated['og_title'])) {
				$seo->translateOrNew($locale)->og_title_tr = $validated['og_title'];
			}
			if (isset($validated['og_description'])) {
				$seo->translateOrNew($locale)->og_description_tr = $validated['og_description'];
			}
			if (isset($validated['og_image_url'])) {
				$seo->translateOrNew($locale)->og_image_url_tr = $validated['og_image_url'];
			}
			if (isset($validated['og_image_alt_text'])) {
				$seo->translateOrNew($locale)->og_image_alt_text_tr = $validated['og_image_alt_text'];
			}
			if (isset($validated['og_image_name'])) {
				$seo->translateOrNew($locale)->og_image_name_tr = $validated['og_image_name'];
			}
			if (isset($validated['paragraph_1'])) {
				$seo->translateOrNew($locale)->paragraph_1_tr = $validated['paragraph_1'];
			}
			if (isset($validated['paragraph_2'])) {
				$seo->translateOrNew($locale)->paragraph_2_tr = $validated['paragraph_2'];
			}
			if (isset($validated['paragraph_3'])) {
				$seo->translateOrNew($locale)->paragraph_3_tr = $validated['paragraph_3'];
			}
			if (isset($validated['paragraph_4'])) {
				$seo->translateOrNew($locale)->paragraph_4_tr = $validated['paragraph_4'];
			}
			if (isset($validated['banner_image_file'])) {
				$seo->translateOrNew($locale)->banner_image_file_tr = $validated['banner_image_file'];
			}
			if (isset($validated['banner_image_alt_text'])) {
				$seo->translateOrNew($locale)->banner_image_alt_text_tr = $validated['banner_image_alt_text'];
			}

			$seo->save();

			DB::commit();

			return response()->json([
				'success' => true,
				'message' => __("Translations updated successfully."),
				'data' => $seo->fresh(),
			]);
		} catch (\Exception $e) {
			DB::rollBack();

			return response()->json([
				'success' => false,
				'message' => __("err_update"),
				'error' => $e->getMessage(),
			], 500);
		}
	}
}
