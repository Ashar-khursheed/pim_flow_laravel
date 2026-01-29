<?php
namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Models\FrontEnd\ContactDirectory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ContactDirectoryController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/frontend/contact-directories",
     *     tags={"Frontend ContactDirectory"},
     *     security={{"bearerAuth":{}}},
     *     summary="Get all contact directories",
     *     @OA\Parameter(
     *         name="customer_id",
     *         in="query",
     *         description="Filter by customer ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Success")
     * )
     */
    public function index(Request $request)
    {
        $query = ContactDirectory::query();

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        return response()->json($query->get());
    }

    /**
     * @OA\Post(
     *     path="/api/frontend/contact-directories",
     *     tags={"Frontend ContactDirectory"},
     *     security={{"bearerAuth":{}}},
     *     summary="Create a new contact",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"customer_id", "name"},
     *                 @OA\Property(property="customer_id", type="integer", example=101),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *                 @OA\Property(property="phone", type="string", example="+1234567890"),
     *                 @OA\Property(property="type", type="string", example="friend"),
     *                 @OA\Property(property="image", type="string", format="binary", description="Image file")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Created"),
     *     @OA\Response(response=422, description="Validation failed")
     * )
     */

   public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|integer',
            'name'        => 'required|string|max:255',
            'email'       => 'nullable|email:strict',
            'phone'       => 'nullable|string|max:20',
            'image'       => 'nullable|file|image|max:2048', // max 2MB
            'type'        => 'nullable|string|max:50',
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('customers/directory', env('STORAGE_ENV', 's3'));
            $validated['image'] = Storage::disk(env('STORAGE_ENV', 's3'))->url($path);
        }

        $contact = ContactDirectory::create($validated);

        return response()->json($contact, 201);
    }

    /**
     * @OA\Get(
     *     path="/api/frontend/contact-directories/{id}",
     *     tags={"Frontend ContactDirectory"},
     * 	   security={{"bearerAuth":{}}},
     *     summary="Get a contact by ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=404, description="Not Found")
     * )
     */
    public function show($id)
    {
        $contact = ContactDirectory::findOrFail($id);
        return response()->json($contact);
    }

    /**
     * @OA\Put(
     *     path="/api/frontend/contact-directories/{id}",
     *     tags={"Frontend ContactDirectory"},
     *     security={{"bearerAuth":{}}},
     *     summary="Update a contact",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Contact directory ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *                 @OA\Property(property="phone", type="string", example="+123456789"),
     *                 @OA\Property(property="type", type="string", example="friend"),
     *                 @OA\Property(property="image", type="string", format="binary", description="Image file")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Updated"),
     *     @OA\Response(response=404, description="Not Found"),
     *     @OA\Response(response=422, description="Validation Error")
     * )
     */

  public function update(Request $request, $id)
    {
        $contact = ContactDirectory::findOrFail($id);

        $validated = $request->validate([
            'name'  => 'sometimes|required|string|max:255',
            'email' => 'nullable|email:strict',
            'phone' => 'nullable|string|max:20',
            'image' => 'nullable|file|image|max:2048',
            'type'  => 'nullable|string|max:50',
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('customers/directory', env('STORAGE_ENV', 's3'));
            $validated['image'] = Storage::disk(env('STORAGE_ENV', 's3'))->url($path);
        }

        $contact->update($validated);

        return response()->json($contact);
    }


    /**
     * @OA\Delete(
     *     path="/api/frontend/contact-directories/{id}",
     *     tags={"Frontend ContactDirectory"},
     * 	   security={{"bearerAuth":{}}},
     *     summary="Delete a contact",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=204, description="Deleted"),
     *     @OA\Response(response=404, description="Not Found")
     * )
     */
    public function destroy($id)
    {
        $contact = ContactDirectory::findOrFail($id);
        $contact->delete();

        return response()->json(null, 204);
    }
}
