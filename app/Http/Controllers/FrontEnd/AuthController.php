<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\FrontEnd\Customer;
use App\Notifications\GuestWelcomeMail;
use App\Notifications\WelcomeMail;
use Illuminate\Support\Str;
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use Illuminate\Support\Facades\Http;


class AuthController extends Controller
{
	/**
	 * @OA\Post(
	 *     path="/api/frontend/login",
	 *     summary="Customer Login",
	 *     tags={"Auth"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"email","password"},
	 *             @OA\Property(property="email", type="string", format="email"),
	 *             @OA\Property(property="password", type="string", format="password")
	 *         ),
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Successful login",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="token", type="string")
	 *         ),
	 *     ),
	 *     @OA\Response(response=401, description="Unauthorized"),
	 * )
	 */
	// public function store(Request $request)
	// {
	// 	$request->validate([
	// 		'email' => 'required|email',
	// 		'password' => 'required',
	// 	]);

	// 	$customer = Customer::where('email', $request->email)->first();

	// 	if (!$customer || !Hash::check($request->password, $customer->password)) {
	// 		return response()->json([
	// 			'success' => false,
	// 			'message' => 'The provided credentials are incorrect.'
	// 		], 401);
	// 	}

	// 	$token = $customer->createToken('auth_token')->plainTextToken;

	// 	return response()->json([
	// 		'message' => 'Login successful',
	// 		'customer' => $customer,
	// 		'token' => $token
	// 	]);
	// }
	public function store(Request $request)
{
    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    $customer = Customer::where('email', $request->email)->first();

    if (!$customer) {
        return response()->json([
            'success' => false,
            'message' => 'User not found.',
        ], 404);
    }

    // 🛑 Prevent password login for social accounts (Google/Apple)
    if ($customer->is_social_login) {
        return response()->json([
            'success' => false,
            'message' => 'This email is linked with a social login (Google/Apple). Please log in using that method.',
        ], 403);
    }

    // ✅ Traditional login (email/password)
    if (!Hash::check($request->password, $customer->password)) {
        return response()->json([
            'success' => false,
            'message' => 'The provided credentials are incorrect.',
        ], 401);
    }

    $token = $customer->createToken('auth_token')->plainTextToken;

    return response()->json([
        'success' => true,
        'message' => 'Login successful',
        'customer' => $customer,
        'token' => $token,
    ]);
}


	/**
	 * @OA\Post(
	 *     path="/api/frontend/logout",
	 *     summary="Customer Logout",
	 *     tags={"Auth"},
	 *     security={{"bearerAuth":{}}},
	 *     @OA\Response(
	 *         response=200,
	 *         description="Successfully logged out",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="message", type="string", example="Logout successful")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=401,
	 *         description="Unauthenticated"
	 *     )
	 * )
	 */
	public function logout(Request $request)
	{
		$user = $request->user();

		if ($user) {
			$user->currentAccessToken()->delete(); // Revoke the current token only
		}

		return response()->json([
			'message' => 'Logout successful'
		]);
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

        // Prepare name if not provided
       // Generate name from email if not provided
			// Get email and requested name
			$email = $decoded->email ?? $request->email;
			$rawName = $request->input('name');

			// If name is not provided, extract from email
			if (empty($rawName) && !empty($email)) {
				$emailPrefix = explode('@', $email)[0]; // e.g. "noman.peera" or "noman_peera"
				$emailPrefix = preg_replace('/[\.\_\-]+/', ' ', $emailPrefix); // Replace . _ - with space
				$emailPrefix = preg_replace('/[^a-zA-Z\s]/', '', $emailPrefix); // Remove numbers and special chars
				$rawName = ucwords(strtolower(trim($emailPrefix))); // Capitalize: "noman peera" => "Noman Peera"
			}

			$nameToUse = $rawName ?: 'Apple User'; // Final fallback

			// Now create user
			if (!$customer) {
				$customer = Customer::create([
					'apple_id' => $appleSub,
					'email' => $email,
					'name' => $nameToUse,
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

	// 	public function appleLogin(Request $request)
	// {
	// 	$request->validate([
	// 		'identity_token' => 'required|string',
	// 		'email' => 'nullable|email',
	// 		'name' => 'nullable|string',
	// 	]);

	// 	$identityToken = $request->input('identity_token');

	// 	try {
	// 		// Decode Apple token header
	// 		$jwtHeader = json_decode(base64_decode(explode('.', $identityToken)[0]), true);
	// 		$kid = $jwtHeader['kid'];

	// 		// Get Apple public keys
	// 		$appleKeys = Http::get('https://appleid.apple.com/auth/keys')->json();
	// 		$publicKeys = JWK::parseKeySet($appleKeys);

	// 		// Decode token using Apple's public key
	// 		$decoded = JWT::decode($identityToken, $publicKeys[$kid]);

	// 		$email = $decoded->email ?? $request->email;
	// 		$appleSub = $decoded->sub;
	// 		$name = $request->input('name') ?? 'Apple User';

	// 		if (!$appleSub) {
	// 			return response()->json([
	// 				'success' => false,
	// 				'message' => 'Apple token does not contain a valid sub.',
	// 			], 422);
	// 		}

	// 		// Try to find existing user by email
	// 		$customer = Customer::where('email', $email)->first();

	// 		if (!$customer) {
	// 			// New customer registration
	// 			$dob = $request->input('dob');
	// 			$countryCode = $request->input('country_code');
	// 			$mobileNumber = $request->input('mobile_number');

	// 			$customer = Customer::create([
	// 				'apple_id' => $appleSub,
	// 				'email' => $email,
	// 				'name' => $name,
	// 				'password' => null, // ✅ no password
	// 				'is_social_login' => true,
	// 				'type' => 'Private',
	// 				'dob' => $dob,
	// 				'country_code' => $countryCode,
	// 				'mobile_number' => $mobileNumber,
	// 				'profile_img' => null,
	// 			]);
	// 		} else {
	// 			// Prevent login if email was registered via password (not social)
	// 			if ($customer->is_social_login === false) {
	// 				return response()->json([
	// 					'success' => false,
	// 					'message' => 'This email is already registered using email & password. Please login using your password.'
	// 				], 403);
	// 			}

	// 			// Update Apple ID if not already set
	// 			if (!$customer->apple_id) {
	// 				$customer->update(['apple_id' => $appleSub]);
	// 			}
	// 		}

	// 		// ✅ Token generation
	// 		$token = $customer->createToken('apple-login')->plainTextToken;

	// 		return response()->json([
	// 			'success' => true,
	// 			'message' => $customer->wasRecentlyCreated
	// 				? 'User registered successfully using Apple.'
	// 				: 'User logged in successfully with Apple.',
	// 			'token' => $token,
	// 			'user' => $customer,
	// 		]);
	// 	} catch (\Exception $e) {
	// 		\Log::error('Apple Login Failed: ' . $e->getMessage());

	// 		return response()->json([
	// 			'success' => false,
	// 			'message' => 'Apple login failed.',
	// 			'error' => $e->getMessage(),
	// 		], 401);
	// 	}
	// }

	
	
}
