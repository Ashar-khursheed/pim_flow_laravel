<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

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
			'email' => 'required|email',
			'password' => 'required',
		]);
		// dd('called1');

		$user = User::where('email', $request->email)->first();

		if (!$user || !Hash::check($request->password, $user->password)) {
			return response()->json(['error' => 'The provided credentials are incorrect.'], 401);
		}

		// Auth::login($user);

		// $user = Auth::user();
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
			'message' => 'Logout successful'
		]);
	}
}
