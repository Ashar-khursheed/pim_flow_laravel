<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Models\FrontEndCountry;
use OpenApi\Annotations as OA;
use App\Models\FrontEnd\Discount;
use App\Models\FrontEnd\DiscountCustomer;
use App\Models\FrontEnd\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;



class CouponController extends Controller
{  
    /**
     * @OA\Post(
     *     path="/api/frontend/coupons/apply",
     *     operationId="applyCoupon",
     *     tags={"FrontEnd-Coupon"},
     *     summary="Apply a coupon to an order",
     *     description="Validates a coupon code and returns the discount and final price if valid.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"coupon_code", "total_order_price"},
     *             @OA\Property(property="coupon_code", type="string", example="WELCOME10"),
     *             @OA\Property(property="total_order_price", type="number", format="float", example=150.00)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Coupon applied successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="discount_amount", type="number", format="float", example=10.00),
     *             @OA\Property(property="final_price", type="number", format="float", example=140.00)
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid coupon or usage conditions not met",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Coupon has expired.")
     *         )
     *     )
     * )
     */
    public function applyCoupon(Request $request)
    {
        $validated = $request->validate([
            'coupon_code' => 'required|string',
            'total_order_price' => 'required|numeric|min:0',
        ]);

        $coupon = Discount::where('code', $request->coupon_code)->first();

        if (!$coupon) {
            return response()->json(['message' => 'Coupon code not found.'], 400);
        }

        if ($coupon->isExpired()) {
            return response()->json(['message' => 'Coupon has expired.'], 400);
        }

        if ($coupon->min_order_price && $request->total_order_price < $coupon->min_order_price) {
            return response()->json(['message' => 'Order price is below the minimum required for this coupon.'], 400);
        }

        if ($coupon->quantity && $coupon->total_used >= $coupon->quantity) {
            return response()->json(['message' => 'Coupon has already been used up.'], 400);
        }

        $discountAmount = min($coupon->value, $request->total_order_price);
        $coupon->increment('total_used');
        $finalPrice = max($request->total_order_price - $discountAmount, 0);

        return response()->json([
            'discount_amount' => $discountAmount,
            'final_price' => $finalPrice,
        ]);
    }

 /**
 * @OA\Get(
 *   path="/api/frontend/coupons",
 *   tags={"FrontEnd-Coupon"},
 *   summary="Get paginated coupons with search & sorting",
 *   security={{"bearerAuth":{}}},
 *   @OA\Parameter(
 *     name="page",
 *     in="query",
 *     description="Page number (pagination)",
 *     required=false,
 *     @OA\Schema(type="integer", example=1)
 *   ),
 *   @OA\Parameter(
 *     name="per_page",
 *     in="query",
 *     description="Items per page",
 *     required=false,
 *     @OA\Schema(type="integer", example=10)
 *   ),
 *   @OA\Parameter(
 *     name="search",
 *     in="query",
 *     description="Search by coupon code or title",
 *     required=false,
 *     @OA\Schema(type="string", example="SUMMER")
 *   ),
 *   @OA\Parameter(
 *     name="sort_by",
 *     in="query",
 *     description="Column to sort by",
 *     required=false,
 *     @OA\Schema(type="string", example="created_at")
 *   ),
 *   @OA\Parameter(
 *     name="sort_order",
 *     in="query",
 *     description="Sort direction asc/desc",
 *     required=false,
 *     @OA\Schema(type="string", example="desc")
 *   ),
 *   @OA\Response(
 *     response=200,
 *     description="Successful response",
 *     @OA\JsonContent(
 *       type="object",
 *       @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Coupon")),
 *       @OA\Property(property="current_page", type="integer"),
 *       @OA\Property(property="last_page", type="integer"),
 *       @OA\Property(property="per_page", type="integer"),
 *       @OA\Property(property="total", type="integer")
 *     )
 *   )
 * )
 */
public function index(Request $request)
{
    $query = Discount::query();

    // 🔎 Searching
    if ($request->filled('search')) {
        $search = $request->search;
        $query->where(function ($q) use ($search) {
            $q->where('code', 'LIKE', "%{$search}%")
              ->orWhere('title', 'LIKE', "%{$search}%");
        });
    }

    // ↕ Sorting
    $sortBy = $request->get('sort_by', 'created_at');
    $sortOrder = $request->get('sort_order', 'desc');
    if (in_array($sortOrder, ['asc', 'desc'])) {
        $query->orderBy($sortBy, $sortOrder);
    }

    // 📄 Pagination
    $perPage = $request->get('per_page', 10);
    $coupons = $query->paginate($perPage);

    return response()->json($coupons);
}

/**
 * @OA\Post(
 *     path="/api/frontend/coupons",
 *     operationId="createCoupon",
 *     tags={"FrontEnd-Coupon"},
 *     summary="Create a new coupon",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"code", "value"},
 *             @OA\Property(property="code", type="string", example="WELCOME10"),
 *             @OA\Property(property="value", type="number", example=50),
 *             @OA\Property(property="type", type="string", enum={"fixed","percent"}, example="fixed"),
 *             @OA\Property(property="min_order_price", type="number", example=100),
 *             @OA\Property(property="quantity", type="integer", example=100),
 *             @OA\Property(property="expires_at", type="string", format="date", example="2025-12-31")
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="Coupon created successfully",
 *         @OA\JsonContent(ref="#/components/schemas/Coupon")
 *     )
 * )
 */

    public function store(Request $request)
    {
    $validated = $request->validate([
        'code' => 'required|string|unique:ec_discounts,code',
        'value' => 'required|numeric|min:0',
        'min_order_price' => 'nullable|numeric|min:0',
        'quantity' => 'nullable|integer|min:1',
        'expires_at' => 'nullable|date|after:today',
        'type' => 'nullable|in:fixed,percent'
    ]);

    $coupon = Discount::create($validated);

    return response()->json([
        'success' => true,
        'message' => 'Coupon created successfully',
        'data' => $coupon
    ], 201);
    }

/**
 * @OA\Get(
 *     path="/api/frontend/coupons/{id}",
 *     operationId="getCouponById",
 *     tags={"FrontEnd-Coupon"},
 *     security={{"bearerAuth":{}}},
 *     summary="Get a coupon by ID",
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="Coupon ID",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Coupon details",
 *         @OA\JsonContent(ref="#/components/schemas/Coupon")
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Coupon not found"
 *     )
 * )
 */
public function show($id)
{
    $coupon = Discount::findOrFail($id);
    return response()->json($coupon);
}

/**
 * @OA\Put(
 *     path="/api/frontend/coupons/{id}",
 *     operationId="updateCoupon",
 *     security={{"bearerAuth":{}}},
 *     tags={"FrontEnd-Coupon"},
 *     summary="Update a coupon",
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="Coupon ID",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="string", example="WELCOME20"),
 *             @OA\Property(property="value", type="number", example=20),
 *             @OA\Property(property="type", type="string", enum={"fixed","percent"}, example="percent"),
 *             @OA\Property(property="min_order_price", type="number", example=200),
 *             @OA\Property(property="quantity", type="integer", example=50),
 *             @OA\Property(property="expires_at", type="string", format="date", example="2026-01-01")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Coupon updated successfully",
 *         @OA\JsonContent(ref="#/components/schemas/Coupon")
 *     )
 * )
 */
public function update(Request $request, $id)
{
    $coupon = Discount::findOrFail($id);

    $validated = $request->validate([
        'code' => 'sometimes|required|string|unique:ec_discounts,code,' . $coupon->id,
        'value' => 'sometimes|required|numeric|min:0',
        'min_order_price' => 'nullable|numeric|min:0',
        'quantity' => 'nullable|integer|min:1',
        'expires_at' => 'nullable|date|after:today',
        'type' => 'nullable|in:fixed,percent'
    ]);

    $coupon->update($validated);

    return response()->json([
        'success' => true,
        'message' => 'Coupon updated successfully',
        'data' => $coupon
    ]);
}

/**
 * @OA\Delete(
 *     path="/api/frontend/coupons/{id}",
 *     operationId="deleteCoupon",
 *     tags={"FrontEnd-Coupon"},
 *     security={{"bearerAuth":{}}},
 *     summary="Delete a coupon",
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="Coupon ID",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Coupon deleted successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Coupon deleted successfully")
 *         )
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Coupon not found"
 *     )
 * )
 */
public function destroy($id)
{
    $coupon = Discount::findOrFail($id);
    $coupon->delete();

    return response()->json([
        'success' => true,
        'message' => 'Coupon deleted successfully'
    ]);
}


    
}
