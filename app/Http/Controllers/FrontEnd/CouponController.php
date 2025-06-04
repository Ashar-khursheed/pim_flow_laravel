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
     *     tags={"Coupons"},
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
}
