<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Category;
use App\Models\Product;
use OpenApi\Annotations as OA;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

 class CategoryController extends Controller
{

    /**
     * @OA\Get(
     *     path="/api/frontend/home-categories",
     *     tags={"Frontend-Categories"},
     *     summary="Fetch a limited set of parent and child categories",
     *     description="Returns up to 14 categories including parent and child, with product count and image URL.",
     *     @OA\Response(
     *         response=200,
     *         description="Categories fetched successfully",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Electronics"),
     *                 @OA\Property(property="slug", type="string", example="electronics"),
     *                 @OA\Property(property="parent_id", type="integer", example=0),
     *                 @OA\Property(property="image", type="string", example="http://example.com/storage/categories/electronics.jpg"),
     *                 @OA\Property(property="productCount", type="integer", example=42)
     *             )
     *         )
     *     )
     * )
     */

    public function fetchCategories(Request $request)
    {
        // Limit to 14 categories
        $limit = 13;

        // Fetch parent categories
        $parentCategories = Category::where('parent_id', 0)
            ->get(['id', 'name', 'slug', 'parent_id', 'image']); // Select necessary fields

        // Fetch child categories
        $childCategories = Category::whereIn('parent_id', $parentCategories->pluck('id'))
            ->get(['id', 'name', 'slug', 'parent_id', 'image']); // Select necessary fields

        // Merge parent and child categories
        $allCategories = $parentCategories->merge($childCategories);

        // Limit the combined result to 14 categories
        $limitedCategories = $allCategories->take($limit);

        // Add product count and adjust image URLs
        foreach ($limitedCategories as $category) {
            $category->productCount = $category->products()->count(); // Count related products
            $category->image = $this->getImageUrl($category->image); // Adjust image URL
        }

        // Return categories with their details
        return response()->json($limitedCategories);
    }

    /**
     * @OA\Get(
     *     path="/api/frontend/all-categories",
     *     tags={"Frontend-Categories"},
     *     summary="Fetch all parent and child categories",
     *     description="Returns all parent and child categories with product count and image URL.",
     *     @OA\Response(
     *         response=200,
     *         description="All categories fetched successfully",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="name", type="string", example="Laptops"),
     *                 @OA\Property(property="slug", type="string", example="laptops"),
     *                 @OA\Property(property="parent_id", type="integer", example=1),
     *                 @OA\Property(property="image", type="string", example="http://example.com/storage/categories/laptops.jpg"),
     *                 @OA\Property(property="productCount", type="integer", example=15)
     *             )
     *         )
     *     )
     * )
     */

    public function fetchAllCategories(Request $request)
    {
        // Fetch parent categories
        $parentCategories = Category::where('parent_id', 0)
            ->get(['id', 'name', 'slug', 'parent_id', 'image']); // Select necessary fields

        // Fetch child categories
        $childCategories = Category::whereIn('parent_id', $parentCategories->pluck('id'))
            ->get(['id', 'name', 'slug', 'parent_id', 'image']); // Select necessary fields

        // Merge parent and child categories
        $allCategories = $parentCategories->merge($childCategories);

        // Add product count and adjust image URLs
        foreach ($allCategories as $category) {
            $category->productCount = $category->products()->count(); // Count related products
            $category->image = $this->getImageUrl($category->image); // Adjust image URL
        }

        // Return all categories with their details
        return response()->json($allCategories);
    }

    /**
     * @OA\Get(
     *     path="/api/frontend/categoryproducts",
     *     tags={"Frontend-Categories"},
     *     security={{"bearerAuth":{}}},
     *     summary="Get all featured products grouped by third-level categories",
     *     description="Returns featured products grouped under third-level categories. Includes wishlist status, best price, delivery date, reviews, stock, and images.",
     *     @OA\Response(
     *         response=200,
     *         description="Featured products grouped by category fetched successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="category_name", type="string", example="Smartphones"),
     *                     @OA\Property(
     *                         property="featured_products",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=101),
     *                             @OA\Property(property="name", type="string", example="iPhone 14"),
     *                             @OA\Property(property="sku", type="string", example="IP14-256GB"),
     *                             @OA\Property(property="price", type="number", format="float", example=999.99),
     *                             @OA\Property(property="sale_price", type="number", format="float", example=899.99),
     *                             @OA\Property(property="original_price", type="number", format="float", example=999.99),
     *                             @OA\Property(property="front_sale_price", type="number", format="float", example=899.99),
     *                             @OA\Property(property="best_price", type="number", format="float", example=899.99),
     *                             @OA\Property(property="best_delivery_date", type="integer", example=3),
     *                             @OA\Property(property="total_reviews", type="integer", example=120),
     *                             @OA\Property(property="avg_rating", type="number", format="float", example=4.5),
     *                             @OA\Property(property="left_stock", type="integer", example=20),
     *                             @OA\Property(property="currency", type="string", example="USD"),
     *                             @OA\Property(property="in_wishlist", type="boolean", example=true),
     *                             @OA\Property(
     *                                 property="images",
     *                                 type="array",
     *                                 @OA\Items(type="string", example="http://example.com/storage/products/image1.jpg")
     *                             )
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
    */

    public function getAllFeaturedProductsByCategory(Request $request)
    {
        $userId = Auth::id();
        $isUserLoggedIn = $userId !== null;

        // Fetch wishlist product IDs for logged-in users or guests
        $wishlistProductIds = $isUserLoggedIn
            ? DB::table('ec_wish_lists')
                ->where('customer_id', $userId)
                ->pluck('product_id')
                ->map(fn($id) => (int) $id)
                ->toArray()
            : session()->get('guest_wishlist', []);

        // Get only third-level child categories that have featured products
        $categories = Category::whereHas('products', function ($query) {
            $query->where('is_featured', 1)
                  ->where('status', 'published');
        }, '>=', 10)
        ->whereHas('parent.parent') // Ensures only third-level child categories
        ->with(['products' => function ($query) {
            $query->where('is_featured', 1)
                ->where('status', 'published')
                ->select('id', 'name', 'sku', 'price', 'currency_id', 'quantity', 'units_sold'); // Select only necessary fields
        }])
        ->take(5)
        ->get();

        // Subquery for best price and delivery days
        $subQuery = Product::select('sku')
            ->selectRaw('MIN(price) as best_price')
            ->selectRaw('MIN(delivery_days) as best_delivery_date')
            ->groupBy('sku');

        // Process categories and products
        $categories = $categories->map(function ($category) use ($subQuery, $wishlistProductIds) {
            $featuredProducts = $category->products->take(10);

            // Fetch all product details in one query
            $productDetails = Product::leftJoinSub($subQuery, 'best_products', function ($join) {
                    $join->on('ec_products.sku', '=', 'best_products.sku')
                        ->whereColumn('ec_products.price', 'best_products.best_price');
                })
                ->whereIn('ec_products.id', $featuredProducts->pluck('id'))
                ->with(['reviews', 'currency']) // Eager load relationships
                ->get()
                ->keyBy('id'); // Use keyBy to quickly fetch by ID later

            return [
                'category_name' => $category->name,
                'featured_products' => $featuredProducts->map(function ($product) use ($productDetails, $wishlistProductIds) {
                    $details = $productDetails[$product->id] ?? null;
                    if (!$details) return null; // Skip if no details found

                    $totalReviews = $details->reviews->count();
                    $avgRating = $totalReviews > 0 ? $details->reviews->avg('star') : null;
                    $leftStock = ($details->quantity ?? 0) - ($details->units_sold ?? 0);
                    $currencyTitle = $details->currency->title ?? $details->price;
                    $isInWishlist = in_array($details->id, $wishlistProductIds);

                    // Process images efficiently
                    $imageUrls = collect($details->images)->map(fn($image) => Str::startsWith($image, ['http://', 'https://']) ? $image : asset('storage/' . ltrim($image, '/')));

                    return [
                        'id' => $details->id,
                        'name' => $details->name,
                        'sku' => $details->sku,
                        'price' => $details->best_price ?? $details->price,
                        "sale_price" => $details->sale_price,
                        'best_delivery_date' => $details->best_delivery_date,
                        'total_reviews' => $totalReviews,
                        'avg_rating' => $avgRating,
                        'left_stock' => $leftStock,
                        'currency' => $currencyTitle,
                        'in_wishlist' => $isInWishlist,
                        'images' => $imageUrls,
                        "original_price"=> $details->price,
                        "front_sale_price"=> $details->price,
                        "best_price"=> $details->price,
                    ];
                })->filter()->values(), // Remove null values and reset array keys
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }


    /**
     * @OA\Get(
     *     path="/api/frontend/categoryguestproducts",
     *     tags={"Frontend-Categories"},
     *     summary="Get all featured products by category for guest users",
     *     description="Returns featured products grouped under third-level categories for guest users. Includes best price, delivery days, stock, reviews, and images.",
     *     @OA\Response(
     *         response=200,
     *         description="Featured products grouped by category fetched successfully for guests",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="category_name", type="string", example="Electronics"),
     *                     @OA\Property(
     *                         property="featured_products",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=202),
     *                             @OA\Property(property="name", type="string", example="Samsung Galaxy S22"),
     *                             @OA\Property(property="sku", type="string", example="SG-S22-128GB"),
     *                             @OA\Property(property="price", type="number", format="float", example=849.99),
     *                             @OA\Property(property="sale_price", type="number", format="float", example=799.99),
     *                             @OA\Property(property="original_price", type="number", format="float", example=849.99),
     *                             @OA\Property(property="front_sale_price", type="number", format="float", example=799.99),
     *                             @OA\Property(property="best_price", type="number", format="float", example=799.99),
     *                             @OA\Property(property="best_delivery_date", type="integer", example=5),
     *                             @OA\Property(property="total_reviews", type="integer", example=85),
     *                             @OA\Property(property="avg_rating", type="number", format="float", example=4.2),
     *                             @OA\Property(property="left_stock", type="integer", example=50),
     *                             @OA\Property(property="currency", type="string", example="USD"),
     *                             @OA\Property(
     *                                 property="images",
     *                                 type="array",
     *                                 @OA\Items(type="string", example="http://example.com/storage/products/samsung.jpg")
     *                             )
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */

    public function getAllGuestFeaturedProductsByCategory(Request $request)
    {
        $categories = Category::whereHas('products', function ($query) {
            $query->where('is_featured', 1)
                  ->where('status', 'published');
        }, '>=', 10)        
        ->whereHas('parent.parent') // Ensures only third-level child categories
        ->with(['products' => function ($query) {
            $query->where('is_featured', 1)
                ->where('status', 'published')
                ->select('id', 'name', 'sku', 'price', 'currency_id', 'quantity', 'units_sold'); // Select only necessary fields
        }])
        ->take(5)
        ->get();

        // Subquery for best price and delivery days
        $subQuery = Product::select('sku')
            ->selectRaw('MIN(price) as best_price')
            ->selectRaw('MIN(delivery_days) as best_delivery_date')
            ->groupBy('sku');

        // Process categories and products
        $categories = $categories->map(function ($category) use ($subQuery) {
            $featuredProducts = $category->products->take(10);

            // Fetch all product details in one query
            $productDetails = Product::leftJoinSub($subQuery, 'best_products', function ($join) {
                    $join->on('ec_products.sku', '=', 'best_products.sku')
                        ->whereColumn('ec_products.price', 'best_products.best_price');
                })
                ->whereIn('ec_products.id', $featuredProducts->pluck('id'))
                ->with(['reviews', 'currency']) // Eager load relationships
                ->get()
                ->keyBy('id'); // Use keyBy to quickly fetch by ID later

            return [
                'category_name' => $category->name,
                'featured_products' => $featuredProducts->map(function ($product) use ($productDetails) {
                    $details = $productDetails[$product->id] ?? null;
                    if (!$details) return null; // Skip if no details found

                    $totalReviews = $details->reviews->count();
                    $avgRating = $totalReviews > 0 ? $details->reviews->avg('star') : null;
                    $leftStock = ($details->quantity ?? 0) - ($details->units_sold ?? 0);
                    $currencyTitle = $details->currency->symbol ?? $details->price;

                    // Process images efficiently
                    $imageUrls = $details->images;

                    return [
                        'id' => $details->id,
                        'name' => $details->name,
                        'sku' => $details->sku,
                        'price' => $details->best_price ?? $details->price,
                        "sale_price" => $details->sale_price,
                        'best_delivery_date' => $details->best_delivery_date,
                        'total_reviews' => $totalReviews,
                        'avg_rating' => $avgRating,
                        'left_stock' => $leftStock,
                        'currency' => $currencyTitle,
                        'images' => $imageUrls,
                        "original_price"=> $details->price,
                        "front_sale_price"=> $details->price,
                        "best_price"=> $details->price,
                    ];
                })->filter()->values(), // Remove null values and reset array keys
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    private function getImageUrl($imagePath)
    {
        // Check if the image is inside 'categories' or general 'storage'
        if (strpos($imagePath, 'storage/categories') === 0) {
            return asset('storage/' . $imagePath); // If inside storage/categories, use the asset helper
        } elseif (strpos($imagePath, 'storage') === 0) {
            return asset('storage/' . $imagePath); // If inside any storage folder, use the asset helper
        }

        // Return default if not found
        return asset('storage/' . $imagePath); 
    }

  

}
