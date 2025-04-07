<?php
// app/Http/Controllers/SeoManagementController.php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SeoManagement;
use App\Models\Product;
use App\Models\SeoSecondaryKeyword;

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
        return SeoManagement::with('secondaryKeywords')->get();
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
 *                 @OA\Property(property="indexing", type="integer", enum={0, 1}, example=1, description="Use 1 for true, 0 for false"),
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
  *                @OA\Property(
 *                   property="secondary_keywords",
 *                   type="array",
 *                   @OA\Items(
 *                       type="object",
 *                           @OA\Property(property="secondary_keyword", type="string", example="related keyword"),
 *                           @OA\Property(property="monthly_search_volume", type="integer", example=500)
 *                    )
 *                  )
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
             'indexing' => 'required|boolean',  // Laravel will convert 'true', 'false', 0, 1, etc.
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
             'secondary_keywords' => 'nullable|string',  // Changed to string since we'll decode it
         ]);
 
         // Prepare the data for creating the SEO management record
         $seoData = collect($validated)->except(['secondary_keywords', 'og_image_file'])->toArray();
         
         // Convert indexing boolean
         $seoData['indexing'] = (bool)$validated['indexing'];
         
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
             'data' => $seo->load('secondaryKeywords')
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
 *     path="/api/seo-management/{id}",
 *     summary="Get a specific SEO record",
 *     tags={"SEO Management"},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(response=200, description="Single SEO Record"),
 *     @OA\Response(response=404, description="Not found"),
 *     security={{"bearerAuth":{}}}
 * )
 */

    public function show($id)
    {
        return SeoManagement::with('secondaryKeywords')->findOrFail($id);
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
 *                 @OA\Property(property="indexing", type="integer", enum={0, 1}, example=1, description="Use 1 for true, 0 for false"),
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
 *                 @OA\Property(
 *                    property="secondary_keywords",
 *                    type="array",
 *                    @OA\Items(
 *                        type="object",
 *                            @OA\Property(property="secondary_keyword", type="string", example="related keyword"),
 *                            @OA\Property(property="monthly_search_volume", type="integer", example=500)
 *                     )
 *                   )
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
             'indexing' => 'required|boolean',
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
         $seoData['indexing'] = (bool)$validated['indexing'];
 
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
 
         // Return response with updated SEO record
         return response()->json([
             'success' => true,
             'message' => 'SEO record updated successfully',
             'data' => $seo->load('secondaryKeywords')
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
        $seo->secondaryKeywords()->delete();
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



    // private function generateSchema(SeoManagement $seo)
    // {
    //     return [
    //         "@context" => "https://schema.org",
    //         "@type" => $seo->relational_type ?? 'WebPage',
    //         "url" => $seo->url,
    //         "name" => $seo->meta_title,
    //         "description" => $seo->meta_description,
    //         "keywords" => $seo->tags,
    //         "image" => [
    //             "@type" => "ImageObject",
    //             "url" => $seo->og_image_url,
    //             "name" => $seo->og_image_name,
    //             "description" => $seo->og_image_alt_text
    //         ],
    //         "aggregateRating" => [
    //             "@type" => "AggregateRating",
    //             "ratingValue" => $seo->schema_rating,
    //             "reviewCount" => $seo->schema_reviews_count
    //         ]
    //     ];
    // }
}
