<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Support\Facades\Cache;
class ProductXMLFeedWatchController extends Controller
{

    /**
     * @OA\Get(
     *     path="/api/feed/products.xml",
     *     summary="Get product feed for DataFeedWatch",
     *     description="Returns dynamic XML feed with all products for DataFeedWatch integration",
     *     tags={"Product Feed XML"},     
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\MediaType(
     *             mediaType="application/xml",
     *             example=""
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid parameters"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error"
     *     )
     * )
     */
    public function getProductFeed(Request $request)
    {
        // Cache the feed for 1 hour
        $cacheKey = 'datafeedwatch_feed_' . md5($request->fullUrl());

        $data = Cache::remember($cacheKey, 3600, function () use ($request) {
            return $this->generateProductFeed($request);
        });
        return response()->json($data, 200);
    }

    private function generateProductFeed(Request $request)
    {

        $perPage = $request->input('per_page', 50);

        $sortBy = $request->input('sort_by', 'id');
        $sortDirection = $request->input('sort_direction', 'desc');

        // Validate sort columns to prevent SQL injection
        $allowedSortColumns = ['id', 'name', 'sku', 'brand_id', 'status', 'gen_type', 'approved'];
        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'id'; // Default to id if invalid column
        }

        // Validate sort direction
        if (!in_array(strtolower($sortDirection), ['asc', 'desc'])) {
            $sortDirection = 'desc'; // Default to descending if invalid direction
        }

        $query = Product::with([
            'brand:id,name',
            'categories:id,name',
            'slug:id,key,reference_id',
            'productSuppliers.vendor:id,name', // Updated to include vendor relationship
            'vendors:id,name' // Make sure to select the name field
        ])
            ->select(['id', 'name', 'sku', 'images', 'brand_id', 'status', 'gen_type', 'approved']);



        $query->where('status', 'published');



        $products = $query->orderBy($sortBy, $sortDirection)
            ->paginate($perPage);

        /* Formatting response */
        $formattedProducts = $products->map(function ($product) {
            $firstSupplier = $product->productSuppliers->first();
 
            $margin = $firstSupplier->sale_price - $firstSupplier->price;
            $marginPercent = $firstSupplier->sale_price > 0
                ? ($margin / $firstSupplier->sale_price) * 100
                : 0;

                   if (is_string($product->description)) {
                $decoded = json_decode($product->description, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $description = array_values(array_filter(array_map(function ($item) {
                        if (is_null($item) || strtolower($item) === 'null') {
                            return null;
                        }

                        // Remove all &nbsp; (HTML and UTF-8) from the string
                        $item = str_replace(['&nbsp;', "\xc2\xa0"], ' ', $item);

                        // Optionally clean up extra spaces
                        $item = preg_replace('/\s+/', ' ', $item); // collapse spaces
                        $item = trim($item);

                        // Still keep <p> tags or not? Your call — if not, uncomment below:
                        // $item = strip_tags($item);

                        return $item !== '' ? $item : null;
                    }, $decoded)));
                } else {
                    $description = [$product->description];
                }
            }
            return [
                'id' => $product->id,
                'name' => $product->name,
                'gen_type' => $product->gen_type,
                'approved' => $product->approved,
                'sku' => $product->sku,
                'image' => ($imageUrls = json_decode($product->images, true)) && isset($imageUrls[0]) ? $imageUrls[0] : null,
                'brand' => optional($product->brand)->name,
                'status' => $product->status,
                'quote_available' => $product->quote_available,
                'price' => $firstSupplier->price,
                'sale_price' => $firstSupplier->sale_price,
                'vendor_id' => $firstSupplier->vendor_id,
                'vendor_name' => $product->vendors->pluck('name')->first(),
                'margin' => $margin,
                'margin_percent' => round($marginPercent, 2),
                'product_family' => $product->categories->pluck('name')->toArray(),
                'taxonomy_path' => optional($product->slug)->key ?? '',               
                'title' => $product->name,
                'description' => $description,
                'link' => route('product.show', $product->slug),              
                'availability' => $product->stock > 0 ? 'in stock' : 'out of stock',
                'condition' => $product->condition ?? 'new',
                'age_group' => $product->age_group ?? 'adult',
                'gender' => $product->gender ?? 'unisex',
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Products retrieved successfully',
            'data' => $formattedProducts,
            'pagination' => [
                'total' => $products->total(),
                'per_page' => $products->perPage(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'next_page_url' => $products->nextPageUrl(),
                'prev_page_url' => $products->previousPageUrl(),
            ],
        ]);
    }     
}
