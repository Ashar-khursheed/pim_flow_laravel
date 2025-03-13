<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;


/**
 * @OA\Tag(name="Roles", description="API Endpoints for managing roles")
 */
class RoleController extends Controller
{
        /**
         * @OA\Get(
         *     path="/api/roles",
         *     summary="Get list of roles",
         *     tags={"Roles"},
         *     security={{"bearerAuth":{}}},
         *     @OA\Response(
         *         response=200,
         *         description="List of roles",
         *         @OA\JsonContent(
         *             type="array",
         *             @OA\Items(ref="#/components/schemas/Role")
         *         )
         *     )
         * )
         */
        public function index()
        {
            return response()->json(Role::all(), 200);
        }

    

    /**
     * @OA\Post(
     *     path="/api/roles",
     *     summary="Create a new role",
     *     description="Create a new role with given details",
     *     tags={"Roles"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"slug", "name"},
     *             @OA\Property(property="slug", type="string", example="admin"),
     *             @OA\Property(property="name", type="string", example="Administrator"),
     *             @OA\Property(property="permissions", type="array", @OA\Items(type="string"), example={"manage_users", "edit_posts"}),
     *             @OA\Property(property="description", type="string", example="Full access to all functionalities"),
     *             @OA\Property(property="is_default", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Role created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Role")
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'slug' => 'required|string|unique:roles,slug',
            'name' => 'required|string',
            'permissions' => 'nullable|array',
            'description' => 'nullable|string',
            'is_default' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $role = Role::create($request->all());

        return response()->json($role, 201);
    }

    /**
     * @OA\Get(
     *     path="/api/roles/{id}",
     *     summary="Get role details",
     *     description="Retrieve details of a specific role by ID",
     *     tags={"Roles"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Role ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Role details",
     *         @OA\JsonContent(ref="#/components/schemas/Role")
     *     )
     * )
     */
    public function show(Role $role)
    {
        return response()->json($role, 200);
    }

    /**
     * @OA\Put(
     *     path="/api/roles/{id}",
     *     summary="Update a role",
     *     description="Update an existing role by ID",
     *     tags={"Roles"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Role ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Super Admin"),
     *             @OA\Property(property="permissions", type="array", @OA\Items(type="string"), example={"manage_users", "edit_posts", "delete_posts"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Role updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Role")
     *     )
     * )
     */
    public function update(Request $request, Role $role)
    {
        $role->update($request->all());
        return response()->json($role, 200);
    }

    /**
     * @OA\Delete(
     *     path="/api/roles/{id}",
     *     summary="Delete a role",
     *     description="Remove an existing role by ID",
     *     tags={"Roles"},
     *    security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Role ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Role deleted successfully",
     *         @OA\JsonContent(type="object", @OA\Property(property="message", type="string", example="Role deleted successfully"))
     *     )
     * )
     */
    public function destroy(Role $role)
    {
        $role->delete();
        return response()->json(['message' => 'Role deleted successfully'], 200);
    }


        /**
     * @OA\Get(
     *     path="/api/roles/names",
     *     summary="Get list of roles with only ID and Name",
     *     tags={"Roles"},
     *     security={{"bearerAuth":{}}}, 
     *     @OA\Response(
     *         response=200,
     *         description="List of roles with only ID and Name",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Admin")
     *             )
     *         )
     *     )
     * )
     */
    public function getRoleNames()
    {
        return response()->json(Role::select('id', 'name')->get(), 200);
    }



        /**
     * @OA\Get(
     *     path="/api/roles/{id}/permissions",
     *     summary="Get role permissions",
     *     description="Retrieve a structured list of permissions for a specific role.",
     *     tags={"Roles"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Role ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Role permissions fetched successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="permissions",
     *                 type="object",
     *                 example={
     *                     "ads": {
     *                         "create": true,
     *                         "edit": false,
     *                         "delete": true,
     *                         "update": true
     *                     },
     *                     "posts": {
     *                         "create": true,
     *                         "edit": true,
     *                         "delete": false,
     *                         "update": true
     *                     }
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Role not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Role not found")
     *         )
     *     )
     * )
     */

     public function getRolePermissions(Role $role)
{
    // Ensure permissions exist and are an array
    if (!is_array($role->permissions)) {
        return response()->json(['message' => 'No permissions found for this role.'], 404);
    }

    $permissions = [];

    // Iterate through permissions (keys are permission names)
    foreach ($role->permissions as $permissionName => $allowed) {
        if (!$allowed) {
            continue; // Skip if permission is set to false
        }

        // Extract module and action (e.g., "ads.create" → ["ads", "create"])
        [$module, $action] = explode('.', $permissionName);

        // Ensure module exists in the result array
        if (!isset($permissions[$module])) {
            $permissions[$module] = [
                'index' => false,
                'create' => false,
                'edit' => false,
                'destroy' => false,
                'cms' => false
            ];
        }

        $permissions[$module][$action] = true; // Set the correct action to true
    }

    return response()->json(['permissions' => $permissions], 200);
}

     



}
