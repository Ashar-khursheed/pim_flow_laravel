<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Google_Client;
use App\Models\FrontEnd\Customer;
use Illuminate\Support\Str;
use App\Notifications\GuestWelcomeMail;
use App\Notifications\WelcomeMail;
use Illuminate\Support\Facades\Auth;
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
	 *                 required={"name", "email"},
	 *                 @OA\Property(property="is_guest", type="boolean", example=true, description="Set to true if registering as a guest"),
	 *                 @OA\Property(property="name", type="string", example="John Doe"),
	 *                 @OA\Property(property="email", type="string", format="email", example="john@example.com"),
	 *                 @OA\Property(property="password", type="string", format="password", example="secret123"),
	 *                 @OA\Property(property="type", type="string", example="Private"),
	 *                 @OA\Property(property="dob", type="string", format="date", example="1990-01-01"),
	 *                 @OA\Property(property="country_code", type="string", example="+91"),
	 *                 @OA\Property(property="mobile_number", type="string", example="971500000000"),
	 *                 @OA\Property(property="profile_img", type="file", description="Profile image (jpeg, png, webp only, max 1 MB)")
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=201, description="Customer registered successfully", @OA\MediaType(mediaType="application/json")),
	 * )
	 */
	public function register(Request $request)
	{
		if ($request->is_guest == true) {
			$validated = $request->validate([
				'name' => 'required|string|max:255',
				'email' => 'required|string|email|max:255',
			]);

			$existingCustomer = Customer::where('email', $validated['email'])->first();
			if ($existingCustomer) {
				return response()->json([
					'success' => false,
					'message' => 'You are already registered. Please login to continue.',
				], 409);
			}

			$randomPassword = Str::random(8); // alphanumeric
			$hashedPassword = Hash::make($randomPassword);

			$guestCustomer = new Customer([
				'name' => $validated['name'],
				'email' => $validated['email'],
				'password' => $hashedPassword,
				'type' => 'Private',
				'dob' => $request->input('dob'),
				'country_code' => $request->input('country_code'),
				'mobile_number' => $request->input('mobile_number'),
				'profile_img' => $request->input('profile_img'),
			]);
			$guestCustomer->save();

			$guestCustomer->notify(new GuestWelcomeMail($randomPassword));

			return response()->json([
				'success' => true,
				'message' => 'Guest account created successfully.',
				'user' => $guestCustomer,
				'plain_password' => $randomPassword
			], 201);
		}

		/* Normal customer registration */
		$validated = $request->validate([
			'name' => 'required|string|max:255',
			'email' => 'required|string|email|max:255|unique:customers',
			'password' => 'required|string|min:8',
			'type' => 'nullable|string',
			'dob' => 'nullable|date',
			'country_code' => 'nullable|string',
			'mobile_number' => 'nullable|string|max:20',
			'profile_img' => 'nullable|file|mimes:jpeg,jpg,png,webp|max:1024',
		]);

		try {
			$validated['profile_img'] = uploadImageToWebpS3FromFile(
				$request,
				'profile_img',
				env('STORAGE_ENV') . '/customer/profile_img'
			);

			$customer = new Customer([
				'name' => $validated['name'],
				'email' => $validated['email'],
				'password' => Hash::make($validated['password']),
				'type' => $validated['type'] ?? null,
				'dob' => $validated['dob'] ?? null,
				'country_code' => $request->input('country_code') ?? null,
				'mobile_number' => $validated['mobile_number'] ?? null,
				'profile_img' => $validated['profile_img'] ?? null,
			]);
			$customer->save();

			$customer->notify(new WelcomeMail());

			return response()->json([
				'success' => true,
				'message' => 'Customer registered successfully.',
				'user' => $customer
			], 201);

		} catch (\Exception $e) {
			\Log::error('Error registering customer: ' . $e->getMessage());
			return response()->json([
				'success' => false,
				'message' => 'Registration failed. Please try again later.'
			], 500);
		}
	}


	/**
	 * @OA\Get(
	 *     path="/api/frontend/coupons/customer",
	 *     tags={"FrontEnd-Customer"},
	 *     summary="Get customer coupons",
	 *     description="Returns all coupons related to the authenticated customer, grouped as all, available, used, and expired.",
	 *     operationId="getCustomerCoupons",
	 *     security={{"bearerAuth":{}}},
	 *     @OA\Response(response=200, description="List of customer coupons", @OA\MediaType(mediaType="application/json"))
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
	 *     @OA\Response(response=200, description="Search results", @OA\MediaType(mediaType="application/json"))
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


	public function googleLogin(Request $request)
{
    $idToken = $request->input('credential');

    $client = new \Google_Client(['client_id' => '96165540519-5abr44463l214dog6teceibk8nmqlfm1.apps.googleusercontent.com']);
    $payload = $client->verifyIdToken($idToken);

    if (!$payload) {
        return response()->json(['success' => false, 'message' => 'Invalid Google token'], 401);
    }

    $email = $payload['email'];
    $name = $payload['name'] ?? 'Guest';
    $googleProfileImg = $payload['picture'] ?? null;

    $customer = Customer::where('email', $email)->first();
    $randomPassword = null;

    if (!$customer) {
        $dob = $request->input('dob');
        $countryCode = $request->input('country_code');
        $mobileNumber = $request->input('mobile_number');

        $profileImg = $googleProfileImg;

        try {
            if ($profileImg && filter_var($profileImg, FILTER_VALIDATE_URL)) {
                $profileImg = uploadImageToWebpS3FromUrl(
                    $profileImg,
                    env('STORAGE_ENV') . '/customer/profile_img'
                );
            }

            $randomPassword = Str::random(8);
            $hashedPassword = Hash::make($randomPassword);

            $customer = Customer::create([
                'name' => $name,
                'email' => $email,
                'password' => $hashedPassword,
                'type' => 'Private',
                'dob' => $dob,
                'country_code' => $countryCode,
                'mobile_number' => $mobileNumber,
                'profile_img' => $profileImg,
            ]);

            $customer->notify(new GuestWelcomeMail($randomPassword));
        } catch (\Exception $e) {
            \Log::error('Google Login Registration Failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Registration failed. Please try again later.'
            ], 500);
        }
    }

    return response()->json([
        'success' => true,
        'message' => $customer->wasRecentlyCreated
            ? 'User registered successfully using Google.'
            : 'User already exists with this Google account.',
        'email' => $customer->email,
        'plain_password' => $randomPassword, // only non-null for newly registered
        'user' => $customer,
    ]);
}


}
