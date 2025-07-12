<?php
namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\FrontEnd\Wishlist;
use App\Models\Product;

class WishlistController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/frontend/wishlist/add",
     *     tags={"Frontend-Wishlist"},
     *     summary="Add a product to the wishlist",
     *     description="Adds a product to the authenticated user's wishlist.",
     *     operationId="addToWishlist",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"product_id"},
     *             @OA\Property(property="product_id", type="integer", example=123, description="ID of the product to add")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Product added to wishlist",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Product added to wishlist"),
     *             @OA\Property(
     *                 property="wishlist",
     *                 type="object",
     *                 @OA\Property(property="customer_id", type="integer", example=1),
     *                 @OA\Property(property="product_id", type="integer", example=123),
     *                 @OA\Property(property="in_wishlist", type="integer", example=1),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function addToWishlist(Request $request)
    {
        // Validate the incoming request data
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:ec_products,id',
        ]);

        $customerId = Auth::id();
        
        // Check if product already exists in wishlist
        $existingWishlist = Wishlist::where('customer_id', $customerId)
                                  ->where('product_id', $validated['product_id'])
                                  ->first();

        if ($existingWishlist) {
            return response()->json([
                'message' => 'Product already in wishlist',
                'wishlist' => [
                    'customer_id' => $existingWishlist->customer_id,
                    'product_id' => $existingWishlist->product_id,
                    'in_wishlist' => 1,
                    'created_at' => $existingWishlist->created_at,
                    'updated_at' => $existingWishlist->updated_at,
                ]
            ], 200);
        }

        // Create new wishlist entry
        $wishlist = Wishlist::create([
            'customer_id' => $customerId,
            'product_id' => $validated['product_id'],
        ]);

        return response()->json([
            'message' => 'Product added to wishlist',
            'wishlist' => [
                'customer_id' => $wishlist->customer_id,
                'product_id' => $wishlist->product_id,
                'in_wishlist' => 1,
                'created_at' => $wishlist->created_at,
                'updated_at' => $wishlist->updated_at,
            ]
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/frontend/wishlist",
     *     tags={"Frontend-Wishlist"},
     *     summary="Get all products in wishlist",
     *     description="Returns wishlist products for authenticated user.",
     *     operationId="getWishlist",
     *     @OA\Response(
     *         response=200,
     *         description="List of wishlist items",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="wishlist",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(
     *                         property="product",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=123),
     *                         @OA\Property(property="name", type="string", example="Product name"),
     *                         @OA\Property(property="images", type="array", @OA\Items(type="string", format="url")),
     *                         @OA\Property(property="in_wishlist", type="integer", example=1)
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    // public function getWishlist(Request $request)
    // {
    //     $userId = Auth::id();
        
    //     $wishlistItems = Wishlist::with('product')
    //                     ->where('customer_id', $userId)
    //                     ->orderBy('created_at', 'desc')
    //                     ->get();

    //     $wishlistItems->transform(function ($item) {
    //         $product = $item->product;

    //         if ($product) {
    //             // Ensure images is properly formatted
    //             if (is_string($product->images)) {
    //                 $product->images = json_decode($product->images, true) ?: [];
    //             }
    //             $product->images = collect($product->images);
    //             $product->in_wishlist = 1;
    //         }

    //         return $item;
    //     });

    //     return response()->json([
    //         'wishlist' => $wishlistItems,
    //         'total_items' => $wishlistItems->count()
    //     ]);
    // }
    public function getWishlist(Request $request)
{
    $userId = Auth::id();

    $wishlistItems = Wishlist::with('product.currency', 'product.productSuppliers', 'product.brand')
        ->where('customer_id', $userId)
        ->orderBy('created_at', 'desc')
        ->get();

    // Fetch applicable discounts for the user
    $userDiscountIds = DB::table('ec_discount_customers')
        ->where('customer_id', $userId)
        ->pluck('discount_id')
        ->toArray();

    // Fetch applicable product discounts
    $productIds = $wishlistItems->pluck('product.id')->filter()->toArray();

    $productDiscounts = DB::table('ec_discount_products')
        ->whereIn('product_id', $productIds)
        ->select('product_id', 'discount_id')
        ->get()
        ->groupBy('product_id')
        ->map(fn($discounts) => $discounts->pluck('discount_id')->toArray());

    // Get all discounts
    $discounts = DB::table('ec_discounts')
        ->whereIn('id', array_merge($userDiscountIds, $productDiscounts->flatten()->toArray()))
        ->get()
        ->keyBy('id');

    $wishlistItems->transform(function ($item) use ($productDiscounts, $discounts) {
        $product = $item->product;

        if ($product) {
            // Brand
            $product->brand_name = optional($product->brand)->name;
            $product->unsetRelation('brand');

            // Images
            $product->images = collect(json_decode($product->images, true) ?? []);
            $product->in_wishlist = 1;

            // Discounts
            $discountIds = $productDiscounts[$product->id] ?? [];
            $product->discounts = collect($discountIds)
                ->map(fn($id) => $discounts[$id] ?? null)
                ->filter()
                ->values();

            // Currency
            $symbol = optional($product->currency)->symbol;
            $product->unsetRelation('currency');
            $product->currency = $symbol;

            // Supplier Details
            $firstSupplier = $product->productSuppliers->first();
            if ($firstSupplier) {
                $product->vendor_sku = $firstSupplier->vendor_sku ?? null;
                $product->price = (float) $firstSupplier->price;
                $product->sale_price = (float) $firstSupplier->sale_price;
                $product->original_price = (float) $firstSupplier->price;
                $product->front_sale_price = (float) ($firstSupplier->sale_price ?? $firstSupplier->price);
                $product->best_price = (float) $firstSupplier->price;
                $product->vendor_id = $firstSupplier->vendor_id;
                $product->map = (float) $firstSupplier->map;
                $product->inventory = $firstSupplier->inventory;
                $product->in_stock = $firstSupplier->in_stock;
                $product->delivery_days = $firstSupplier->delivery_days;
                $product->return_policy = $firstSupplier->return_policy;
                $product->free_shipping = $firstSupplier->free_shipping;
                $product->warranty_information = $firstSupplier->warranty_information;
            } else {
                // Safe fallback values
                $product->vendor_sku = null;
                $product->price = null;
                $product->sale_price = null;
                $product->original_price = null;
                $product->front_sale_price = null;
                $product->best_price = null;
                $product->vendor_id = null;
                $product->map = null;
                $product->inventory = null;
                $product->in_stock = null;
                $product->delivery_days = null;
                $product->return_policy = null;
                $product->free_shipping = null;
                $product->warranty_information = null;
            }
        }

        return $item;
    });

    return response()->json([
        'wishlist' => $wishlistItems,
        'total_items' => $wishlistItems->count()
    ]);
}


    /**
     * @OA\Delete(
     *     path="/api/frontend/wishlist/remove",
     *     tags={"Frontend-Wishlist"},
     *     summary="Remove a product from wishlist",
     *     description="Removes a product from the authenticated user's wishlist.",
     *     operationId="removeFromWishlist",
     *     @OA\Parameter(
     *         name="product_id",
     *         in="query",
     *         required=true,
     *         description="ID of the product to remove",
     *         @OA\Schema(type="integer", example=123)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product removed from wishlist",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Product removed from wishlist"),
     *             @OA\Property(property="in_wishlist", type="integer", example=0)
     *         )
     *     ),
     *     @OA\Response(response=404, description="Product not found in wishlist"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error"),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function removeFromWishlist(Request $request)
    {
        $request->validate([
            'product_id' => 'required|integer'
        ]);

        $productId = $request->query('product_id');
        $userId = Auth::id();
        
        $deleted = Wishlist::where('customer_id', $userId)
                           ->where('product_id', $productId)
                           ->delete();

        if ($deleted) {
            return response()->json([
                'message' => 'Product removed from wishlist',
                'in_wishlist' => 0
            ]);
        }

        return response()->json([
            'message' => 'Product not found in wishlist'
        ], 404);
    }

    /**
     * @OA\Get(
     *     path="/api/frontend/wishlist/check/{product_id}",
     *     tags={"Frontend-Wishlist"},
     *     summary="Check if product is in wishlist",
     *     description="Check if a specific product is in the authenticated user's wishlist.",
     *     operationId="checkWishlist",
     *     @OA\Parameter(
     *         name="product_id",
     *         in="path",
     *         required=true,
     *         description="ID of the product to check",
     *         @OA\Schema(type="integer", example=123)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Wishlist check result",
     *         @OA\JsonContent(
     *             @OA\Property(property="in_wishlist", type="boolean", example=true),
     *             @OA\Property(property="product_id", type="integer", example=123)
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function checkWishlist($productId)
    {
        $userId = Auth::id();
        
        $exists = Wishlist::where('customer_id', $userId)
                         ->where('product_id', $productId)
                         ->exists();

        return response()->json([
            'in_wishlist' => $exists,
            'product_id' => (int) $productId
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/frontend/wishlist/count",
     *     tags={"Frontend-Wishlist"},
     *     summary="Get wishlist items count",
     *     description="Returns the total number of items in the authenticated user's wishlist.",
     *     operationId="getWishlistCount",
     *     @OA\Response(
     *         response=200,
     *         description="Wishlist count",
     *         @OA\JsonContent(
     *             @OA\Property(property="count", type="integer", example=5)
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     security={{ "bearerAuth": {} }}
     * )
     */
    public function getWishlistCount()
    {
        $userId = Auth::id();
        
        $count = Wishlist::where('customer_id', $userId)->count();

        return response()->json([
            'count' => $count
        ]);
    }
}