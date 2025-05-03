<?php

// app/Http/Controllers/SeoDetailController.php

namespace App\Http\Controllers;

use App\Models\SeoDetail;
use Illuminate\Http\Request;

class SeoDetailController extends Controller
{
      /**
     * @OA\Post(
     *     path="/api/seo-details",
     *     summary="Create SEO Details for Product",
     *     tags={"SEO Details"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"primary_keyword", "primary_keyword_search_volume", "url", "meta_title"},
     *             @OA\Property(property="primary_keyword", type="string", example="Product A"),
     *             @OA\Property(property="primary_keyword_search_volume", type="integer", example=500),
     *             @OA\Property(property="secondary_keywords", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="url", type="string", example="https://example.com/product-a"),
     *             @OA\Property(property="meta_title", type="string", example="Meta Title of Product A"),
     *             @OA\Property(property="meta_description", type="string", example="Description of Product A"),
     *             @OA\Property(property="og_title", type="string", example="OG Title of Product A"),
     *             @OA\Property(property="og_description", type="string", example="OG Description of Product A"),
     *             @OA\Property(property="og_image", type="string", example="https://example.com/image.jpg"),
     *             @OA\Property(property="canonical_tag", type="string", example="https://example.com/product-a-canonical"),
     *             @OA\Property(property="internal_links", type="string", example="https://example.com/internal-link-1"),
     *             @OA\Property(property="indexing", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="SEO details successfully created",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid input",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Validation error")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        // Validation logic here...

        // Create or update SEO details for the product/category/page
        $seoDetail = SeoDetail::updateOrCreate(
            ['product_id' => $request->product_id],
            [
                'primary_keyword' => $request->primary_keyword,
                'primary_keyword_search_volume' => $request->primary_keyword_search_volume,
                'secondary_keywords' => json_encode($request->secondary_keywords),
                'secondary_keywords_search_volume' => json_encode($request->secondary_keywords_search_volume),
                'url' => $request->url,
                'title_tag' => $request->title_tag,
                'meta_title' => $request->meta_title,
                'meta_description' => $request->meta_description,
                'og_title' => $request->og_title,
                'og_description' => $request->og_description,
                'og_image' => $request->og_image,
                'canonical_tag' => $request->canonical_tag,
                'internal_links' => $request->internal_links,
                'indexing' => $request->indexing,
            ]
        );

        return response()->json($seoDetail);
    }
}
