<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RoleController extends Controller
{
	/**
	 * @OA\Get(
	 *     path="/api/roles",
	 *     summary="Get list of roles",
	 *     tags={"Roles and Permissions"},
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
		if (!auth()->user()->can('list role')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}

		$records = Role::with('permissions:id,name');

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

		$records->each(function ($role) {
			$role->permissions->each->makeHidden(['pivot']);
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
	 *     path="/api/roles",
	 *     summary="Create a new role",
	 *     description="Creates a new role.",
	 *     tags={"Roles and Permissions"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"name", "permissions"},
	 *             @OA\Property(property="name", type="string", example="Admin"),
	 *             @OA\Property(
	 *                 property="permissions",
	 *                 type="array",
	 *                 description="Array of permissions",
	 *                 @OA\Items(type="string", example="create record")
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=201, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function store(Request $request)
	{
		/* Validate request data */
		$request->validate([
			'name' => "required|unique:roles,name",
			'permissions' => 'required|array',
			'permissions.*' => 'exists:permissions,name',
		]);

		$role = new Role();
		$role->name = $request->name;
		$role->created_at = now();
		$role->updated_at = now();
		$role->save();

		$role->syncPermissions($request->permissions);
		$role = Role::with('permissions:id,name')->find($role->id);

		$role->permissions->each->makeHidden(['pivot']);

		return response()->json([
			'success' => true,
			'message' => __("msg_create"),
			'data' => $role
		]);
	}

	/**
	 * @OA\Get(
	 *     path="/api/roles/{role_id}",
	 *     summary="Get role details",
	 *     description="Fetches role details based on the given role ID.",
	 *     tags={"Roles and Permissions"},
	 *     @OA\Parameter(
	 *         name="role_id",
	 *         in="path",
	 *         required=true,
	 *         description="ID of the role",
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function show($roleId)
	{
		$role = Role::with('permissions:id,name')->find($roleId);
		if (!$role) {
			return response()->json([
				'success' => false,
				'message' => __("err_exist")
			]);
		}
		$role->permissions->each->makeHidden(['pivot']);

		return response()->json([
			'success' => true,
			'message' => __("msg_rec_dtl"),
			'data' => $role
		]);
	}

	/**
	 * @OA\Put(
	 *     path="/api/roles/{id}",
	 *     summary="Update an existing role",
	 *     description="Updates an role's details",
	 *     tags={"Roles and Permissions"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         required=true,
	 *         description="ID of the role to update",
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"name", "permissions"},
	 *             @OA\Property(property="name", type="string", example="Admin"),
	 *             @OA\Property(
	 *                 property="permissions",
	 *                 type="array",
	 *                 description="Array of permissions",
	 *                 @OA\Items(type="string", example="create record")
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function update(Request $request, $roleId)
	{
		$role = Role::find($roleId);
		if (!$role) {
			return response()->json([
				'success' => false,
				'message' => __("err_exist")
			]);
		}

		/* Validate request data */
		$request->validate([
			'name' => "required|unique:roles,name,{$roleId}",
			'permissions' => 'required|array',
			'permissions.*' => 'exists:permissions,name',
		]);

		$input = $request->all();
		DB::beginTransaction();
		try {
			/* Save the role */
			$role->syncPermissions($input['permissions'])->update(['name' => $input['name']]);

			$role = Role::with('permissions:id,name')->find($roleId);

			$role->permissions->each->makeHidden(['pivot']);

			DB::commit();

			/* Return success response */
			return response()->json([
				'success' => true,
				'message' => __("msg_update"),
				'data' => $role
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
	 *     path="/api/roles/{id}",
	 *     summary="Delete a role",
	 *     description="Deletes a role.",
	 *     operationId="deleteRole",
	 *     tags={"Roles and Permissions"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         description="ID of the role to delete",
	 *         required=true,
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function destroy($id)
	{
		$role = Role::find($id);

		if (!$role) {
			return response()->json([
				'success' => false,
				'message' => __("err_exist")
			], 404);
		}

		/* Check if role is attached to any role group */
		if ($role->users()->count()) {
			return response()->json([
				'success' => false,
				'message' => __("err_role_association")
			], 400);
		}

		/* Proceed with deletion */
		$role->delete();

		return response()->json([
			'success' => true,
			'message' => __("msg_dlt")
		], 200);
	}
}
