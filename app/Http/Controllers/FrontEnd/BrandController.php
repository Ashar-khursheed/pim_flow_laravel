<?php
namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Models\Brand;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use OpenApi\Annotations as OA;

class BrandController extends Controller
{
 
    private function getWishlistProductIds()
    {
        $userId = Auth::id();

        if ($userId) {
            return Cache::remember("wishlist_user_{$userId}", 60, function () use ($userId) {
                return DB::table('ec_wish_lists')
                    ->where('customer_id', $userId)
                    ->pluck('product_id')
                    ->toArray();
            });
        }

        return session()->get('guest_wishlist', []);
    }

    /**
     * @OA\Get(
     *     path="/api/frontend/homebrandproducts",
     *     tags={"Frontend-Brands"},
     *     summary="Get all home brand products for authenticated users",
     *     description="Retrieves the latest 5 brands with at least 10 products each, limited to 10 products per brand. Includes wishlist status for authenticated users.",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search products by name",
     *         required=false,
     *         @OA\Schema(type="string", example="iPhone")
     *     ),
     *     @OA\Parameter(
     *         name="price_min",
     *         in="query",
     *         description="Minimum price filter",
     *         required=false,
     *         @OA\Schema(type="number", format="float", example=100.00)
     *     ),
     *     @OA\Parameter(
     *         name="price_max",
     *         in="query",
     *         description="Maximum price filter",
     *         required=false,
     *         @OA\Schema(type="number", format="float", example=1000.00)
     *     ),
     *     @OA\Parameter(
     *         name="rating",
     *         in="query",
     *         description="Minimum rating filter",
     *         required=false,
     *         @OA\Schema(type="number", format="float", minimum=1, maximum=5, example=4.0)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="brand_name", type="string", example="Apple"),
     *                     @OA\Property(
     *                         property="products",
     *                         type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="iPhone 14"),
     *                             @OA\Property(property="sku", type="string", example="IPH14-001"),
     *                             @OA\Property(property="price", type="number", format="float", example=999.99),
     *                             @OA\Property(property="sale_price", type="number", format="float", example=899.99),
     *                             @OA\Property(property="best_delivery_date", type="string", example="2024-01-15"),
     *                             @OA\Property(property="total_reviews", type="integer", example=150),
     *                             @OA\Property(property="avg_rating", type="number", format="float", example=4.5),
     *                             @OA\Property(property="left_stock", type="integer", example=25),
     *                             @OA\Property(property="currency", type="string", example="USD"),
     *                             @OA\Property(property="in_wishlist", type="boolean", example=false),
     *                             @OA\Property(
     *                                 property="images",
     *                                 type="array",
     *                                 @OA\Items(type="string", example="https://example.com/storage/products/iphone14.jpg")
     *                             ),
     *                             @OA\Property(property="original_price", type="number", format="float", example=999.99),
     *                             @OA\Property(property="front_sale_price", type="number", format="float", example=999.99),
     *                             @OA\Property(property="best_price", type="number", format="float", example=999.99)
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */   
  
    public function getAllHomeBrandProducts(Request $request)
    {
        $wishlistIds = $this->getWishlistProductIds();

        // Fetch only the latest 5 brands with at least 10 products
        $brands = Brand::with(['products' => function ($query) {
            $query->where('status', 'published');
        }])
        ->whereHas('products', function ($query) {
            $query->where('status', 'published') // 👈 Add this line
                ->select('brand_id')
                ->groupBy('brand_id')
                ->havingRaw('COUNT(*) >= 10');
        })
        ->orderBy('created_at', 'desc')
        ->take(5)
        ->get();

        return response()->json([
            'success' => true,
            'data' => $brands->map(function ($brand) use ($request, $wishlistIds) {
                // Filter and limit products to 10 for each brand
                $products = $brand->products()
                    ->when($request->has('search'), function ($query) use ($request) {
                        $query->where('name', 'like', '%' . $request->input('search') . '%');
                    })
                    ->when($request->has('price_min'), function ($query) use ($request) {
                        $query->where('price', '>=', $request->input('price_min'));
                    })
                    ->when($request->has('price_max'), function ($query) use ($request) {
                        $query->where('price', '<=', $request->input('price_max'));
                    })
                    ->when($request->has('rating'), function ($query) use ($request) {
                        $query->whereHas('reviews', function ($q) use ($request) {
                            $q->selectRaw('AVG(star) as avg_rating')
                                ->groupBy('product_id')
                                ->havingRaw('AVG(star) >= ?', [$request->input('rating')]);
                        });
                    })
                    ->orderBy('created_at', 'desc') // Order products by latest
                    ->take(10) // Limit to 10 products per brand
                    ->get();

                // Map brand data
                return [
                    'brand_name' => $brand->name,
                    'products' => $products->map(function ($product) use ($wishlistIds) {
                        $totalReviews = $product->reviews->count();
                        $avgRating = $totalReviews > 0 ? $product->reviews->avg('star') : null;
                        $leftStock = ($details->quantity ?? 0) - ($product->units_sold ?? 0);
                        $currencyTitle = $details->currency->title ?? $product->price;

                           // Assuming $details->images is already decoded once and looks like:
                           $rawImageData = $product->images;

                           // Step 1: Make sure it's an array
                           $imageArray = is_array($rawImageData) ? $rawImageData : json_decode($rawImageData, true);
   
                           // Step 2: Decode the nested JSON strings (if any)
                           $cleanedImages = collect($imageArray)->map(function ($item) {
                               // Check if it's a string and a valid JSON array
                               if (is_string($item) && str_starts_with($item, '[')) {
                                   $decoded = json_decode($item, true);
                                   return is_array($decoded) ? $decoded : [$item];
                               }
                               return [$item]; // already a normal value
                           })->flatten()->filter()->values(); // remove nulls and reindex
   
                           // Output
                           $imageUrls = $cleanedImages;

                        return [
                            "id" => $product->id,
                            // "name" => $product->name,
                            // "images" => array_map(function ($image) use ($getImageUrl) {
                            //     return $getImageUrl($image);
                            // }, $productImages),
                            // "sku" => $product->sku ?? '',
                            // "price" => $product->price,
                            // "sale_price" => $product->sale_price ?? null,
                            // "rating" => $product->reviews()->avg('star') ?? null,
                            // "in_wishlist" => in_array($product->id, $wishlistIds),
                            'name' => $product->name,
                            'sku' => $product->sku,
                            'price' => $product->price,
                            'sale_price' => $product->sale_price,
                            'best_delivery_date' => $product->best_delivery_date,
                            'total_reviews' => $product->reviews->count(),
                            'avg_rating' => $product->reviews->count() > 0 ? $product->reviews->avg('star') : null,
                            'left_stock' => $product->left_stock ?? 0,
                            'currency' => $product->currency->symbol ?? 'USD',
                            'in_wishlist' => $product->in_wishlist ?? false,
                            'images' => $imageUrls,
                            'original_price' => $product->price,
                            'front_sale_price' => $product->price,
                            'best_price' => $product->price,
                        ];
                    }),
                ];
            }),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/frontend/brandguestproducts",
     *     tags={"Frontend-Brands"},
     *     summary="Get all brand products for guest users",
     *     description="Retrieves the latest 5 brands with at least 10 products each, optimized for guest users without wishlist functionality.",
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search products by name",
     *         required=false,
     *         @OA\Schema(type="string", example="Samsung")
     *     ),
     *     @OA\Parameter(
     *         name="price_min",
     *         in="query",
     *         description="Minimum price filter",
     *         required=false,
     *         @OA\Schema(type="number", format="float", example=50.00)
     *     ),
     *     @OA\Parameter(
     *         name="price_max",
     *         in="query",
     *         description="Maximum price filter",
     *         required=false,
     *         @OA\Schema(type="number", format="float", example=2000.00)
     *     ),
     *     @OA\Parameter(
     *         name="rating",
     *         in="query",
     *         description="Minimum rating filter",
     *         required=false,
     *         @OA\Schema(type="number", format="float", minimum=1, maximum=5, example=3.5)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="brand_name", type="string", example="Samsung"),
     *                     @OA\Property(
     *                         property="products",
     *                         type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="id", type="integer", example=2),
     *                             @OA\Property(property="name", type="string", example="Galaxy S23"),
     *                             @OA\Property(property="sku", type="string", example="GAL-S23-001"),
     *                             @OA\Property(property="price", type="number", format="float", example=799.99),
     *                             @OA\Property(property="original_price", type="number", format="float", example=899.99),
     *                             @OA\Property(property="sale_price", type="number", format="float", example=799.99),
     *                             @OA\Property(property="best_delivery_date", type="string", example="2024-01-10"),
     *                             @OA\Property(property="total_reviews", type="integer", example=89),
     *                             @OA\Property(property="avg_rating", type="number", format="float", example=4.2),
     *                             @OA\Property(property="left_stock", type="integer", example=15),
     *                             @OA\Property(property="currency", type="string", example="USD"),
     *                             @OA\Property(
     *                                 property="images",
     *                                 type="array",
     *                                 @OA\Items(type="string", example="https://example.com/storage/galaxy-s23.jpg")
     *                             ),
     *                             @OA\Property(property="front_sale_price", type="number", format="float", example=799.99),
     *                             @OA\Property(property="best_price", type="number", format="float", example=799.99)
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getAllBrandGuestProducts(Request $request)
    {
        // Subquery for best price and delivery days by SKU
        $subQuery = Product::select('sku')
            ->selectRaw('MIN(price) as best_price')
            ->selectRaw('MIN(delivery_days) as best_delivery_date')
            ->groupBy('sku');

        // Fetch only the latest 5 brands with at least 10 products
        $brands = Brand::with(['products' => function ($query) {
            $query->where('status', 'published');
        }])
        ->whereHas('products', function ($query) {
            $query->where('status', 'published') // 👈 Add this line
                ->select('brand_id')
                ->groupBy('brand_id')
                ->havingRaw('COUNT(*) >= 10');
        })
        ->orderBy('created_at', 'desc')
        ->take(5)
        ->get();

        return response()->json([
            'success' => true,
            'data' => $brands->map(function ($brand) use ($request, $subQuery) {
                // Filter and limit products to 10 for each brand
                $products = $brand->products()
                    ->when($request->has('search'), function ($query) use ($request) {
                        $query->where('name', 'like', '%' . $request->input('search') . '%');
                    })
                    ->when($request->has('price_min'), function ($query) use ($request) {
                        $query->where('price', '>=', $request->input('price_min'));
                    })
                    ->when($request->has('price_max'), function ($query) use ($request) {
                        $query->where('price', '<=', $request->input('price_max'));
                    })
                    ->when($request->has('rating'), function ($query) use ($request) {
                        $query->whereHas('reviews', function ($q) use ($request) {
                            $q->selectRaw('AVG(star) as avg_rating')
                                ->groupBy('product_id')
                                ->havingRaw('AVG(star) >= ?', [$request->input('rating')]);
                        });
                    })
                    ->take(10)
                    ->pluck('id'); // Only get product IDs

                // Fetch product details with joined best_price and eager load
                $productDetails = Product::leftJoinSub($subQuery, 'best_products', function ($join) {
                        $join->on('ec_products.sku', '=', 'best_products.sku')
                            ->whereColumn('ec_products.price', 'best_products.best_price');
                    })
                    ->whereIn('ec_products.id', $products)
                    ->with(['reviews', 'currency'])
                    ->get()
                    ->keyBy('id');

                return [
                    'brand_name' => $brand->name,
                    'products' => $productDetails->map(function ($details) {
                        $totalReviews = $details->reviews->count();
                        $avgRating = $totalReviews > 0 ? $details->reviews->avg('star') : null;
                        $leftStock = ($details->quantity ?? 0) - ($details->units_sold ?? 0);
                        $currencyTitle = $details->currency->symbol ?? $details->price;

                           // Assuming $details->images is already decoded once and looks like:
                           $rawImageData = $details->images;

                           // Step 1: Make sure it's an array
                           $imageArray = is_array($rawImageData) ? $rawImageData : json_decode($rawImageData, true);
   
                           // Step 2: Decode the nested JSON strings (if any)
                           $cleanedImages = collect($imageArray)->map(function ($item) {
                               // Check if it's a string and a valid JSON array
                               if (is_string($item) && str_starts_with($item, '[')) {
                                   $decoded = json_decode($item, true);
                                   return is_array($decoded) ? $decoded : [$item];
                               }
                               return [$item]; // already a normal value
                           })->flatten()->filter()->values(); // remove nulls and reindex
   
                           // Output
                           $imageUrls = $cleanedImages;

                        return [
                            'id' => $details->id,
                            'name' => $details->name,
                            'sku' => $details->sku,
                            'price' => $details->best_price ?? $details->price,
                            'original_price' => $details->price,
                            'sale_price' => $details->sale_price,
                            'best_delivery_date' => $details->best_delivery_date,
                            'total_reviews' => $totalReviews,
                            'avg_rating' => $avgRating,
                            'left_stock' => $leftStock,
                            'currency' => $currencyTitle,
                            'images' => $imageUrls,
                            'front_sale_price' => $details->price,
                            'best_price' => $details->best_price ?? $details->price,
                        ];
                    })->values(),
                ];
            }),
        ]);
    }


    /**
     * @OA\Get(
     *     path="/api/frontend/brands-by-category/{id}",
     *     tags={"Frontend-Brands"},
     *     summary="Get brands by category",
     *     description="Retrieves all published brands that have products in the specified category.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Category ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Brands retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Apple"),
     *                     @OA\Property(property="logo", type="string", example="https://example.com/storage/brands/apple-logo.png")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No brands found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No brands found for this category."),
     *             @OA\Property(property="data", type="array", @OA\Items())
     *         )
     *     )
     * )
     */
    public function brandsByCategory($id): JsonResponse
    {
        $brandIds = Product::whereHas('categories', function ($query) use ($id) {
            $query->where('ec_product_category_product.category_id', $id);
        })->pluck('brand_id')->unique()->filter();

        if ($brandIds->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No brands found for this category.',
                'data' => []
            ], 404);
        }

        $brands = Brand::whereIn('id', $brandIds)
            ->where('status', 'published')
            ->select('id', 'name', 'logo')
            ->get()
            ->map(function ($brand) {
                $brand->logo = $brand->logo ? asset( $brand->logo) : null;
                return $brand;
            });

        return response()->json([
            'success' => true,
            'message' => 'Brands retrieved successfully.',
            'data' => $brands
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/frontend/brand/{id}/categories",
     *     tags={"Frontend-Brands"},
     *     summary="Get categories by brand",
     *     description="Retrieves all unique categories associated with products of the specified brand.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Brand ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="sucess", type="string", example="true"),
     *             @OA\Property(property="brand_id", type="integer", example=1),
     *             @OA\Property(
     *                 property="categories",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Smartphones"),
     *                     @OA\Property(property="image", type="string", example="https://example.com/storage/categories/smartphones.jpg")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Brand not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Brand not found.")
     *         )
     *     )
     * )
     */
    public function getCategories($id)
    {
        $brand = Brand::with(['products.categories'])->findOrFail($id);
    
        // Count the number of products per category for this brand
        $categoryCounts = [];
    
        foreach ($brand->products as $product) {
            foreach ($product->categories as $category) {
                if (!isset($categoryCounts[$category->id])) {
                    $categoryCounts[$category->id] = [
                        'id' => $category->id,
                        'name' => $category->name,
                        'image' => $category->image,
                        'product_count' => 0
                    ];
                }
                $categoryCounts[$category->id]['product_count']++;
            }
        }
    
        // Reindex array and return as values
        $categories = array_values($categoryCounts);
    
        return response()->json([
            'success' => true,
            'brand_id' => $id,
            'categories' => $categories
        ]);
    }


    
    
    
  
     /**
     * @OA\Get(
     *     path="/api/frontend/products/brand/{brandId}/category/{categoryId?}",
     *     tags={"Frontend-Brands"},
     *     summary="Get products by brand and optional category",
     *     description="Retrieves published products for a specific brand, optionally filtered by category with search functionality and pagination.",
     *     @OA\Parameter(
     *         name="brandId",
     *         in="path",
     *         description="Brand ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="categoryId",
     *         in="path",
     *         description="Category ID (optional)",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by product name or SKU",
     *         required=false,
     *         @OA\Schema(type="string", example="iPhone")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Products retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="iPhone 14 Pro"),
     *                     @OA\Property(
     *                         property="images",
     *                         type="array",
     *                         @OA\Items(type="string", example="https://example.com/storage/products/iphone14pro.jpg")
     *                     ),
     *                     @OA\Property(property="video_url", type="string", example="https://youtube.com/watch?v=xyz"),
     *                     @OA\Property(
     *                         property="video_path",
     *                         type="array",
     *                         @OA\Items(type="string", example="https://example.com/storage/videos/iphone14pro.mp4")
     *                     ),
     *                     @OA\Property(property="sku", type="string", example="IPH14PRO-001"),
     *                     @OA\Property(property="original_price", type="number", format="float", example=1099.99),
     *                     @OA\Property(property="front_sale_price", type="number", format="float", example=1099.99),
     *                     @OA\Property(property="sale_price", type="number", format="float", example=999.99),
     *                     @OA\Property(property="price", type="number", format="float", example=1099.99),
     *                     @OA\Property(property="start_date", type="string", format="date", example="2024-01-01"),
     *                     @OA\Property(property="end_date", type="string", format="date", example="2024-12-31"),
     *                     @OA\Property(property="warranty_information", type="string", example="1 year limited warranty"),
     *                     @OA\Property(property="currency", type="string", example="USD"),
     *                     @OA\Property(property="total_reviews", type="integer", example=245),
     *                     @OA\Property(property="avg_rating", type="number", format="float", example=4.7),
     *                     @OA\Property(property="best_price", type="number", format="float", example=999.99),
     *                     @OA\Property(property="best_delivery_date", type="string", nullable=true, example=null),
     *                     @OA\Property(property="leftStock", type="integer", example=42),
     *                     @OA\Property(property="currency_title", type="string", example="$1099.99")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="pagination",
     *                 @OA\Property(property="total", type="integer", example=150),
     *                 @OA\Property(property="per_page", type="integer", example=50),
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=3)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Brand not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Brand not found.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An error occurred while fetching products"),
     *             @OA\Property(property="error", type="string", example="Database connection failed")
     *         )
     *     )
     * )
     */
    
     public function getProductsByBrandAndCategory(Request $request, $brandId, $categoryId = null)
     {
         try {
             $searchTerm = strtolower($request->input('search'));
     
             $brand = Brand::with(['products.categories'])->findOrFail($brandId);
     
             // Filter by category
             $filteredProducts = is_null($categoryId)
                 ? $brand->products
                 : $brand->products->filter(function ($product) use ($categoryId) {
                     return $product->categories->contains('id', $categoryId);
                 })->values();
     
             // Filter by search term if provided
             if (!empty($searchTerm)) {
                 $filteredProducts = $filteredProducts->filter(function ($product) use ($searchTerm) {
                     return stripos($product->name, $searchTerm) !== false;
                 })->values();
             }
     
             if ($filteredProducts->isEmpty()) {
                 return response()->json([
                     'success' => true,
                     'message' => 'No products found for this brand' . ($categoryId ? ' and category' : '') . ($searchTerm ? ' with search term' : ''),
                     'data' => [],
                     'pagination' => $this->emptyPagination(),
                 ]);
             }
     
             $productIds = $filteredProducts->pluck('id')->toArray();
     
             $productsWithRelations = Product::whereIn('id', $productIds)
                 ->with([
                     'reviews:id,product_id,star',
                     'currency',
                     'specifications',
                 ])
                 ->get()
                 ->keyBy('id');
     
             $perPage = 50;
             $page = max(1, (int) $request->input('page', 1));
             $total = count($productIds);
             $offset = ($page - 1) * $perPage;
             $paginatedProducts = $filteredProducts->slice($offset, $perPage);
     
             $pagination = $this->buildPagination($page, $perPage, $total);
     
             $transformedProducts = $paginatedProducts->map(function ($product) use ($productsWithRelations) {
                 $productWithRelations = $productsWithRelations->get($product->id) ?? $product;
     
                 $images = $this->$product->images;
                 $videos = $this->$product->video_path;
     
                 $totalReviews = $productWithRelations->reviews ? $productWithRelations->reviews->count() : 0;
                 $avgRating = $totalReviews > 0 ? $productWithRelations->reviews->avg('star') : null;
     
                 $quantity = $product->quantity ?? 0;
                 $unitsSold = $product->units_sold ?? 0;
                 $leftStock = $quantity - $unitsSold;
     
                 return [
                     'id' => $product->id,
                     'name' => $product->name,
                     'images' => $images,
                     'video_url' => $product->video_url,
                     'video_path' => $videos,
                     'sku' => $product->sku,
                     'original_price' => $product->price,
                     'front_sale_price' => $product->price,
                     'sale_price' => $product->sale_price,
                     'price' => $product->price,
                     'start_date' => $product->start_date,
                     'end_date' => $product->end_date,
                     'warranty_information' => $product->warranty_information,
                     'currency' => $productWithRelations->currency?->title,
                     'total_reviews' => $totalReviews,
                     'avg_rating' => $avgRating,
                     'best_price' => $product->sale_price ?? $product->price,
                     'best_delivery_date' => null,
                     'leftStock' => $leftStock,
                     'currency_title' => $productWithRelations->currency
                         ? ($productWithRelations->currency->is_prefix_symbol
                             ? $productWithRelations->currency->title
                             : ($product->price . ' ' . $productWithRelations->currency->title))
                         : $product->price,
                 ];
             });
     
             return response()->json([
                 'success' => true,
                 'data' => $transformedProducts->values(),
                 'pagination' => $pagination,
                 'message' => 'Products retrieved successfully',
             ]);
         } catch (\Exception $e) {
             Log::error('Error in getProductsByBrandAndCategory: ' . $e->getMessage());
             return response()->json([
                 'success' => false,
                 'message' => 'An error occurred while fetching products',
                 'error' => $e->getMessage(),
             ], 500);
         }
     }
     
   
     protected function emptyPagination()
    {
            return [
                'total' => 0,
                'per_page' => 0,
                'current_page' => 1,
                'last_page' => 1,
            ];
    }

    
    protected function buildPagination($page, $perPage, $total)
        {
            return [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => ceil($total / $perPage),
            ];
        }

    protected function normalizeMediaUrls($media)
    {
        if (is_array($media)) {
            return array_map(fn ($url) => url($url), $media);
        }
        return $media ? url($media) : null;
    }

    
    /**
     * @OA\Get(
     *     path="/api/frontend/brands/alphabetical",
     *     tags={"Frontend-Brands"},
     *     summary="Get all brands alphabetically",
     *     description="Retrieves all published brands either grouped alphabetically or filtered by starting letter.",
     *     @OA\Parameter(
     *         name="letter",
     *         in="query",
     *         description="Filter brands by starting letter (A-Z)",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             pattern="^[A-Z]$",
     *             example="A"
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Successful operation - Filtered by letter",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Brands starting with letter 'A'."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Apple"),
     *                     @OA\Property(property="logo", type="string", example="https://example.com/storage/brands/apple-logo.png")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    
     public function getAllBrandsAlphabetically(Request $request): JsonResponse
     {
         $letter = strtoupper($request->query('letter')); // e.g. ?letter=B
 
         $brandsQuery = Brand::where('status', 'published')
             ->whereNotNull('thumbnail') // Only include brands with a thumbnail
             ->select('id', 'name', 'logo', 'thumbnail', 'ar_thumbnail')
             ->orderBy('name');
 
         if ($letter) {
             $brandsQuery->where('name', 'LIKE', $letter . '%');
         }
 
         $brands = $brandsQuery->get()->map(function ($brand) {
             $brand->logo = $brand->logo ? asset($brand->logo) : null;
             $brand->thumbnail = $brand->thumbnail ? asset($brand->thumbnail) : null;
             $brand->ar_thumbnail = $brand->ar_thumbnail ? asset($brand->ar_thumbnail) : null;
             return $brand;
         });
 
         if ($letter) {
             return response()->json([
                 'success' => true,
                 'message' => "Brands starting with letter '$letter'.",
                 'data' => $brands
             ]);
         } else {
             $grouped = $brands->groupBy(function ($brand) {
                 return strtoupper(substr($brand->name, 0, 1));
             })->sortKeys();
 
             return response()->json([
                 'success' => true,
                 'message' => 'Brands grouped alphabetically.',
                 'data' => $grouped
             ]);
         }
     }
 


    
}
