<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\FrontEnd\Cart;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class CartController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/frontend/cart/add",
     *     tags={"Frontend-Cart"},
     *     summary="Add product to cart",
     *     description="Add a product to the user's shopping cart. If the product already exists in cart, the quantity will be increased.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"product_id", "quantity"},
     *             @OA\Property(property="product_id", type="integer", description="ID of the product to add", example=1),
     *             @OA\Property(property="quantity", type="integer", minimum=1, description="Quantity to add", example=2)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product added to cart successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="user_id", type="integer", example=1),
     *                 @OA\Property(property="product_id", type="integer", example=1),
     *                 @OA\Property(property="quantity", type="integer", example=3),
     *                 @OA\Property(property="currency_id", type="integer", example=1),
     *                 @OA\Property(property="currency_title", type="string", example="USD")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="User not authenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized user")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="product_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The product id field is required.")
     *                 )
     *             )
     *         )
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    // public function addToCart(Request $request)
    // {
    //     $request->validate([
    //         'product_id' => 'required|exists:ec_products,id',
    //         'quantity' => 'required|integer|min:1',
    //     ]);

    //     $productId = $request->input('product_id');
    //     $quantity = $request->input('quantity');

    //     if (Auth::check()) {
    //         $userId = Auth::id();

    //         // Check if the user has already added this product
    //         $cartItem = Cart::where('user_id', $userId)
    //                         ->where('product_id', $productId)
    //                         ->first();

    //         if ($cartItem) {
    //             \Log::info('Cart item already exists', ['cartItem' => $cartItem]);

    //             // Update the quantity by adding the new quantity
    //             \Log::info('Updating cart item with added quantity', ['old_quantity' => $cartItem->quantity, 'added_quantity' => $quantity]);
    //             $cartItem->quantity += $quantity;
    //             $cartItem->save();
    //         } else {
    //             \Log::info('No cart item found, creating new');
    //             $cartItem = Cart::create([
    //                 'user_id' => $userId,
    //                 'product_id' => $productId,
    //                 'quantity' => $quantity,
    //             ]);
    //         }

    //         $cartItem = Cart::with('product.currency')->find($cartItem->id);

    //         return response()->json([
    //             'success' => true,
    //             'data' => [
    //                 'id' => $cartItem->id,
    //                 'user_id' => $cartItem->user_id,
    //                 'product_id' => $cartItem->product_id,
    //                 'quantity' => $cartItem->quantity,
    //                 'currency_id' => $cartItem->product->currency->id,
    //                 'currency_title' => $cartItem->product->currency->title,
    //             ],
    //         ]);
    //     }

    //     return response()->json([
    //         'success' => false,
    //         'message' => 'Unauthorized user',
    //     ], 200);
    // }
    public function addToCart(Request $request)
{
    $request->validate([
        'product_id' => 'required|exists:ec_products,id',
        'quantity' => 'required|integer|min:1',
    ]);

    $productId = $request->input('product_id');
    $quantity = $request->input('quantity');

    if (!Auth::check()) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized user',
        ], 200);
    }

    $userId = Auth::id();

    $cartItem = Cart::where('user_id', $userId)
        ->where('product_id', $productId)
        ->first();

    if ($cartItem) {
        $cartItem->quantity += $quantity;
        $cartItem->save();
    } else {
        $cartItem = Cart::create([
            'user_id' => $userId,
            'product_id' => $productId,
            'quantity' => $quantity,
        ]);
    }

    $cartItem->load('product.reviews', 'product.currency', 'product.sellingUnitAttribute', 'product.per_unit_price_attributes.attributeDetails');

    $product = $cartItem->product;
    if (!$product) {
        return response()->json([
            'success' => false,
            'message' => 'Product not found',
        ]);
    }

    // Wishlist check
    $wishlistProductIds = DB::table('ec_wish_lists')
        ->where('customer_id', $userId)
        ->pluck('product_id')
        ->map(fn($id) => (int) $id)
        ->toArray();

    // Reviews
    $totalReviews = $product->reviews->count();
    $avgRating = $totalReviews > 0 ? round($product->reviews->avg('star'), 1) : null;

    // Images
    $imageUrls = is_string($product->images)
        ? json_decode($product->images, true)
        : (array) $product->images;

    // Selling Type
    $sellingType = null;
    if ($product->sellingUnitAttribute && $product->sellingUnitAttribute->attribute_value) {
        $fullValue = $product->sellingUnitAttribute->attribute_value;
        $attributeUnit = strpos($fullValue, '/') !== false
            ? trim(explode('/', $fullValue)[1])
            : $fullValue;

        $sellingType = [
            'attribute_value' => $fullValue,
            'attribute_value_unit' => $attributeUnit,
        ];
    }

    // Per Unit Price
    $basePrice = ($product->sale_price > 0) ? $product->sale_price : $product->price;
    $unitsPerCase = $product->per_unit_price_attributes->firstWhere(fn($attr) => $attr->attributeDetails->name === 'Units per Case');
    $packType = $product->per_unit_price_attributes->firstWhere(fn($attr) => $attr->attributeDetails->name === 'Pack Type');

    $perUnitPrice = null;
    if ($basePrice && $unitsPerCase && is_numeric($unitsPerCase->attribute_value)) {
        $unitValue = (float) $unitsPerCase->attribute_value;
        if ($unitValue > 0) {
            $calculated = round($basePrice / $unitValue, 2);
            $perUnitPrice = $calculated . ' /' . ($packType?->attribute_value ?? '');
        }
    }

    $currencyTitle = $product->currency->symbol ?? $product->price;

    return response()->json([
        'success' => true,
        'message' => 'Product added to cart successfully.',
        'data' => [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'price' => $product->price,
            'sale_price' => $product->sale_price,
            'original_price' => $product->price,
            'front_sale_price' => $product->sale_price ?? $product->price,
            'total_reviews' => $totalReviews,
            'avg_rating' => $avgRating,
            'left_stock' => ($product->quantity ?? 0) - ($product->units_sold ?? 0),
            'currency' => $currencyTitle,
            'in_wishlist' => in_array($product->id, $wishlistProductIds),
            'images' => $imageUrls,
            'selling_type' => $sellingType,
            'per_unit_price' => $perUnitPrice,
        ]
    ]);
}


    /**
     * @OA\Get(
     *     path="/api/frontend/cart",
     *     tags={"Frontend-Cart"},
     *     summary="View cart items",
     *     description="Retrieve all items in the user's shopping cart with product details, discounts, and wishlist status",
     *     @OA\Response(
     *         response=200,
     *         description="Cart items retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="currency_title",
     *                 type="array",
     *                 @OA\Items(type="string", example="USD")
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="user_id", type="integer", example=1),
     *                     @OA\Property(property="product_id", type="integer", example=1),
     *                     @OA\Property(property="quantity", type="integer", example=2),
     *                     @OA\Property(
     *                         property="product",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Product Name"),
     *                         @OA\Property(property="price", type="number", format="float", example=99.99),
     *                         @OA\Property(property="image", type="string", example="https://example.com/storage/products/image.jpg"),
     *                         @OA\Property(property="in_wishlist", type="boolean", example=false),
     *                         @OA\Property(
     *                             property="images",
     *                             type="array",
     *                             @OA\Items(type="string", example="https://example.com/storage/products/image1.jpg")
     *                         ),
     *                         @OA\Property(
     *                             property="currency",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="title", type="string", example="USD")
     *                         ),
     *                         @OA\Property(
     *                             property="discounts",
     *                             type="array",
     *                             @OA\Items(
     *                                 type="object",
     *                                 @OA\Property(property="id", type="integer", example=1),
     *                                 @OA\Property(property="title", type="string", example="Summer Sale"),
     *                                 @OA\Property(property="value", type="number", format="float", example=10.00)
     *                             )
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function viewCart(Request $request)
    {
        $userId = auth()->id();
        $isUserLoggedIn = $userId !== null;

        Log::info('User logged in:', ['user_id' => $userId]);

        // Get wishlist product IDs
        $wishlistProductIds = $isUserLoggedIn
            ? DB::table('ec_wish_lists')
                ->where('customer_id', $userId)
                ->pluck('product_id')
                ->map(fn($id) => (int) $id)
                ->toArray()
            : session()->get('guest_wishlist', []);

        // Fetch cart items with product and currency details
        $cartItems = Auth::check()
            ? Cart::where('user_id', $userId)->with('product.currency')->get()
            : Cart::where('session_id', $request->session()->getId())->with('product.currency')->get();

        // Fetch applicable discounts for the user
        $userDiscountIds = DB::table('ec_discount_customers')
            ->where('customer_id', $userId)
            ->pluck('discount_id')
            ->toArray();

        // Fetch applicable product discounts (allow multiple discounts per product)
        $productDiscounts = DB::table('ec_discount_products')
            ->whereIn('product_id', $cartItems->pluck('product.id'))
            ->select('product_id', 'discount_id')
            ->get()
            ->groupBy('product_id')
            ->map(fn($discounts) => $discounts->pluck('discount_id')->toArray());

        // Get all discounts from the discount table
        $discounts = DB::table('ec_discounts')
            ->whereIn('id', array_merge($userDiscountIds, $productDiscounts->flatten()->toArray()))
            ->get()
            ->keyBy('id');

        // Process each cart item
        $cartItems->each(function ($item) use ($wishlistProductIds, $productDiscounts, $discounts) {
            $item->product->in_wishlist = in_array($item->product->id, $wishlistProductIds);
        
            $item->product->images = collect(json_decode($item->product->images, true) ?? []);
        
            $item->product->original_price = $item->product->price;
            $item->product->front_sale_price = $item->product->sale_price ?? $item->product->price;
        
            // Attach all applicable discounts
            $discountIds = $productDiscounts[$item->product->id] ?? [];
            $item->product->discounts = collect($discountIds)->map(fn($id) => $discounts[$id] ?? null)->filter()->values();
        
            // Attach only the currency symbol and remove full currency object
            if (isset($item->product->currency)) {
                $item->product->currency_symbol = $item->product->currency->symbol;
                unset($item->product->currency); // remove full currency object
            }
        });
        

        $currencyTitles = $cartItems->pluck('product.currency_symbol')->unique()->filter()->values();

        return response()->json([
            'success' => true,
            'currency' => $currencyTitles,
            'data' => $cartItems,
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/frontend/cart/clear",
     *     tags={"Frontend-Cart"},
     *     summary="Clear entire cart",
     *     description="Remove all items from the user's shopping cart",
     *     @OA\Response(
     *         response=201,
     *         description="Cart cleared successfully or was already empty",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Cart cleared successfully.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cart was already empty",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Cart was already empty or could not be cleared.")
     *         )
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function clearCart(Request $request)
    {
        $deleted = 0; // Track rows deleted

        if (Auth::check()) {
            $deleted = Cart::where('user_id', Auth::id())->delete();
        } else {
            $deleted = Cart::where('session_id', $request->session()->getId())->delete();
        }

        if ($deleted > 0) {
            return response()->json([
                'success' => true,
                'message' => 'Cart cleared successfully.',
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Cart was already empty or could not be cleared.',
            ]);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/frontend/cart/product/{productId}",
     *     tags={"Frontend-Cart"},
     *     summary="Remove specific product from cart",
     *     description="Remove a specific product from the user's shopping cart",
     *     @OA\Parameter(
     *         name="productId",
     *         in="path",
     *         required=true,
     *         description="ID of the product to remove from cart",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product removed from cart successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true)
     *         )
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function clearProductFromCart(Request $request, $productId)
    {
        // Determine if the user is logged in and get the user ID
        $userId = auth()->id();

        if (Auth::check()) {
            // Remove the product from the cart for logged-in user
        Cart::where('user_id', $userId)
            ->where('product_id', $productId)
                ->delete();
        } else {
            // Remove the product from the cart for guest user (using session ID)
            Cart::where('session_id', $request->session()->getId())
                ->where('product_id', $productId)
            ->delete();
        }

      return response()->json(['success' => true]);
    }

    /**
     * @OA\Post(
     *     path="/api/frontend/cart/update-quantity",
     *     tags={"Frontend-Cart"},
     *     summary="Update cart item quantity",
     *     description="Update the quantity of a specific product in the cart",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"product_id", "quantity"},
     *             @OA\Property(property="product_id", type="integer", description="ID of the product", example=1),
     *             @OA\Property(property="quantity", type="integer", minimum=1, description="New quantity", example=3)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cart item quantity updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="user_id", type="integer", example=1),
     *                 @OA\Property(property="session_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="product_id", type="integer", example=1),
     *                 @OA\Property(property="quantity", type="integer", example=3),
     *                 @OA\Property(property="currency_title", type="string", example="USD"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2023-01-01T12:00:00.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2023-01-01T12:30:00.000000Z")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Cart item not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Item not found in cart.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="quantity",
     *                     type="array",
     *                     @OA\Items(type="string", example="The quantity must be at least 1.")
     *                 )
     *             )
     *         )
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function updateCartQuantity(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:ec_products,id',
            'quantity' => 'required|integer|min:1',
        ]);
    
        $productId = $request->input('product_id');
        $quantity = $request->input('quantity');
    
        if (Auth::check()) {
            $userId = auth()->id();
            $cartItem = Cart::where('user_id', $userId)->where('product_id', $productId)->with('product.currency')->first();
        } else {
            $sessionId = $request->session()->getId();
            $cartItem = Cart::where('session_id', $sessionId)->where('product_id', $productId)->with('product.currency')->first();
        }
    
        if ($cartItem) {
            $cartItem->quantity = $quantity;
            $cartItem->update();
    
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $cartItem->id,
                    'user_id' => $cartItem->user_id,
                    'session_id' => $cartItem->session_id,
                    'product_id' => $cartItem->product_id,
                    'quantity' => $cartItem->quantity,
                    'currency_title' => $cartItem->product->currency->symbol ?? null, // Currency title inside data
                    'created_at' => $cartItem->created_at,
                    'updated_at' => $cartItem->updated_at,
                ],
            ]);
        }
    
        return response()->json(['success' => false, 'message' => 'Item not found in cart.'], 404);
    }


    /**
     * @OA\Post(
     *     path="/api/frontend/cart/decrease-quantity",
     *     tags={"Frontend-Cart"},
     *     summary="Decrease cart item quantity",
     *     description="Decrease the quantity of a specific product in the cart. If quantity becomes 0 or less, the item will be removed from cart.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"product_id", "quantity"},
     *             @OA\Property(property="product_id", type="integer", description="ID of the product", example=1),
     *             @OA\Property(property="quantity", type="integer", minimum=1, description="Quantity to decrease", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Cart item quantity decreased successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="user_id", type="integer", example=1),
     *                 @OA\Property(property="session_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="product_id", type="integer", example=1),
     *                 @OA\Property(property="quantity", type="integer", example=2),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2023-01-01T12:00:00.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2023-01-01T12:30:00.000000Z")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Item removed from cart (quantity became 0)",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Item removed from cart.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Cart item not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Item not found in cart.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="quantity",
     *                     type="array",
     *                     @OA\Items(type="string", example="The quantity must be at least 1.")
     *                 )
     *             )
     *         )
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function decreaseQuantity(Request $request)
    {
        // Validate request inputs
        $request->validate([
            'product_id' => 'required|exists:ec_products,id',
            'quantity' => 'required|integer|min:1',
        ]);
    
        $productId = $request->input('product_id');
        $quantityToDecrease = $request->input('quantity');
    
        // Determine if the user is logged in and retrieve the cart item
        $cartItem = null;
        if (Auth::check()) {
            $userId = auth()->id();
            $cartItem = Cart::where('user_id', $userId)
                ->where('product_id', $productId)
                ->first();
        } else {
            $sessionId = $request->session()->getId();
            $cartItem = Cart::where('session_id', $sessionId)
                ->where('product_id', $productId)
                ->first();
        }
    
        // Check if the cart item exists
        if (!$cartItem) {
            Log::info('Cart item not found for product', [
                'product_id' => $productId,
                'user_id' => Auth::id(),
                'session_id' => $request->session()->getId()
            ]);
            return response()->json(['success' => false, 'message' => 'Item not found in cart.'], 404);
        }
    
        // Decrease the quantity and check if it should be removed
        $cartItem->quantity -= $quantityToDecrease;
    
        if ($cartItem->quantity <= 0) {
            $cartItem->delete();
            return response()->json(['success' => true, 'message' => 'Item removed from cart.']);
        } else {
            $cartItem->save();
    
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $cartItem->id,
                    'user_id' => $cartItem->user_id,
                    'session_id' => $cartItem->session_id,
                    'product_id' => $cartItem->product_id,
                    'quantity' => $cartItem->quantity,
                    'created_at' => $cartItem->created_at,
                    'updated_at' => $cartItem->updated_at,
                ],
            ]);
        }
    }


    /**
     * @OA\Get(
     *     path="/api/frontend/cart/guest",
     *     tags={"Frontend-Cart"},
     *     summary="View guest cart items",
     *     description="Retrieve all items in the guest user's shopping cart (session-based)",
     *     @OA\Response(
     *         response=200,
     *         description="Guest cart items retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="session_id", type="string", example="abc123session"),
     *                     @OA\Property(property="product_id", type="integer", example=1),
     *                     @OA\Property(property="quantity", type="integer", example=2),
     *                     @OA\Property(
     *                         property="product",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Product Name"),
     *                         @OA\Property(property="price", type="number", format="float", example=99.99),
     *                         @OA\Property(property="image", type="string", example="product-image.jpg")
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function viewCartGuest(Request $request)
    {
        $cartItems = Cart::where('session_id', $request->session()->getId())->with('product')->get();

        return response()->json([
            'success' => true,
            'data' => $cartItems,
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/frontend/cart/guest/clear",
     *     tags={"Frontend-Cart"},
     *     summary="Clear guest cart",
     *     description="Remove all items from the guest user's shopping cart (session-based)",
     *     @OA\Response(
     *         response=200,
     *         description="Guest cart cleared successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true)
     *         )
     *     )
     * )
     */
    public function clearCartGuest(Request $request)
    {
        // Delete all items from the cart for a guest user (based on session ID)
        Cart::where('session_id', $request->session()->getId())->delete();

        return response()->json(['success' => true]);
    }


    /**
     * Add multiple products to the cart.
     *
     * @OA\Post(
     *     path="/api/frontend/cart/add-multiple",
     *     tags={"Frontend-Cart"},
     *     summary="Add multiple products to cart",
     *     description="Adds an array of products with quantity to the cart for the authenticated or guest user.",
     *     operationId="addMultipleToCart",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"products"},
     *             @OA\Property(
     *                 property="products",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"product_id", "quantity"},
     *                     @OA\Property(property="product_id", type="integer", example=1, description="ID of the product"),
     *                     @OA\Property(property="quantity", type="integer", example=2, description="Quantity to add")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Products added to cart",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Products added to cart"),
     *             @OA\Property(
     *                 property="cart",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="product_id", type="integer", example=1),
     *                     @OA\Property(property="quantity", type="integer", example=2),
     *                     @OA\Property(property="user_id", type="integer", nullable=true),
     *                     @OA\Property(property="session_id", type="string", nullable=true),
     *                     @OA\Property(
     *                         property="product",
     *                         type="object",
     *                         description="Details of the product",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Product Name")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 example={"products.0.product_id": {"The product_id field is required."}}
     *             )
     *         )
     *     )
     * )
     */

    public function addMultipleToCart(Request $request)
    {
        // Validate the request input
        $request->validate([
            'products' => 'required|array',
            'products.*.product_id' => 'required|exists:ec_products,id',
            'products.*.quantity' => 'required|integer|min:1',
        ]);

        $products = $request->input('products');
        $userId = Auth::check() ? Auth::id() : null; // Get authenticated user ID
        $sessionId = $userId ? null : $request->session()->getId(); // Get session ID for guests

        foreach ($products as $item) {
            $productId = $item['product_id'];
            $quantity = $item['quantity'];

            // Query to find existing cart item
            $cartItem = Cart::where(function($query) use ($userId, $sessionId) {
                if ($userId) {
                    $query->where('user_id', $userId);
                } else {
                    $query->where('session_id', $sessionId);
                }
            })
            ->where('product_id', $productId)
            ->first();

            if ($cartItem) {
                // Update quantity if item already in cart
                $cartItem->quantity += $quantity;
                $cartItem->save();
            } else {
                // Create new cart item
                Cart::create([
                    'user_id' => $userId,
                    'session_id' => $sessionId,
                    'product_id' => $productId,
                    'quantity' => $quantity,
                ]);
            }
        }

        // Fetch the current cart items
        $cartItems = Cart::where(function($query) use ($userId, $sessionId) {
            if ($userId) {
                $query->where('user_id', $userId);
            } else {
                $query->where('session_id', $sessionId);
            }
        })->with('product')
        ->get();

        return response()->json([
            'success' => true,
            'message' => 'Products added to cart',
            'cart' => $cartItems
        ]);
    }


    /**
     * @OA\Get(
     *     path="/api/frontend/cart-summary",
     *     tags={"Frontend-Cart"},
     *     summary="Get cart summary for the authenticated user or guest session",
     *     description="Returns subtotal, tax, total, shipping rate, and other summary details for the cart.",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Cart summary returned successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="subtotal", type="number", format="float", example=150.00),
     *             @OA\Property(property="tax", type="number", format="float", example=15.00),
     *             @OA\Property(property="total_with_tax", type="number", format="float", example=165.00),
     *             @OA\Property(property="savings", type="number", format="float", example=20.00),
     *             @OA\Property(property="item_count", type="integer", example=3),
     *             @OA\Property(property="currency_title", type="string", example="USD")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function cartSummary(Request $request)
    {
        // Determine if the user is logged in
        $userId = auth()->id();
        $sessionId = $userId ? null : $request->session()->getId();

        // Fetch cart items with product details and currency information
        $cartItems = Cart::where(function ($query) use ($userId, $sessionId) {
            if ($userId) {
                $query->where('user_id', $userId);
            } else {
                $query->where('session_id', $sessionId);
            }
        })->with('product.currency')->get();

        // Initialize summary variables
        $subtotal = 0;
        $total = 0;
        $savings = 0;
        $currencyTitle = $cartItems->first()->product->currency->symbol; // Default to 'USD' if no currency found

        foreach ($cartItems as $item) {
            // Use sale_price if available, otherwise use price
            $itemPrice = ($item->product->sale_price && $item->product->sale_price > 0)
                ? $item->product->sale_price
                : $item->product->price;

            $subtotal += $item->quantity * $itemPrice;
            $total += $item->quantity * $item->product->price;
        }

        $savings = $total - $subtotal;

        // Calculate tax and total including tax
        $tax = $subtotal * 0.10;
        $totalWithTax = $subtotal + $tax;



        // Return the cart summary with currency title
        return response()->json([
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total_with_tax' => $totalWithTax,
            'savings' => $savings,
            'item_count' => $cartItems->count(),
            'currency_title' => $currencyTitle,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/frontend/cart/total-products",
     *     tags={"Frontend-Cart"},
     *     summary="Get total quantity of products in cart for authenticated user",
     *     description="Returns the total number of items in the cart for a logged-in user.",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Total products returned successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="total", type="integer", example=5)
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */

    public function totalProductsInCart(Request $request)
    {
        $userId = auth()->id();

        $totalQuantity = Cart::where('user_id', $userId)->sum('quantity');

        return response()->json(['total' => $totalQuantity]);
    }


   /**
     * @OA\Get(
     *     path="/api/frontend/cart/total-products-guest",
     *     tags={"Frontend-Cart"},
     *     summary="Get total quantity of products in cart for guest user",
     *     description="Returns the total number of items in the cart for a guest session using session ID.",
     *     @OA\Response(
     *         response=200,
     *         description="Total products for guest returned successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="total", type="integer", example=3)
     *         )
     *     )
     * )
     */
    public function totalProductsInCartGuest(Request $request)
    {
        // Get the session ID from the current request
        $sessionId = $request->session()->getId();

        // Get the total quantity of items for the guest session
        $totalQuantity = Cart::where('session_id', $sessionId)->sum('quantity');

        return response()->json(['total' => $totalQuantity]);
    }



}