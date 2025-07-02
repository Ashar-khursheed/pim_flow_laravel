<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\FrontEnd\Customer;
use Google_Client;
use App\Notifications\GuestWelcomeMail;
use App\Notifications\WelcomeMail;
use Illuminate\Support\Str;

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
			'email' => 'required|email',
			'password' => 'required',
		]);

		$customer = Customer::where('email', $request->email)->first();

		if (!$customer || !Hash::check($request->password, $customer->password)) {
			return response()->json([
				'success' => false,
				'message' => 'The provided credentials are incorrect.'
			], 401);
		}

		$token = $customer->createToken('auth_token')->plainTextToken;

		return response()->json([
			'message' => 'Login successful',
			'customer' => $customer,
			'token' => $token
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

	public function login(Request $request)
	{
		$idToken = $request->input('credential');
	
		$client = new Google_Client(['client_id' => '96165540519-5abr44463l214dog6teceibk8nmqlfm1.apps.googleusercontent.com']);
		$payload = $client->verifyIdToken($idToken);
	
		if ($payload) {
			$email = $payload['email'];
			$name = $payload['name'];
			$profileImg = $payload['picture'];
	
			$user = Customer::where('email', $email)->first();
	
			if (!$user) {
				$randomPassword = Str::random(8);
				$hashedPassword = Hash::make($randomPassword);
	
				$user = Customer::create([
					'name' => $name,
					'email' => $email,
					'profile_img' => $profileImg,
					'password' => $hashedPassword,
					'type' => 'Private',
					'mobile_number' => null,
				]);
	
				$user->notify(new GuestWelcomeMail($randomPassword));
			}
	
			// ✅ Don't call Auth::login — return Sanctum token instead
			return response()->json([
				'message' => 'Logged in successfully',
				'user' => $user,
				'token' => $user->createToken('google-auth')->plainTextToken,
			]);
		} else {
			return response()->json(['error' => 'Invalid ID token'], 401);
		}
	}
	
}
