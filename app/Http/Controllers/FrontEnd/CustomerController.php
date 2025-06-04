<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

use App\Models\FrontEnd\Customer;

class CustomerController extends BaseController
{
	/**
	 * @OA\Post(
	 *     path="/api/frontend/register",
	 *     summary="Register a new customer",
	 *     tags={"FrontEnd-Customer"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="multipart/form-data",
	 *             @OA\Schema(
	 *                 required={"name", "email", "password"},
	 *                 @OA\Property(property="name", type="string", example="John Doe"),
	 *                 @OA\Property(property="email", type="string", format="email", example="john@example.com"),
	 *                 @OA\Property(property="password", type="string", format="password", example="secret123"),
	 *                 @OA\Property(property="dob", type="string", format="date", example="1990-01-01"),
	 *                 @OA\Property(property="mobile_number", type="string", example="971500000000"),
	 *                 @OA\Property(property="profile_img", type="file", description="Profile image (jpeg, png, webp only, max 1 mb)"),
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=201, description="Customer registered successfully", @OA\MediaType(mediaType="application/json")),
	 * )
	 */
	public function register(Request $request)
	{
		$validatedData = $request->validate([
			'name' => 'required|string|max:255',
			'email' => 'required|string|email|max:255|unique:customers',
			'password' => 'required|string|min:8',
			'dob' => 'nullable|date',
			'mobile_number' => 'nullable|string|max:20|unique:customers',
			'profile_img' => 'nullable|file|mimes:jpeg,jpg,png,webp|max:1024',
		]);

		try {
			$validatedData['profile_img'] = uploadImageToWebpS3FromFile(
				$request,
				'profile_img',
				env('STORAGE_ENV') . '/customer/profile_img'
			);

			$customer = new Customer([
				'name' => $validatedData['name'],
				'email' => $validatedData['email'],
				'password' => Hash::make($validatedData['password']),
				'dob' => $validatedData['dob'] ?? null,
				'mobile_number' => $validatedData['mobile_number'] ?? null,
				'profile_img' => $validatedData['profile_img'] ?? null,
			]);

			$customer->save();

			return response()->json([
				'success' => true,
				'message' => 'Customer registered successfully!',
				'user' => $customer
			], 201);

		} catch (\Exception $e) {
			\Log::error('Error registering user: ' . $e->getMessage());
			return response()->json(['error' => 'Failed to register user'], 500);
		}
	}


	/**
     * Get all coupons related to the authenticated customer.
     *
     * @OA\Get(
     *     path="/api/frontend/coupons/customer",
     *     tags={"FrontEnd-Customer"},
     *     summary="Get customer coupons",
     *     description="Returns all coupons related to the authenticated customer, grouped as all, available, used, and expired.",
     *     operationId="getCustomerCoupons",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of customer coupons",
     *         @OA\JsonContent(
     *             @OA\Property(property="all_coupons", type="array", @OA\Items(ref="#/components/schemas/Coupon")),
     *             @OA\Property(property="available_coupons", type="array", @OA\Items(ref="#/components/schemas/Coupon")),
     *             @OA\Property(property="used_coupons", type="array", @OA\Items(ref="#/components/schemas/Coupon")),
     *             @OA\Property(property="expired_coupons", type="array", @OA\Items(ref="#/components/schemas/Coupon"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */


	public function getCustomerCoupons(Request $request)
	{
		$userId = Auth::id();
	
		if (!$userId) {
			return response()->json(['message' => 'User not authenticated.'], 401);
		}
	
		// Get all coupons related to the customer
		$allCoupons = Discount::whereHas('customers', function ($query) use ($userId) {
				$query->where('customer_id', $userId);
			})
			->get()
			->map(function ($discount) {
				return [
					'id' => $discount->id,
					'code' => $discount->code,
					'value' => $discount->value,
					'type' => $discount->type,
					'min_order_price' => $discount->min_order_price,
					'start_date' => $discount->start_date,
					'end_date' => $discount->end_date,
				];
			});
	
		// Get used coupons from ec_customer_used_coupons table
		$usedCoupons = DB::table('ec_customer_used_coupons')
			->join('ec_discounts', 'ec_customer_used_coupons.discount_id', '=', 'ec_discounts.id')
			->where('ec_customer_used_coupons.customer_id', $userId)
			->get(['ec_discounts.id', 'ec_discounts.code', 'ec_discounts.value', 'ec_discounts.type', 'ec_discounts.min_order_price', 'ec_discounts.start_date', 'ec_discounts.end_date']);
	
		// Get expired coupons (past end_date)
		$expiredCoupons = Discount::whereHas('customers', function ($query) use ($userId) {
				$query->where('customer_id', $userId);
			})
			->where('end_date', '<', Carbon::now()) // Coupons that have expired
			->get()
			->map(function ($discount) {
				return [
					'id' => $discount->id,
					'code' => $discount->code,
					'value' => $discount->value,
					'type' => $discount->type,
					'min_order_price' => $discount->min_order_price,
					'start_date' => $discount->start_date,
					'end_date' => $discount->end_date,
				];
			});
	
		// Get available (valid) coupons
		$availableCoupons = Discount::whereHas('customers', function ($query) use ($userId) {
				$query->where('customer_id', $userId);
			})
			->where(function ($query) {
				$query->where('end_date', '>=', Carbon::now())
					  ->orWhereNull('end_date');
			})
			->get()
			->map(function ($discount) {
				return [
					'id' => $discount->id,
					'code' => $discount->code,
					'value' => $discount->value,
					'type' => $discount->type,
					'min_order_price' => $discount->min_order_price,
					'start_date' => $discount->start_date,
					'end_date' => $discount->end_date,
				];
			});
	
		return response()->json([
			'all_coupons' => $allCoupons, // This includes all coupons linked to the customer
			'available_coupons' => $availableCoupons,
			'used_coupons' => $usedCoupons,
			'expired_coupons' => $expiredCoupons
		]);
	}
	
	
	/**
     * Search coupons belonging to the authenticated customer.
     *
     * @OA\Get(
     *     path="/api/frontend/coupons/search",
     *     tags={"FrontEnd-Customer"},
     *     summary="Search customer coupons",
     *     description="Search through the customer's coupons using a query term.",
     *     operationId="searchCustomerCoupons",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         required=true,
     *         description="Search term (coupon code, type, or value)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Search results",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="coupons", type="array", @OA\Items(ref="#/components/schemas/Coupon"))
     *         )
     *     )
     * )
     */

	public function searchCustomerCoupons(Request $request)
	{
		$userId = Auth::id();
	
		if (!$userId) {
			return response()->json([
				'success' => false,
				'message' => 'User not authenticated.'
			], 200);
		}
	
		$searchTerm = $request->input('query');
	
		$discounts = Discount::whereHas('customers', function ($query) use ($userId) {
			$query->where('customer_id', $userId);
		})
		->where(function ($query) use ($searchTerm) {
			$query->where('code', 'LIKE', "%{$searchTerm}%")
				  ->orWhere('type', 'LIKE', "%{$searchTerm}%")
				  ->orWhere('value', 'LIKE', "%{$searchTerm}%");
		})
		->where(function ($query) {
			$query->where('end_date', '>=', Carbon::now())
				  ->orWhereNull('end_date');
		})
		->get();
	
		if ($discounts->isEmpty()) {
			return response()->json([
				'success' => false,
				'message' => 'No matching coupons found.'
			], 200);
		}
	
		$coupons = $discounts->map(function ($discount) {
			return [
				'id' => $discount->id,
				'code' => $discount->code,
				'value' => $discount->value,
				'type' => $discount->type,
				'min_order_price' => $discount->min_order_price,
				'start_date' => $discount->start_date,
				'end_date' => $discount->end_date,
			];
		});
	
		return response()->json([
			'success' => true,
			'coupons' => $coupons
		], 200);
	}
}
