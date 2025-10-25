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
 *     @OA\Parameter(
 *         name="per_page",
 *         in="query",
 *         description="Number of items per page (default: 50)",
 *         required=false,
 *         @OA\Schema(type="integer", example=50)
 *     ),
 *     @OA\Parameter(
 *         name="page",
 *         in="query",
 *         description="Page number (default: 1)",
 *         required=false,
 *         @OA\Schema(type="integer", example=1)
 *     ),
 *     @OA\Parameter(
 *         name="sort_by",
 *         in="query",
 *         description="Sort by column (default: id)",
 *         required=false,
 *         @OA\Schema(type="string",enum={"id"})
 *     ),
 *     @OA\Parameter(
 *         name="sort_direction",
 *         in="query",
 *         description="Sort direction (asc/desc, default: desc)",
 *         required=false,
 *         @OA\Schema(type="string",enum={"asc", "desc"})
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Successful operation",
 *         @OA\MediaType(
 *             mediaType="application/json"
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
    // Cache the feed for 1 hour with pagination parameters
    $cacheKey = 'datafeedwatch_feed_' . md5($request->fullUrl());

    $data = Cache::remember($cacheKey, 3600, function () use ($request) {
        return $this->generateProductFeed($request);
    });
    
    return $data;
}

private function generateProductFeed(Request $request)
{
    $perPage = $request->input('per_page', 50);
    $page = $request->input('page', 1);
    $sortBy = $request->input('sort_by', 'id');
    $sortDirection = $request->input('sort_direction', 'desc');

    // Validate sort columns to prevent SQL injection
    $allowedSortColumns = ['id', 'name', 'sku', 'brand_id', 'status', 'gen_type', 'approved'];
    if (!in_array($sortBy, $allowedSortColumns)) {
        $sortBy = 'id';
    }

    // Validate sort direction
    if (!in_array(strtolower($sortDirection), ['asc', 'desc'])) {
        $sortDirection = 'desc';
    }

    $query = Product::with([
        'brand:id,name',
        'categories:id,name',
        'slug:id,key,reference_id',
        'productSuppliers.vendor:id,name',
        'vendors:id,name'
    ])
    ->select(['id', 'name', 'sku', 'images', 'brand_id', 'status', 'gen_type', 'approved', 'description', 'quote_available', 'stock_status', 'condition', 'age_group', 'gender'])
    ->where('status', 'published')
    ->orderBy($sortBy, $sortDirection);

    $products = $query->paginate($perPage, ['*'], 'page', $page);

    /* Formatting response */
    $formattedProducts = $products->map(function ($product) {
        $firstSupplier = $product->productSuppliers->first();
        
        $price = $firstSupplier->price ?? 0;
        $salePrice = $firstSupplier->sale_price ?? 0;
        $margin = $salePrice - $price;
        $marginPercent = $salePrice > 0 ? ($margin / $salePrice) * 100 : 0;

        $description = [];
        if (is_string($product->description)) {
            $decoded = json_decode($product->description, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $description = array_values(array_filter(array_map(function ($item) {
                    if (is_null($item) || strtolower($item) === 'null') {
                        return null;
                    }

                    $item = str_replace(['&nbsp;', "\xc2\xa0"], ' ', $item);
                    $item = preg_replace('/\s+/', ' ', $item);
                    $item = trim($item);

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
            'price' => $price,
            'sale_price' => $salePrice,
            'vendor_id' => $firstSupplier->vendor_id ?? null,
            'vendor_name' => $product->vendors->pluck('name')->first(),
            'margin' => $margin,
            'margin_percent' => round($marginPercent, 2),
            'product_family' => $product->categories->pluck('name')->toArray(),
            'taxonomy_path' => optional($product->slug)->key ?? '',
            'title' => $product->name,
            'description' => $description,
            'link' => route('product.show', $product->slug),
            'availability' => $product->stock_status,
            
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
            'from' => $products->firstItem(),
            'to' => $products->lastItem(),
            'next_page_url' => $products->nextPageUrl(),
            'prev_page_url' => $products->previousPageUrl(),
        ],
    ]);
}
}
