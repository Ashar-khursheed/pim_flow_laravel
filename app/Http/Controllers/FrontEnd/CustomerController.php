<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Google_Client;
use App\Models\Discount;
use Illuminate\Support\Carbon;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\DB;
use App\Models\FrontEnd\Coupon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Bus;
use Illuminate\Bus\Batch;

use App\Models\FrontEnd\Customer;

use App\Jobs\Welcome\GuestWelcomeMailJob;
use App\Jobs\Welcome\WelcomeMailJob;

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

			$randomPassword = Str::random(8);
			$hashedPassword = Hash::make($randomPassword);

			$guestCustomer = new Customer([
				'name' => $validated['name'],
				'email' => $validated['email'],
				'password' => $hashedPassword,
				'type' => 'Private',
				'dob' => $request->input('dob'),
				'country_code' => $request->input('country_code'),
				'mobile_number' => $request->input('mobile_number'),
				'business_name' => $request->input('business_name'),
				'profile_img' => $request->input('profile_img'),
			]);
			$guestCustomer->save();

			$batch = Bus::batch([])->name('Welcome mail on guest')->dispatch();

			$batch->options['queue'] = config('app.website') . '_GUST_WLCM';
			$batch->add(new GuestWelcomeMailJob([
				'recordId' => $guestCustomer->id,
				'randomPassword' => $randomPassword,
			]));

			$this->sendToOdoo($guestCustomer);

			return response()->json([
				'success' => true,
				'message' => 'Guest account created successfully.',
				'user' => $guestCustomer,
				'plain_password' => $randomPassword
			], 201);
		}

		$validated = $request->validate([
			'name' => 'required|string|max:255',
			'email' => 'required|string|email|max:255|unique:customers',
			'password' => 'required|string|min:8',
			'mobile_number' => 'nullable|string|max:20', // 👈 ADD THIS
			'type' => 'nullable|string',
			'business_name' => 'nullable|string',
			'dob' => 'nullable|date',
			'country_code' => 'nullable|string',
			'country_code' => 'nullable|string',
			'business_name' => 'nullable|string',
			'profile_img' => 'nullable|file|mimes:jpeg,jpg,png,webp|max:1024',
			'business_name' => 'nullable|string',
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
				'business_name' =>  $validated['business_name'] ?? null,
			]);
			$customer->save();

			$batch = Bus::batch([])->name('Welcome mail on register')->dispatch();

			$batch->options['queue'] = config('app.website') . '_WLCM';
			$batch->add(new WelcomeMailJob([
				'recordId' => $customer->id,
			]));

			$this->sendToOdoo($customer);

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


	private function sendToOdoo($customer)
	{
		try {
			$response = Http::withHeaders([
				'Content-Type' => 'application/json',
				'Cookie' => 'session_id=57ddd7bc96ea7900b2615ef1db5e65409c0e0e98',
			])->post('https://horecastore-staging-20736821.dev.odoo.com/web/dataset/call_kw', [
				'jsonrpc' => '2.0',
				'method' => 'call',
				'params' => [
					'model' => 'res.partner',
					'method' => 'create',
					'args' => [[
						'company_type' => 'person',
						'name' => $customer->name,
						'email' => $customer->email,
						'phone' => ($customer->country_code ? $customer->country_code . ' ' : '') . $customer->mobile_number,
						'mobile' => ($customer->country_code ? $customer->country_code . ' ' : '') . $customer->mobile_number,
						'business_name' =>  $customer->business_name,
						'image_1920' => '',
						'website' => '',
						'is_customer' => "1",
						'vat_not_register' => "0",
						'vat' => '215444',
						'street' => '12',
						'street2' => 'new',
						'city' => 'Surat',
						'zip' => '395006',
						'website_partner_id' => $customer->id,
						'website_customer' => 1
					]],
					'kwargs' => new \stdClass()
				],
				'id' => 1
			]);

			if ($response->successful()) {
				\Log::info('Customer synced to Odoo successfully.', ['odoo_response' => $response->json()]);
			} else {
				\Log::error('Failed to sync customer to Odoo.', [
					'status' => $response->status(),
					'body' => $response->body()
				]);
			}
		} catch (\Exception $e) {
			\Log::error('Exception syncing to Odoo: ' . $e->getMessage());
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


	public function appleLogin(Request $request)
	{
		$request->validate([
			'identity_token' => 'required|string',
			'email' => 'nullable|email',
			'name'  => 'nullable|string',
		]);

		$identityToken = $request->input('identity_token');

		try {
			// Decode JWT header
			$jwtHeader = json_decode(base64_decode(explode('.', $identityToken)[0]), true);
			$kid = $jwtHeader['kid'];

			// Get Apple public keys
			$appleKeys = Http::get('https://appleid.apple.com/auth/keys')->json();
			$publicKeys = JWK::parseKeySet($appleKeys);

			// Decode token using Apple’s public key
			$decoded = JWT::decode($identityToken, $publicKeys[$kid]);
			$email = $decoded->email ?? $request->email;
			$appleSub = $decoded->sub;

			if (!$appleSub) {
				return response()->json(['message' => 'Apple token does not contain a valid sub.'], 422);
			}

			// Try to find user by apple_id first
			$customer = Customer::where('apple_id', $appleSub)->first();

			// If not found by apple_id, try email fallback
			if (!$customer && $email) {
				$customer = Customer::where('email', $email)->first();
			}

			// If still not found, create new
			if (!$customer) {
				$customer = Customer::create([
					'apple_id' => $appleSub,
					'email' => $email,
					'name' => $request->name ?? 'Hello',
					'password' => Hash::make(Str::random(32)),
					'is_social_login' => true,
					'created_by' => null,
					'dob' => null,
					'country_code' => null,
					'mobile_number' => null,
					'profile_img' => null,
				]);
			} else {
				// Update apple_id if missing
				if (!$customer->apple_id) {
					$customer->update(['apple_id' => $appleSub]);
				}
			}

			$token = $customer->createToken('apple-login')->plainTextToken;

			$batch = Bus::batch([])->name('Welcome mail on apple login')->dispatch();

			$batch->options['queue'] = config('app.website') . '_WLCM';
			$batch->add(new WelcomeMailJob([
				'recordId' => $customer->id,
			]));

			return response()->json([
				'user' => $customer,
				'token' => $token,
			]);
		} catch (\Exception $e) {
			return response()->json([
				'message' => 'Apple login failed',
				'error' => $e->getMessage(),
			], 401);
		}
	}

	public function googleLogin(Request $request)
	{
		$idToken = $request->input('credential');

		$client = new \Google_Client(['client_id' => '96165540519-5abr44463l214dog6teceibk8nmqlfm1.apps.googleusercontent.com']);
		$payload = $client->verifyIdToken($idToken);

		if (!$payload) {
			return response()->json([
				'success' => false,
				'message' => 'Invalid Google token'
			], 401);
		}

		$email = $payload['email'];
		$name = $payload['name'] ?? 'Guest';
		$googleProfileImg = $payload['picture'] ?? null;

		$customer = Customer::where('email', $email)->first();

		if (!$customer) {
			$dob = $request->input('dob');
			$countryCode = $request->input('country_code');
			$mobileNumber = $request->input('mobile_number');

			try {
				$customer = Customer::create([
					'name' => $name,
					'email' => $email,
					'password' => null,
					'is_social_login' => true,
					'type' => 'Private',
					'dob' => $dob,
					'country_code' => $countryCode,
					'mobile_number' => $mobileNumber,
					'profile_img' => $googleProfileImg,
				]);

				$batch = Bus::batch([])->name('Welcome mail on google login')->dispatch();

				$batch->options['queue'] = config('app.website') . '_WLCM';
				$batch->add(new WelcomeMailJob([
					'recordId' => $customer->id,
				]));
			} catch (\Exception $e) {
				\Log::error('Google Login Registration Failed: ' . $e->getMessage());

				return response()->json([
					'success' => false,
					'message' => 'Registration failed. Please try again later.'
				], 500);
			}
		} else {
			// Prevent login if existing user signed up with password (not Google)
			if ($customer->is_social_login === false) {
				return response()->json([
					'success' => false,
					'message' => 'This email is already registered using email & password. Please login using your password.'
				], 403);
			}
		}

		// ✅ Issue token for authenticated session
		$token = $customer->createToken('google-login')->plainTextToken;

		return response()->json([
			'success' => true,
			'message' => $customer->wasRecentlyCreated
				? 'User registered successfully using Google.'
				: 'User logged in successfully with Google.',
			'token' => $token,
			'user' => $customer,
		]);
	}

	/**
	 * @OA\Post(
	 *     path="/api/frontend/update-profile",
	 *     tags={"FrontEnd-Customer"},
	 *     summary="Update customer profile",
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="multipart/form-data",
	 *             @OA\Schema(
	 *                 @OA\Property(property="name", type="string", example="John Doe"),
	 *                 @OA\Property(property="email", type="string", format="email", example="john@example.com"),
	 *                 @OA\Property(property="password", type="string", format="password", example="secret123"),
	 *                 @OA\Property(property="password_confirmation", type="string", format="password", example="secret123"),
	 *                 @OA\Property(property="type", type="string", example="customer"),
	 *                 @OA\Property(property="dob", type="string", format="date", example="1990-01-01"),
	 *                 @OA\Property(property="country_code", type="string", example="+971"),
	 *                 @OA\Property(property="mobile_number", type="string", example="501234567"),
	 *                 @OA\Property(property="profile_img", type="string", format="binary"),
	 *                 @OA\Property(property="business_name", type="string", example="ABC Trading LLC"),
	 *                 @OA\Property(property="business_licence", type="string", format="binary", description="Upload business licence (PDF only)"),
	 *                 @OA\Property(property="trn_number", type="string", example="1234567890"),
	 *                 @OA\Property(property="vat_certificate", type="string", format="binary", description="Upload VAT certificate (PDF only)")
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Profile updated successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth": {}}},
	 * )
	 */
	public function updateProfile(Request $request)
	{
		$user = auth()->user();

		$request->validate([
			'name'             => 'nullable|string|max:255',
			'business_name'    => 'nullable|string',
			'business_licence' => 'nullable|file|mimes:pdf|max:2048',
			'trn_number'       => 'nullable|string',
			'vat_certificate'  => 'nullable|file|mimes:pdf|max:2048',

			'email'            => 'nullable|email|unique:users,email,' . $user->id,
			'password'         => 'nullable|string|min:6|confirmed',
			'type'             => 'nullable|string',
			'dob'              => 'nullable|date',
			'country_code'     => 'nullable|string|max:10',
			'mobile_number'    => 'nullable|string|max:20',
			'profile_img'      => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
		]);


		$user->fill($request->only([
			'name',
			'email',
			'type',
			'dob',
			'country_code',
			'mobile_number',
			'business_name',
			'trn_number',
		]));

		if ($request->filled('password')) {
			$user->password = Hash::make($request->password);
		}

		if ($request->hasFile('profile_img')) {
			$user->profile_img = uploadImageToWebpS3FromFile(
				$request,
				'profile_img',
				env('STORAGE_ENV') . '/customer/profile_img'
			);
		}

		/* Business licence PDF */
		if ($request->hasFile('business_licence')) {
			$user->business_licence = uploadPdfToS3FromFile($request, 'business_licence', env('STORAGE_ENV') . '/customer/business_licence');
		}

		/* VAT certificate PDF */
		if ($request->hasFile('vat_certificate')) {
			$user->vat_certificate = uploadPdfToS3FromFile($request, 'vat_certificate', env('STORAGE_ENV') . '/customer/vat_certificate');
		}

		$user->save();

		return response()->json([
			'success' => true,
			'message' => 'Profile updated successfully',
			'data' => $user,
		]);
	}

	public function getProfile(Request $request)
	{
		$user = auth()->user();

		if (!$user) {
			return response()->json([
				'success' => false,
				'message' => 'Unauthorized',
			], 401);
		}

		return response()->json([
			'success' => true,
			'message' => 'Profile fetched successfully',
			'data' => [
				'id'             => $user->id,
				'name'           => $user->name,
				'email'          => $user->email,
				'type'           => $user->type,
				'dob'            => $user->dob,
				'country_code'   => $user->country_code,
				'mobile_number'  => $user->mobile_number,
				'profile_img'    => $user->profile_img,
				'created_at'     => $user->created_at,
				'updated_at'     => $user->updated_at,
			],
		]);
	}

	/**
	 * @OA\Post(
	 *     path="/api/frontend/customers/change-password",
	 *     summary="Change customer password",
	 *     tags={"FrontEnd-Customer"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"old_password", "new_password", "new_password_confirmation"},
	 *             @OA\Property(property="old_password", type="string", example="OldPass123"),
	 *             @OA\Property(property="new_password", type="string", example="NewPass123"),
	 *             @OA\Property(property="new_password_confirmation", type="string", example="NewPass123")
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Password changed successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function changePassword(Request $request)
	{
		$request->validate([
			'old_password' => 'required|string',
			'new_password' => 'required|string|min:8|confirmed',
		]);

		$customer = auth()->user();

		if (!Hash::check($request->old_password, $customer->password)) {
			return response()->json([
				'success' => false,
				'message' => 'Old password is incorrect.',
			], 422);
		}

		$customer->password = Hash::make($request->new_password);
		$customer->save();

		return response()->json([
			'success' => true,
			'message' => 'Password changed successfully',
		]);
	}

	/**
	 * @OA\Post(
	 *     path="/api/frontend/coupon-register",
	 *     summary="Coupon Register a new customer",
	 *     tags={"FrontEnd-Customer"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="multipart/form-data",
	 *             @OA\Schema(
	 *                 required={"name", "email"},	 *                 
	 *                 @OA\Property(property="name", type="string", example="John Doe"),
	 *                 @OA\Property(property="email", type="string", format="email", example="john@example.com"),                
	 *                 @OA\Property(property="mobile_number", type="string", example="971500000000")
	 *                 
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=201, description="Customer registered successfully", @OA\MediaType(mediaType="application/json")),
	 * )
	 */
	public function couponRegister(Request $request)
	{
		$validated = $request->validate([
			'name' => 'required|string|max:255',
			'email' => 'required|string|email|max:255',
			'mobile_number' => 'required|string|max:255',
		]);

		$existingCustomer = Customer::where('email', $validated['email'])->first();
		if ($existingCustomer) {
			return response()->json([
				'success' => false,
				'message' => 'You are already registered. Please login to continue.',
			], 409);
		}

		$randomPassword = Str::random(8);
		$hashedPassword = Hash::make($randomPassword);

		$guestCustomer = new Customer([
			'name' => $validated['name'],
			'email' => $validated['email'],
			'password' => $hashedPassword,
			'type' => 'Private',
			'mobile_number' => $request->input('mobile_number'),
		]);
		$guestCustomer->save();
		
		$data['customer_ids'] = $guestCustomer->id;
		$data['code'] = 'WELCOME50';
		$data['name'] = 'welcome50';
		$data['description'] = 'string';
		$data['type'] = 'fixed';
		$data['value'] = 50; //AED 
		$data['basis'] = 'customer';
		$data['min_order_value'] = 550;
		$data['max_order_value'] = 999999;
		$data['usage_type'] = 'once';
		$data['usage_limit'] = 10000;
		$data['usage_limit_per_customer'] = 1;
		$data['start_date'] = now();
		$data['expire_date'] = now()->addDays(365);
		$data['is_active'] = '1';
		$data['status'] = 'approved';
		$data['created_by'] = 1;
		$data['approved_by'] = 1;
		$data['approved_at'] = now();	
		$coupon = Coupon::where('code','WELCOME50')->first();
		if (!$coupon) {					
			$coupon = Coupon::create($data);
		}

		// Attach relationships based on basis
		if ($data['basis'] === 'customer' && !empty($data['customer_ids'])) {
			$coupon->customers()->attach($data['customer_ids']);
		}

		$coupon->load(['customers']);

		$batch = Bus::batch([])->name('Welcome mail on guest')->dispatch();

		$batch->options['queue'] = config('app.website') . '_GUST_WLCM';
		$batch->add(new GuestWelcomeMailJob([
			'recordId' => $guestCustomer->id,
			'randomPassword' => $randomPassword,
		]));

		$this->sendToOdoo($guestCustomer);

		return response()->json([
			'success' => true,
			'message' => 'Guest account created successfully.',
			'user' => $guestCustomer,
			'plain_password' => $randomPassword
		], 201);
	}
}
