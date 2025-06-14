<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Models\MediaFile;
use Illuminate\Http\Request;
use App\Http\Requests\UserRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use OpenApi\Annotations as OA;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;


class UserController extends BaseController
{
	/**
	 * @OA\Get(
	 *     path="/api/users",
	 *     summary="Get list of users",
	 *     tags={"Users"},
	 *     @OA\Parameter(
	 *         name="page",
	 *         in="query",
	 *         description="Page number for pagination. Starts from 1.",
	 *         required=true,
	 *         example=1,
	 *         @OA\Schema(
	 *             type="integer",
	 *             minimum=1
	 *         )
	 *     ),
	 *     @OA\Parameter(
	 *         name="length",
	 *         in="query",
	 *         description="Number of records per page.",
	 *         required=true,
	 *         example=20,
	 *         @OA\Schema(
	 *             type="integer",
	 *             minimum=1
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function index(Request $request)
	{
		if (!auth()->user()->can('list user')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}
		$records = User::with('roles:id,name');

		/* Pagination */
		if ($request->filled('page') && $request->filled('length')) {
			$page = (int) $request->input('page');
			$length = (int) $request->input('length');
			$totalRecords = $records->count();
			$totalPages = ceil($totalRecords / $length);

			$records = $records->offset(($page - 1) * $length)->limit($length)->get();
		} else {
			$records = $records->get();
			$totalRecords = $records->count();
		}

		$records->each(function ($user) {
			$role = $user->roles->first();
			if ($role) {
				$user->role_id = $role->id;
				$user->role_name = $role->name;
			}
			$user->makeHidden('roles');
		});

		return response()->json([
			'success' => true,
			'message' => __("msg_rec_list"),
			'data' => $records,
			'total_pages' => $totalPages ?? 1,
			'total_records' => $totalRecords,
		]);
	}

	/**
	 * @OA\Post(
	 *     path="/api/users",
	 *     summary="Create a new user",
	 *     description="Creates a new user.",
	 *     tags={"Users"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         description="User creation payload",
	 *         @OA\JsonContent(
	 *             type="object",
	 *             required={"email", "username", "password", "role"},
	 *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
	 *             @OA\Property(property="username", type="string", example="johndoe"),
	 *             @OA\Property(property="password", type="string", format="password", example="securePass123"),
	 *             @OA\Property(property="first_name", type="string", example="John"),
	 *             @OA\Property(property="last_name", type="string", example="Doe"),
	 *             @OA\Property(property="role", type="string", example="admin")
	 *         )
	 *     ),
	 *     @OA\Response(response=201, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function store(Request $request)
	{
		if (!auth()->user()->can('add user')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}
		$validatedData = $request->validate([
			'username'    => 'required|string|max:255|unique:users,username',
			'email'       => 'required|string|email|max:255|unique:users,email',
			'password'    => 'required|string|min:8',
			'first_name'  => 'required|string|max:255',
			'last_name'   => 'required|string|max:255',
			'role'        => 'required|string|exists:roles,name',
		]);

		$user = User::create([
			'username'   => $validatedData['username'],
			'email'      => $validatedData['email'],
			'password'   => Hash::make($validatedData['password']),
			'first_name' => $validatedData['first_name'] ?? null,
			'last_name'  => $validatedData['last_name'] ?? null,
		]);

		$user->syncRoles([$validatedData['role']]);

		return response()->json([
			'success' => true,
			'message' => 'User created successfully.',
			'data'    => $user->load('roles'),
		], 201);
	}

	/**
	 * @OA\Get(
	 *     path="/api/users/{user_id}",
	 *     summary="Get user details",
	 *     description="Fetches user details based on the given user ID.",
	 *     tags={"Users"},
	 *     @OA\Parameter(
	 *         name="user_id",
	 *         in="path",
	 *         required=true,
	 *         description="ID of the user",
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function show($userId)
	{
		if (!auth()->user()->can('show user')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}
		$user = User::find($userId);
		if (!$user) {
			return response()->json([
				'success' => false,
				'message' => __("err_exist")
			]);
		}

		$role = $user->roles->first();
		if ($role) {
			$user->role_id = $role->id;
			$user->role_name = $role->name;
		}

		return response()->json([
			'success' => true,
			'message' => __("msg_rec_dtl"),
			'data' => $user
		]);
	}

	/**
	 * @OA\Put(
	 *     path="/api/users/{id}",
	 *     summary="Update an existing user",
	 *     description="Updates an user's details",
	 *     tags={"Users"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         required=true,
	 *         description="ID of the user to update",
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\RequestBody(
	 *         required=true,
	 *         description="User creation payload",
	 *         @OA\JsonContent(
	 *             type="object",
	 *             required={"email", "username", "password", "role"},
	 *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
	 *             @OA\Property(property="username", type="string", example="johndoe"),
	 *             @OA\Property(property="password", type="string", format="password", example="securePass123"),
	 *             @OA\Property(property="first_name", type="string", example="John"),
	 *             @OA\Property(property="last_name", type="string", example="Doe"),
	 *             @OA\Property(property="role", type="string", example="admin")
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function update(Request $request, $userId)
	{
		if (!auth()->user()->can('update user')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}

		$user = User::find($userId);
		if (!$user) {
			return response()->json([
				'success' => false,
				'message' => __("err_exist")
			]);
		}

		/* Validate request data */
		$validatedData = $request->validate([
			'username'    => 'required|string|max:255|unique:users,username,' . $userId,
			'email'       => 'required|string|email|max:255|unique:users,email,' . $userId,
			'password'    => 'nullable|string|min:8',
			'first_name'  => 'required|string|max:255',
			'last_name'   => 'required|string|max:255',
			'role'        => 'required|string|exists:roles,name',
		]);

		DB::beginTransaction();
		try {
			/* Check if password is being updated */
			if (!empty($validatedData['password'])) {
				/* Revoke all existing tokens */
				$user->tokens()->delete();
				$validatedData['password'] = Hash::make($validatedData['password']);
			} else {
				$validatedData['password'] = $user->password;
			}
			/* Save the user */
			$user->syncRoles($validatedData['role'])->update([
				'username' => $validatedData['username'],
				'email' => $validatedData['email'],
				'password' => $validatedData['password'],
				'first_name' => $validatedData['first_name'],
				'last_name' => $validatedData['last_name'],
			]);

			$role = $user->roles->first();
			if ($role) {
				$user->role_id = $role->id;
				$user->role_name = $role->name;
			}

			DB::commit();

			/* Return success response */
			return response()->json([
				'success' => true,
				'message' => __("msg_update"),
				'data' => $user
			]);
		} catch (\Exception $e) {
			DB::rollBack();

			return response()->json([
				'success' => false,
				'message' => __("err_update"),
				'error' => $e->getMessage()
			], 500);
		}
	}

	/**
	 * @OA\Delete(
	 *     path="/api/users/{id}",
	 *     summary="Delete a user",
	 *     description="Deletes a user.",
	 *     operationId="deleteUser",
	 *     tags={"Users"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         description="ID of the user to delete",
	 *         required=true,
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function destroy($id)
	{
		if (!auth()->user()->can('delete user')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}
		$user = User::find($id);

		if (!$user) {
			return response()->json([
				'success' => false,
				'message' => __("err_exist")
			], 404);
		}

		/* Proceed with deletion */
		$user->delete();

		return response()->json([
			'success' => true,
			'message' => __("msg_dlt")
		], 200);
	}
}
