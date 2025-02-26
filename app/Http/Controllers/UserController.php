<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use App\Http\Requests\UserRequest;
use Illuminate\Support\Facades\Hash;
use OpenApi\Annotations as OA;

class UserController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/users",
     *     summary="Get all users",
     *     tags={"Users"},
     *     @OA\Response(response=200, description="List of users")
     * )
     */
    public function index()
    {
        return response()->json(User::with('roles')->get());
    }

    /**
     * @OA\Post(
     *     path="/api/users",
     *     summary="Create a new user",
     *     tags={"Users"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password","role_id"},
     *             @OA\Property(property="email", type="string", example="test@example.com"),
     *             @OA\Property(property="password", type="string", example="password"),
     *             @OA\Property(property="first_name", type="string", example="John"),
     *             @OA\Property(property="last_name", type="string", example="Doe"),
     *             @OA\Property(property="role_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(response=201, description="User created"),
     *     @OA\Response(response=400, description="Validation error")
     * )
     */
    public function store(UserRequest $request)
    {
        $user = User::create([
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'first_name' => $request->first_name,
            'last_name' => $request->last_name
        ]);

        $user->roles()->attach($request->role_id);

        return response()->json($user->load('roles'), 201);
    }

    /**
     * @OA\Get(
     *     path="/api/users/{id}",
     *     summary="Get user details",
     *     tags={"Users"},
     *     @OA\Parameter(name="id", in="path", required=true, description="User ID", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="User details"),
     *     @OA\Response(response=404, description="User not found")
     * )
     */
    public function show($id)
    {
        $user = User::with('roles')->find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }
        return response()->json($user);
    }
    
    /**
 * @OA\Post(
 *     path="/api/users/{id}",
 *     summary="Update user",
 *     description="Updates user details including avatar, email, role, and password.",
 *     operationId="updateUser",
 *     tags={"Users"},
 *     security={{"bearerAuth":{}}},

 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="User ID",
 *         @OA\Schema(type="integer")
 *     ),

 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="multipart/form-data",
 *             @OA\Schema(
 *                 required={"_method", "email", "role_id", "first_name", "last_name"},
 *                 @OA\Property(property="_method", type="string", example="PUT"),
 *                 @OA\Property(property="email", type="string", format="email", example="user@example.com"),
 *                 @OA\Property(property="role_id", type="integer", example=2),
 *                 @OA\Property(property="first_name", type="string", example="John"),
 *                 @OA\Property(property="last_name", type="string", example="Doe"),
 *                 @OA\Property(property="avatar", type="string", format="binary"),
 *                 @OA\Property(property="old_password", type="string", example="oldpass123"),
 *                 @OA\Property(property="new_password", type="string", example="newpass123"),
 *                 @OA\Property(property="confirm_password", type="string", example="newpass123"),
 *             )
 *         )
 *     ),

 *     @OA\Response(
 *         response=200,
 *         description="User updated successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="User updated successfully")
 *         )
 *     ),
 *     @OA\Response(response=400, description="Invalid input"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=404, description="User not found")
 * )
 */


     public function update(UserRequest $request, $id)
     {
         // Find User
         $user = User::find($id);
         if (!$user) {
             return response()->json(['message' => 'User not found'], 404);
         }
 
         // Handle Password Update
         if ($request->filled('old_password') && $request->filled('new_password')) {
             if (!Hash::check($request->old_password, $user->password)) {
                 return response()->json(['message' => 'Old password is incorrect'], 400);
             }
             if ($request->new_password !== $request->confirm_password) {
                 return response()->json(['message' => 'New password and confirm password do not match'], 400);
             }
             $user->password = Hash::make($request->new_password);
         }
 
         // Update User Data
         $user->update($request->only('first_name', 'last_name', 'email'));
 
         // Update role if provided
         if ($request->has('role_id')) {
             $user->roles()->sync([$request->role_id]);
         }
 
         // Handle avatar upload
         if ($request->hasFile('avatar')) {
             $file = $request->file('avatar');
             $path = $file->store('avatars', 'public');
 
             // Create new media record
             $media = MediaFile::create([
                 'user_id'   => $user->id,
                 'name'      => $file->getClientOriginalName(),
                 'mime_type' => $file->getMimeType(),
                 'size'      => $file->getSize(),
                 'url'       => asset("storage/$path"),
             ]);
 
             // Update user's avatar_id
             $user->avatar_id = $media->id;
         }
 
         $user->save();
 
         return response()->json([
             'message'    => 'User updated successfully',
             'user'       => $user->load('roles', 'avatar'),
             'avatar_url' => $user->avatar ? $user->avatar->url : null,
         ]);
     }

    /**
     * @OA\Delete(
     *     path="/api/users/{id}",
     *     summary="Delete a user",
     *     tags={"Users"},
     *     @OA\Parameter(name="id", in="path", required=true, description="User ID", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="User deleted"),
     *     @OA\Response(response=404, description="User not found")
     * )
     */
    public function destroy($id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $user->delete();
        return response()->json(['message' => 'User deleted']);
    }
}
