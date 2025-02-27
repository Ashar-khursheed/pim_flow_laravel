<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\MediaFile;
use Illuminate\Http\Request;
use App\Http\Requests\UserRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use OpenApi\Annotations as OA;
use Illuminate\Support\Facades\Log;


class UserController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/users",
     *     summary="Get all users",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
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
 *     summary="Update a user",
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
 *                 required={"email", "first_name", "last_name"},
 *                 @OA\Property(property="_method", type="string", example="PUT"),
 *                 @OA\Property(property="email", type="string", format="email", example="user@example.com"),
 *                 @OA\Property(property="first_name", type="string", example="John"),
 *                 @OA\Property(property="last_name", type="string", example="Doe"),
 *                 @OA\Property(property="avatar", type="string", format="binary"),
 *                 @OA\Property(property="old_password", type="string", example="oldpassword"),
 *                 @OA\Property(property="new_password", type="string", example="newpassword"),
 *                 @OA\Property(property="confirm_password", type="string", example="newpassword"),
 *                 @OA\Property(property="role_id", type="integer", example=10)
 *             )
 *         )
 *     ),
 *     @OA\Response(response=200, description="User updated successfully"),
 *     @OA\Response(response=400, description="Validation error"),
 *     @OA\Response(response=401, description="Unauthorized"),
 *     @OA\Response(response=404, description="User not found"),
 *     @OA\Response(response=405, description="Method Not Allowed"),
 *     @OA\Response(response=500, description="Internal server error")
 * )
 */



    public function update(Request $request, $id)
{

    if ($request->isMethod('post') && $request->input('_method') === 'PUT') {
        $request->request->remove('_method'); // Remove _method from request
    }
    \Log::info('Update method started for user ID: ' . $id);

    // Debugging: Dump all request data
    \Log::info('Raw Input:', [file_get_contents('php://input')]);
    \Log::info('Request data: ', $request->all());

    try {
        // Manually extract inputs for debugging
        $email = $request->input('email');
        $first_name = $request->input('first_name');
        $last_name = $request->input('last_name');

        if (!$email || !$first_name || !$last_name) {
            \Log::error('Missing required fields: ', compact('email', 'first_name', 'last_name'));
            return response()->json(["message" => "Missing required fields"], 400);
        }

        $request->validate([
            'email' => 'required|email|unique:users,email,' . $id,
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'avatar' => 'nullable|file|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'old_password' => 'nullable|string',
            'new_password' => 'nullable|string|min:6|required_with:old_password',
            'confirm_password' => 'nullable|string|same:new_password',
            'role_id' => 'nullable|integer|exists:roles,id',
        ]);

        \Log::info('Validation passed');

        $user = User::findOrFail($id);
        \Log::info('User found: ' . $user->email);

        // Update user details
        $user->email = $email;
        $user->first_name = $first_name;
        $user->last_name = $last_name;

        // Handle password update
        if ($request->filled('old_password')) {
            \Log::info('Password update requested');
            if (!Hash::check($request->old_password, $user->password)) {
                \Log::info('Old password incorrect');
                return response()->json(["message" => "Old password is incorrect"], 400);
            }
            $user->password = Hash::make($request->new_password);
            \Log::info('Password updated');
        }

        if ($request->hasFile('avatar')) {
            \Log::info('✅ Avatar file detected. Processing upload...');
            
            try {
                $avatarFile = $request->file('avatar');
        
                // Ensure file is valid before proceeding
                if (!$avatarFile->isValid()) {
                    throw new \Exception('Invalid file upload');
                }
        
                // Store the file
                $path = $avatarFile->store('avatars', 'public');
                \Log::info('✅ Avatar stored at: ' . $path);
        
                // Save to media_files table
                $mediaFile = new MediaFile();
                $mediaFile->user_id = $user->id;
                $mediaFile->name = $avatarFile->getClientOriginalName();
                $mediaFile->mime_type = $avatarFile->getMimeType();
                $mediaFile->size = $avatarFile->getSize();
                $mediaFile->url = 'storage/' . $path;
                $mediaFile->folder_id = 0;
                $mediaFile->visibility = 'public';
                $mediaFile->save();
        
                \Log::info('✅ Media file created with ID: ' . $mediaFile->id);
        
                // Update user's avatar_id
                $user->avatar_id = $mediaFile->id;
                $user->save();
        
                \Log::info('✅ User avatar_id updated to ' . $user->avatar_id);
            } catch (\Exception $e) {
                \Log::error('❌ Avatar upload failed: ' . $e->getMessage());
            }
        } else {
            \Log::error('❌ Avatar file is missing in request');
        }
        
        


        // Handle role update
        if ($request->filled('role_id')) {
            \Log::info('Role update requested to: ' . $request->role_id);
            try {
                $user->roles()->sync([$request->role_id]);
                \Log::info('Role updated');
            } catch (\Exception $e) {
                \Log::error('Role update failed: ' . $e->getMessage());
            }
        }

        $user->save();
        \Log::info('User saved');

        // Load relationships for the response
        $user->load(['avatar', 'roles']);
        \Log::info('Relationships loaded');

        return response()->json([
            "message" => "User updated successfully",
            "user" => $user->toArray()
        ], 200);
    } catch (\Exception $e) {
        \Log::error('Exception in update method: ' . $e->getMessage());
        return response()->json([
            'message' => 'An error occurred while updating the user',
            'error' => $e->getMessage()
        ], 500);
    }
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
