<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use App\Models\FrontEnd\Coupon;

use Illuminate\Support\Facades\Bus;
use Illuminate\Bus\Batch;

use App\Models\User;
use App\Models\FrontEnd\Customer;
use App\Jobs\Auth\CommonPasswordResetMailJob;
use App\Jobs\Auth\ResetPasswordMailJob;

class AuthController extends BaseController
{
	/**
	 * @OA\Post(
	 *     path="/api/login",
	 *     summary="User Login",
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

		$user = User::where('email', $request->email)->first();

		if (!$user || !Hash::check($request->password, $user->password)) {
			return response()->json([
				'success' => false,
				'message' => 'The provided credentials are incorrect.'
			], 401);
		}
		$token = $user->createToken('auth_token')->plainTextToken;

		return response()->json([
			'message' => 'Login successful',
			'user' => $user,
			'token' => $token
		]);
	}

	/**
	 * @OA\Get(
	 *     path="/api/auth/has-permission",
	 *     summary="Check if the authenticated user has a specific permission",
	 *     description="Returns true or false based on whether the user has the given permission.",
	 *     tags={"Auth"},
	 *     @OA\Parameter(
	 *         name="permission",
	 *         in="query",
	 *         description="The name of the permission to check (e.g., 'brand.create')",
	 *         required=true,
	 *         @OA\Schema(type="string")
	 *     ),
	 *     @OA\Response(response=200, description="Permission check result", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function hasPermission(Request $request)
	{
		$permission = $request->query('permission');

		if (!$permission) {
			return response()->json([
				'success' => false,
				'message' => 'Permission parameter is required.',
			], 400);
		}

		$user = Auth::user();
		$hasPermission = $user->can($permission);

		return response()->json([
			'success' => true,
			'has_permission' => $hasPermission,
			'message' => $hasPermission
				? "User has the '{$permission}' permission."
				: "User does not have the '{$permission}' permission.",
		]);
	}

	/**
	 * @OA\Get(
	 *     path="/api/auth/permissions",
	 *     summary="Get all permission names for the authenticated user",
	 *     description="Returns a list of permission names granted to the currently authenticated user.",
	 *     tags={"Auth"},
	 *     @OA\Response(response=200, description="List of permissions", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function getAllPermissions(Request $request)
	{
		$user = Auth::user();

		$permissions = $user->getAllPermissions()->pluck('name');

		return response()->json([
			'success' => true,
			'permissions' => $permissions,
			'message' => 'Permissions fetched successfully.',
		]);
	}

	/**
	 * @OA\Post(
	 *     path="/api/logout",
	 *     summary="User Logout",
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
			$user->currentAccessToken()->delete();
		}

		return response()->json([
			'success' => true,
			'message' => 'Logout successful'
		]);
	}

	/**
	 * @OA\Post(
	 *     path="/api/auth/forgot-password",
	 *     summary="Send password reset link",
	 *     tags={"Auth"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             @OA\Property(property="email", type="string"),
	 *             @OA\Property(property="type", type="string", enum={"user", "customer"})
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Reset link sent", @OA\MediaType(mediaType="application/json")),
	 * )
	 */
	public function sendResetLinkEmail(Request $request)
	{
		$request->validate([
			'email' => 'required|email:strict',
			'type' => 'required|in:user,customer'
		]);

		$model = $request->type === 'customer' ? Customer::class : User::class;

		$user = $model::where('email', $request->email)->first();

		if (!$user) {
			return response()->json([
				'success' => false,
				'message' => "❌ No account found with this email. Please try again or create a new account."
			]);
		}

		$batch = Bus::batch([])->name('Reset Password Mail from auth controller')->dispatch();
		$batch->options['queue'] = config('app.website') . '_RST_PWD';
		$batch->add(new ResetPasswordMailJob([
			'recordId' => $user->id,
			'userType' => $request->type,
		]));

		return response()->json([
			'success' => true,
			'message' => 'Reset link sent to your email.'
		]);
	}

	/**
	 * @OA\Post(
	 *     path="/api/auth/reset-password",
	 *     summary="Reset password using token",
	 *     tags={"Auth"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             @OA\Property(property="email", type="string"),
	 *             @OA\Property(property="password", type="string"),
	 *             @OA\Property(property="password_confirmation", type="string"),
	 *             @OA\Property(property="token", type="string"),
	 *             @OA\Property(property="type", type="string", enum={"user", "customer"})
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Password has been reset")
	 * )
	 */
	public function resetPassword(Request $request)
	{
		$request->validate([
			'email' => 'required|email:strict',
			'password' => 'required|confirmed|min:6',
			'token' => 'required',
			'type' => 'required|in:user,customer',
		]);

		$model = $request->type === 'customer' ? Customer::class : User::class;

		$user = $model::where('email', $request->email)->first();

		if (!$user || !$user->passwordResetToken) {
			return response()->json([
				'success' => false,
				'message' => 'The provided email or reset token is invalid.'
			]);
		}

		if (!Hash::check($request->token, $user->passwordResetToken->token)) {
			return response()->json([
				'success' => false,
				'message' => 'Your reset token is incorrect or has expired. Please request a new one.'
			]);
		}

		if (now()->diffInMinutes($user->passwordResetToken->created_at) > 10) {
			return response()->json([
				'success' => false,
				'message' => 'Your reset token has expired. Please request a new one from the "Forgot Password" section.'
			]);
		}

		$user->password = Hash::make($request->password);
		$user->save();

		$user->passwordResetToken->delete();

		$user->tokens()->delete();

		return response()->json([
			'success' => true,
			'message' => 'Password has been reset.'
		]);
	}

	/**
	 * @OA\Get(
	 *     path="/api/auth/send-customers-reset-link",
	 *     summary="Send password reset links to all customers",
	 *     description="Generates and emails password reset links to all registered customers.",
	 *     tags={"Auth"},
	 *     @OA\Response(response=200, description="Reset link sent", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}},
	 * )
	 */
	public function sendAllCustomersResetLinkEmail()
	{
		try {
			$page = 1;
			$limit = 5000;
			$offset = ($page - 1) * $limit;
			$customers = Customer::where('id', '<', 1)
				->whereNull('password')
				->orderBy('id', 'desc')
				->offset($offset)
				->limit($limit)
				->get();

			/* Check if any customers found */
			if ($customers->isEmpty()) {
				return response()->json([
					'success' => false,
					'message' => 'No customers found to send reset links.'
				], 404);
			}

			/* Create jobs array first to avoid multiple batch creation */
			$jobs = [];
			foreach ($customers as $customer) {
				$jobs[] = new CommonPasswordResetMailJob([
					'recordId' => $customer->id
				]);
			}

			/* Create single batch with all jobs and proper queue configuration */
			$batch = Bus::batch($jobs)
				->name('Common Password Mail')
				->onQueue(config('app.website') . '_COMM_PWD')
				->dispatch();

			return response()->json([
				'success' => true,
				'message' => 'Reset link batch created successfully.',
				'batch_id' => $batch->id,
				'total_jobs' => count($jobs)
			], 200);

		} catch (\Exception $e) {
			/* Log error details */
			Log::error('Error sending reset links to customers: ' . $e->getMessage());
			return response()->json([
				'success' => false,
				'message' => 'Failed to send reset links.',
				'error' => $e->getMessage()
			], 500);
		}
	}
}
