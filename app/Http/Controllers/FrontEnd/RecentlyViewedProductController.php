<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\FrontEnd\RecentlyViewedProduct;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;
use App\Models\FrontEnd\GuestRecentlyViewedProduct;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cookie;

class RecentlyViewedProductController extends Controller
{

    /**
     * @OA\Post(
     *     path="/api/frontend/recent-products/add",
     *     tags={"Frontend-Recently Viewed Products"},
     *     summary="Add product to recently viewed list",
     *     description="Adds a product to the authenticated user's recently viewed list.",
     *     operationId="addToRecent",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"product_id"},
     *             @OA\Property(property="product_id", type="integer", example=123, description="ID of the product to be added")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product added to recently viewed list",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Product added to recently viewed list.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="User not authenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="User not authenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */

    public function addToRecent(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:ec_products,id', // Validates if the product exists
        ]);

        $productId = $request->input('product_id');
        $userId = Auth::id(); // Use Auth if the user is logged in

        if ($userId) {
            // Add the product to the recently viewed list
            RecentlyViewedProduct::updateOrCreate(
                ['customer_id' => $userId, 'product_id' => $productId],
                ['updated_at' => now()] // Updates the timestamp if already exists
            );

            return response()->json(['message' => 'Product added to recently viewed list.'], 200);
        }

        return response()->json(['message' => 'User not authenticated.'], 401);
    }



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
     *     path="/api/frontend/recent-products",
     *     tags={"Frontend-Recently Viewed Products"},
     *     summary="Get recently viewed products",
     *     description="Returns the last 5 recently viewed products for the authenticated user.",
     *     operationId="getRecentProducts",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Recently viewed products retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="product_id", type="integer", example=123),
     *                     @OA\Property(property="name", type="string", example="Sample Product"),
     *                     @OA\Property(property="sku", type="string", example="SP123"),
     *                     @OA\Property(property="price", type="number", format="float", example=99.99),
     *                     @OA\Property(property="sale_price", type="number", format="float", example=89.99),
     *                     @OA\Property(property="best_delivery_date", type="string", example="2025-06-10"),
     *                     @OA\Property(property="total_reviews", type="integer", example=5),
     *                     @OA\Property(property="avg_rating", type="number", format="float", example=4.5),
     *                     @OA\Property(property="left_stock", type="integer", example=10),
     *                     @OA\Property(property="currency", type="string", example="USD"),
     *                     @OA\Property(property="in_wishlist", type="boolean", example=true),
     *                     @OA\Property(property="images", type="array", @OA\Items(type="string", example="https://example.com/image.jpg")),
     *                     @OA\Property(property="original_price", type="number", format="float", example=99.99),
     *                     @OA\Property(property="front_sale_price", type="number", format="float", example=89.99),
     *                     @OA\Property(property="best_price", type="number", format="float", example=89.99)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No recently viewed products found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No recently viewed products found.")
     *         )
     *     )
     * )
     */

    public function getRecentProducts()
    {
        $userId = Auth::id(); // Get authenticated user

        if ($userId) {
            // Fetch recently viewed products for the logged-in user, eager load the related product data
            $recentlyViewed = RecentlyViewedProduct::with('product') // Ensure 'product' relationship is loaded
                ->where('customer_id', $userId)
                ->latest()  // Order by most recently viewed
                ->take(5)   // Limit to the last 5 viewed products
                ->get();

            // Get wishlist product IDs
            $wishlistIds = $this->getWishlistProductIds();

            // Check if we have any recently viewed products
            if ($recentlyViewed->isEmpty()) {
                return response()->json(['message' => 'No recently viewed products found.'], 404);
            }


            return response()->json([
                'success' => true,
                'data' => $recentlyViewed->map(function ($viewed) use ($wishlistIds) {
                    $product = $viewed->product;

            
                    // Check if the product is null
                    if (!$product) {
                        return null; // Or handle it as needed (e.g., skip this entry, log it, etc.)
                    }
                    $imageArray = is_array($product->images) ? $product->images : json_decode($product->images, true);
                    $cleanedImages = collect($imageArray)->map(function ($item) {
                        if (is_string($item) && str_starts_with($item, '[')) {
                            $decoded = json_decode($item, true);
                            return is_array($decoded) ? $decoded : [$item];
                        }
                        return [$item];
                    })->flatten()->filter()->values();

                    $sellingType = null;
                    if ($product->sellingUnitAttribute && $product->sellingUnitAttribute->attribute_value) {
                        $fullValue = $product->sellingUnitAttribute->attribute_value;

                        $attributeUnit = strpos($fullValue, '/') !== false
                            ? trim(explode('/', $fullValue)[1])
                            : $fullValue;

                        $sellingType = [
                            'attribute_value' => $product->sellingUnitAttribute->attribute_value,
                            'attribute_value_unit' => $attributeUnit,
                        ];
                    }

            
                    return [
                        'product_id' => $product->id,
                        'name' => $product->name,
                        'sku' => $product->sku,
                        'price' => $product->price,
                        'sale_price' => $product->sale_price,
                        'best_delivery_date' => $product->best_delivery_date,
                        'total_reviews' => $product->reviews->count(),
                        'avg_rating' => $product->reviews->count() > 0 ? $product->reviews->avg('star') : null,
                        'left_stock' => $product->left_stock ?? 0,
                        'currency' => $product->currency->symbol ?? '$',
                        'in_wishlist' => in_array($product->id, $wishlistIds),
                        'images' =>$cleanedImages,
                        'original_price' => $product->price,
                        'front_sale_price' => $product->price,
                        'best_price' => $product->price,
                        "selling_type"=> $sellingType,
                    ];
                })->filter(), // Filter out null values
            ]);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/frontend/guest/view-product",
     *     tags={"Guest"},
     *     summary="Save guest product view",
     *     description="Stores a product view by a guest user using a cookie-based guest_token.",
     *     operationId="saveGuestProductView",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"product_id"},
     *             @OA\Property(property="product_id", type="integer", example=123)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product view saved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid product ID or no guest_token",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Invalid product ID.")
     *         )
     *     )
     * )
     */
    // public function saveGuestProductView(Request $request)
    // {
    //     $productId = $request->input('product_id');
    //     $guestToken = $request->input('guest_token');
    
    //     if (!$productId || !Product::find($productId)) {
    //         return response()->json(['message' => 'Invalid product ID.'], 400);
    //     }
    
    //     // If token not provided, create one
    //     if (!$guestToken) {
    //         $guestToken = Str::uuid()->toString();
    //     }
    
    //     // Save the guest product view
    //     GuestRecentlyViewedProduct::create([
    //         'guest_token' => $guestToken,
    //         'product_id' => $productId,
    //     ]);
    
    //     return response()->json([
    //         'success' => true,
    //         'guest_token' => $guestToken, // send back token to store on frontend
    //     ]);
    // }
    public function saveGuestProductView(Request $request)
    {
        $productId = $request->input('product_id');
        $guestToken = $request->input('guest_token');

        if (!$productId || !Product::find($productId)) {
            return response()->json(['message' => 'Invalid product ID.'], 400);
        }

        // If token not provided, create one
        if (!$guestToken) {
            $guestToken = Str::uuid()->toString();
        }

        // ✅ Check if the combination already exists
        $exists = GuestRecentlyViewedProduct::where('guest_token', $guestToken)
            ->where('product_id', $productId)
            ->exists();

        if (!$exists) {
            GuestRecentlyViewedProduct::create([
                'guest_token' => $guestToken,
                'product_id' => $productId,
            ]);
        }

        return response()->json([
            'success' => true,
            'guest_token' => $guestToken, // send back token to store on frontend
        ]);
    }
    
    /**
     * @OA\Get(
     *     path="/api/frontend/guest/recent-products",
     *     tags={"Guest"},
     *     summary="Get recently viewed guest products",
     *     description="Returns the last 5 products viewed by a guest using the guest_token from cookies.",
     *     operationId="getGuestRecentProducts",
     *     @OA\Response(
     *         response=200,
     *         description="List of recently viewed products",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="product_id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Product Name"),
     *                     @OA\Property(property="sku", type="string", example="SKU123"),
     *                     @OA\Property(property="price", type="number", format="float", example=99.99),
     *                     @OA\Property(property="sale_price", type="number", format="float", example=89.99),
     *                     @OA\Property(property="best_delivery_date", type="string", format="date", example="2025-06-20"),
     *                     @OA\Property(property="total_reviews", type="integer", example=15),
     *                     @OA\Property(property="avg_rating", type="number", format="float", example=4.5),
     *                     @OA\Property(property="left_stock", type="integer", example=10),
     *                     @OA\Property(property="currency", type="string", example="USD"),
     *                     @OA\Property(property="in_wishlist", type="boolean", example=false),
     *                     @OA\Property(property="images", type="array", @OA\Items(type="string", example="https://example.com/image.jpg")),
     *                     @OA\Property(property="original_price", type="number", format="float", example=99.99),
     *                     @OA\Property(property="front_sale_price", type="number", format="float", example=99.99),
     *                     @OA\Property(property="best_price", type="number", format="float", example=99.99)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="No guest token found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No guest token found.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No recently viewed products found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No recently viewed products found.")
     *         )
     *     )
     * )
     */

     public function getGuestRecentProducts(Request $request)
     {
         // Get token from param or cookie
         $guestToken = $request->input('guest_token') ?? $request->cookie('guest_token');
     
         if (!$guestToken) {
             return response()->json(['message' => 'No guest token found.'], 400);
         }
     
         $data = $this->getGuestRecentlyViewedData($guestToken);
     
         if (empty($data)) {
             return response()->json(['message' => 'No recently viewed products found.'], 404);
         }
     
         return response()->json([
             'success' => true,
             'data' => $data,
         ]);
     }

     private function getGuestRecentlyViewedData(string $guestToken): array
{
    $recentlyViewed = GuestRecentlyViewedProduct::with('product.reviews', 'product.currency')
        ->where('guest_token', $guestToken)
        ->latest()
        ->take(5)
        ->get();

    $data = [];

    foreach ($recentlyViewed as $viewed) {
        $product = $viewed->product;
        if (!$product) continue;

        $images = $product->images;
        if (is_string($images)) {
            $images = json_decode($images, true);
        }
        if (!is_array($images)) {
            $images = [];
        }

        $cleanedImages = collect($images)->flatten()->filter()->values();
        $sellingType = null;
        if ($product->sellingUnitAttribute && $product->sellingUnitAttribute->attribute_value) {
            $fullValue = $product->sellingUnitAttribute->attribute_value;

            $attributeUnit = strpos($fullValue, '/') !== false
                ? trim(explode('/', $fullValue)[1])
                : $fullValue;

            $sellingType = [
                'attribute_value' => $product->sellingUnitAttribute->attribute_value,
                'attribute_value_unit' => $attributeUnit,
            ];
        }


        $data[] = [
            'product_id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'price' => $product->price,
            'sale_price' => $product->sale_price,
            'best_delivery_date' => $product->best_delivery_date,
            'total_reviews' => $product->reviews->count(),
            'avg_rating' => $product->reviews->avg('star'),
            'left_stock' => $product->left_stock ?? 0,
            'currency' => $product->currency->symbol ?? '$',
            'in_wishlist' => false,
            'images' => $cleanedImages,
            'original_price' => $product->price,
            'front_sale_price' => $product->price,
            'best_price' => $product->price,
            "selling_type"=> $sellingType,
        ];
    }

    return $data;
}

     



}

