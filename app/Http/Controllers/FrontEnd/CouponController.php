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
   // ↕ Sorting
    $allowedSorts = ['id', 'code', 'title', 'created_at', 'updated_at']; // add other valid columns
    $sortBy = $request->get('sort_by', 'created_at');
    $sortOrder = $request->get('sort_order', 'desc');

    if (!in_array($sortBy, $allowedSorts)) {
        $sortBy = 'created_at'; // fallback
    }

    if (!in_array($sortOrder, ['asc', 'desc'])) {
        $sortOrder = 'desc';
    }

    $query->orderBy($sortBy, $sortOrder);


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
 *     security={{"bearerAuth":{}}},
 *     summary="Create a new coupon",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"title", "code", "value", "type", "store_id"},
 *             @OA\Property(property="title", type="string", example="Welcome Discount"),
 *             @OA\Property(property="code", type="string", example="WELCOME20"),
 *             @OA\Property(property="value", type="number", example=20),
 *             @OA\Property(property="type", type="string", enum={"fixed","percent"}, example="percent"),
 *             @OA\Property(property="description", type="string", example="New customer welcome discount"),
 *             @OA\Property(property="start_date", type="string", format="date", example="2025-08-21"),
 *             @OA\Property(property="end_date", type="string", format="date", example="2025-12-31"),
 *             @OA\Property(property="quantity", type="integer", example=100),
 *             @OA\Property(property="min_order_price", type="number", example=50),
 *             @OA\Property(property="can_use_with_promotion", type="boolean", example=true),
 *             @OA\Property(property="discount_on", type="string", enum={"order","product","shipping"}, example="order"),
 *             @OA\Property(property="product_quantity", type="integer", example=1),
 *             @OA\Property(property="type_option", type="string", example="general"),
 *             @OA\Property(property="target", type="string", enum={"all","specific_products","categories"}, example="all"),
 *             @OA\Property(property="apply_via_url", type="boolean", example=false),
 *             @OA\Property(property="display_at_checkout", type="boolean", example=true),
 *             @OA\Property(property="store_id", type="integer", example=1)
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="Coupon created successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Coupon created successfully"),
 *             @OA\Property(property="data", ref="#/components/schemas/Coupon")
 *         )
 *     ),
 *     @OA\Response(
 *         response=422,
 *         description="Validation error"
 *     )
 * )
 */
public function store(Request $request)
{
    try {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:ec_discounts,code',
            'value' => 'required|numeric|min:0',
            'type' => 'required|in:fixed,percent',
            'description' => 'nullable|string|max:1000',
            'start_date' => 'nullable|date|after_or_equal:today',
            'end_date' => 'nullable|date|after:start_date',
            'quantity' => 'nullable|integer|min:1|max:10000',
            'min_order_price' => 'nullable|numeric|min:0',
            'can_use_with_promotion' => 'boolean',
            'discount_on' => 'nullable|in:order,product,shipping',
            'product_quantity' => 'nullable|integer|min:1',
            'type_option' => 'nullable|string|max:100',
            'target' => 'nullable|in:all,specific_products,categories',
            'apply_via_url' => 'boolean',
            'display_at_checkout' => 'boolean',
            'store_id' => 'required|integer|exists:stores,id'
        ]);

        // Set default values
        $validated['total_used'] = 0;
        $validated['can_use_with_promotion'] = $validated['can_use_with_promotion'] ?? false;
        $validated['apply_via_url'] = $validated['apply_via_url'] ?? false;
        $validated['display_at_checkout'] = $validated['display_at_checkout'] ?? true;
        $validated['discount_on'] = $validated['discount_on'] ?? 'order';
        $validated['target'] = $validated['target'] ?? 'all';

        // Additional validation for percent type
        if ($validated['type'] === 'percent' && $validated['value'] > 100) {
            return response()->json([
                'success' => false,
                'message' => 'Percentage discount cannot exceed 100%',
                'errors' => ['value' => ['Percentage discount cannot exceed 100%']]
            ], 422);
        }

        $coupon = Discount::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Coupon created successfully',
            'data' => $coupon->load('store') // Load store relationship if exists
        ], 201);

    } catch (ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $e->errors()
        ], 422);
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to create coupon',
            'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
        ], 500);
    }
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
 *             @OA\Property(property="title", type="string", example="Updated Welcome Discount"),
 *             @OA\Property(property="code", type="string", example="WELCOME25"),
 *             @OA\Property(property="value", type="number", example=25),
 *             @OA\Property(property="type", type="string", enum={"fixed","percent"}, example="percent"),
 *             @OA\Property(property="description", type="string", example="Updated description"),
 *             @OA\Property(property="start_date", type="string", format="date", example="2025-08-21"),
 *             @OA\Property(property="end_date", type="string", format="date", example="2025-12-31"),
 *             @OA\Property(property="quantity", type="integer", example=150),
 *             @OA\Property(property="min_order_price", type="number", example=75),
 *             @OA\Property(property="can_use_with_promotion", type="boolean", example=false),
 *             @OA\Property(property="discount_on", type="string", enum={"order","product","shipping"}, example="product"),
 *             @OA\Property(property="product_quantity", type="integer", example=2),
 *             @OA\Property(property="type_option", type="string", example="premium"),
 *             @OA\Property(property="target", type="string", enum={"all","specific_products","categories"}, example="categories"),
 *             @OA\Property(property="apply_via_url", type="boolean", example=true),
 *             @OA\Property(property="display_at_checkout", type="boolean", example=false)
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Coupon updated successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Coupon updated successfully"),
 *             @OA\Property(property="data", ref="#/components/schemas/Coupon")
 *         )
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Coupon not found"
 *     ),
 *     @OA\Response(
 *         response=422,
 *         description="Validation error"
 *     )
 * )
 */
public function update(Request $request, $id)
{
    try {
        $coupon = Discount::findOrFail($id);
        
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'code' => 'sometimes|required|string|max:50|unique:ec_discounts,code,' . $coupon->id,
            'value' => 'sometimes|required|numeric|min:0',
            'type' => 'sometimes|required|in:fixed,percent',
            'description' => 'nullable|string|max:1000',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'quantity' => 'nullable|integer|min:1|max:10000',
            'min_order_price' => 'nullable|numeric|min:0',
            'can_use_with_promotion' => 'boolean',
            'discount_on' => 'nullable|in:order,product,shipping',
            'product_quantity' => 'nullable|integer|min:1',
            'type_option' => 'nullable|string|max:100',
            'target' => 'nullable|in:all,specific_products,categories',
            'apply_via_url' => 'boolean',
            'display_at_checkout' => 'boolean',
            'store_id' => 'sometimes|required|integer|exists:stores,id'
        ]);

        // Additional validation for percent type
        if (isset($validated['type']) && $validated['type'] === 'percent' && isset($validated['value']) && $validated['value'] > 100) {
            return response()->json([
                'success' => false,
                'message' => 'Percentage discount cannot exceed 100%',
                'errors' => ['value' => ['Percentage discount cannot exceed 100%']]
            ], 422);
        }

        // Check if coupon type is being changed and validate value accordingly
        if (isset($validated['value']) && !isset($validated['type'])) {
            if ($coupon->type === 'percent' && $validated['value'] > 100) {
                return response()->json([
                    'success' => false,
                    'message' => 'Percentage discount cannot exceed 100%',
                    'errors' => ['value' => ['Percentage discount cannot exceed 100%']]
                ], 422);
            }
        }

        // Prevent updating total_used directly
        unset($validated['total_used']);

        $coupon->update($validated);
        
        return response()->json([
            'success' => true,
            'message' => 'Coupon updated successfully',
            'data' => $coupon->fresh()->load('store') // Reload with fresh data and store relationship
        ]);

    } catch (ModelNotFoundException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Coupon not found'
        ], 404);
    } catch (ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $e->errors()
        ], 422);
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to update coupon',
            'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
        ], 500);
    }
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
