<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\FrontEnd\Customer;
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

	public function store(Request $request)
	{
	    $request->validate([
	        'email' => 'required|email:strict',
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
	   if ($customer->is_social_login && !$customer->password) {
   		 return response()->json([
				'success' => false,
			'message' => 'This email is linked with a social login (Google/Apple). Please log in using that method.',
		], 403);
	}


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
}
