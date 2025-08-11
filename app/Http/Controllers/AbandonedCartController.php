<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\FrontEnd\Cart;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AbandonedCartController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/abandoned-carts",
     *     tags={"Carts"},
     *      security={{"bearerAuth":{}}},
     *     summary="Get list of abandoned carts",
     *     description="Returns a paginated list of abandoned carts with customer info, addresses, product, and brand",
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by customer name or product name",
     *         required=false,
     *         @OA\Schema(type="string", example="John")
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort by column",
     *         required=false,
     *         @OA\Schema(type="string", example="created_at")
     *     ),
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Sort order (asc or desc)",
     *         required=false,
     *         @OA\Schema(type="string", example="desc")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of abandoned carts",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="quantity", type="integer", example=2),
     *                 @OA\Property(property="created_at", type="string", example="2025-08-10T12:00:00Z"),
     *                 @OA\Property(property="customer", type="object",
     *                     @OA\Property(property="id", type="integer", example=5),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="addresses", type="array", @OA\Items(
     *                         @OA\Property(property="address", type="string", example="123 Main Street"),
     *                         @OA\Property(property="city", type="string", example="New York")
     *                     ))
     *                 ),
     *                 @OA\Property(property="product", type="object",
     *                     @OA\Property(property="name", type="string", example="Nike Shoes"),
     *                     @OA\Property(property="sku", type="string", example="NK123"),
     *                     @OA\Property(property="brand", type="object",
     *                         @OA\Property(property="name", type="string", example="Nike")
     *                     )
     *                 )
     *             ))
     *         )
     *     )
     * )
     */
//   public function index(Request $request)
//     {
//         $threshold = Carbon::now()->subHours(1);

//         $query = Cart::with([
//             'customer',
//             'customer.addresses',
//             'product' => function ($q) {
//                 $q->select('id', 'sku', 'name', 'images', 'brand_id')
//                 ->with(['brand:id,name']);
//             },
//         ])->where('created_at', '<=', $threshold);

//         // Search filter
//         if ($request->filled('search')) {
//             $searchTerm = $request->search;
//             $query->whereHas('customer', function ($q) use ($searchTerm) {
//                 $q->where('name', 'like', "%{$searchTerm}%");
//             })->orWhereHas('product', function ($q) use ($searchTerm) {
//                 $q->where('name', 'like', "%{$searchTerm}%");
//             });
//         }

//         // Sorting
//         $sortBy = $request->get('sort_by', 'created_at');
//         $sortOrder = $request->get('sort_order', 'desc');
//         $allowedSortFields = ['created_at', 'quantity', 'id'];

//         if (!in_array($sortBy, $allowedSortFields)) {
//             $sortBy = 'created_at';
//         }

//         $query->orderBy($sortBy, $sortOrder);

//         // Pagination
//         $perPage = $request->get('per_page', 10);
//         $carts = $query->paginate($perPage);

//         return response()->json([
//             'status' => true,
//             'data' => $carts
//         ]);
//     }

    public function index(Request $request)
    {
        $threshold = Carbon::now()->subHours(1);

        // Get all abandoned carts with relations
        $carts = Cart::with([
            'customer:id,name,email',
            'customer.customerAddress',
            'product' => function ($q) {
                $q->select('id', 'sku', 'name', 'images', 'brand_id')
                ->with(['brand:id,name']);
            },
        ])
        ->where('created_at', '<=', $threshold)
        ->get()
        ->groupBy('user_id'); // Group by customer

        // Transform into customer-wise structure
        $result = $carts->map(function ($items) {
            $customer = $items->first()->customer;
            return [
                'customer' => $customer,
                'carts' => $items->map(function ($cart) {
                    return [
                        'id' => $cart->id,
                        'quantity' => $cart->quantity,
                        'created_at' => $cart->created_at,
                        'product' => $cart->product
                    ];
                })->values()
            ];
        })->values();

        return response()->json([
            'status' => true,
            'data' => $result
        ]);
    }

   
    /**
     * @OA\Get(
     *     path="/api/abandoned-carts/{id}",
     *     tags={"Carts"},
     *   security={{"bearerAuth":{}}},
     *     summary="Get details of a specific abandoned cart",
     *     description="Returns abandoned cart details by ID with customer info, addresses, product, and brand",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Cart ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Abandoned cart details",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="quantity", type="integer", example=2),
     *                 @OA\Property(property="created_at", type="string", example="2025-08-10T12:00:00Z"),
     *                 @OA\Property(property="customer", type="object",
     *                     @OA\Property(property="id", type="integer", example=5),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="addresses", type="array", @OA\Items(
     *                         @OA\Property(property="address", type="string", example="123 Main Street"),
     *                         @OA\Property(property="city", type="string", example="New York")
     *                     ))
     *                 ),
     *                 @OA\Property(property="product", type="object",
     *                     @OA\Property(property="name", type="string", example="Nike Shoes"),
     *                     @OA\Property(property="sku", type="string", example="NK123"),
     *                     @OA\Property(property="brand", type="object",
     *                         @OA\Property(property="name", type="string", example="Nike")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Cart not found"
     *     )
     * )
     */
    public function show($id)
    {
        $threshold = Carbon::now()->subHours(1);

        $cart = Cart::with([
                'customer',
                'customer.addresses',
                'product.brand',
            ])
            ->where('created_at', '<=', $threshold)
            ->find($id);

        if (!$cart) {
            return response()->json([
                'status' => false,
                'message' => 'Cart not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $cart
        ]);
    }

}
