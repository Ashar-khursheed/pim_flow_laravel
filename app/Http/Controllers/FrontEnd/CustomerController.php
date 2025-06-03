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
}
