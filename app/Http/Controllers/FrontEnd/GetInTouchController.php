<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Models\FrontEnd\GetInTouch;
use Illuminate\Http\Request;

class GetInTouchController extends Controller
{
	/**
	 * Display a listing of the resource.
	 */
	public function index()
	{
		//
	}

	/**
	 * @OA\Post(
	 *     path="/api/frontend/get-in-touch",
	 *     summary="Save customer message",
	 *     tags={"FrontEnd-GetInTouch"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="multipart/form-data",
	 *             @OA\Schema(
	 *                 required={"name", "email", "phone", "topic"},
	 *                 @OA\Property(property="name", type="string", example="John Doe"),
	 *                 @OA\Property(property="email", type="string", format="email", example="john@example.com"),
	 *                 @OA\Property(property="phone", type="string", example="971500000000"),
	 *                 @OA\Property(property="topic", type="string", example="Regarding my order"),
	 *                 @OA\Property(property="order_number", type="string", example="1001"),
	 *                 @OA\Property(property="image", type="file", description="Image (jpeg, png, webp only, max 2 MB)"),
	 *                 @OA\Property(property="message", type="string", example="Type your description"),
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=201, description="Saved successfully", @OA\MediaType(mediaType="application/json")),
	 * )
	 */
	public function store(Request $request)
{
    /* Validate request data */
    $validated = $request->validate([
        'name'          => 'required|string|max:255',
        'email'         => 'required|email:strict|max:255',
        'phone'         => 'required|string|max:20',
        'topic'         => 'required|string|max:255',
        'order_number'  => 'nullable|string|max:50',
        'message'       => 'nullable|string',
        'image'         => 'nullable|file|mimes:jpeg,jpg,png,webp,pdf|max:2048', // 2MB
    ]);

    $imageUrl = null;

    /* Handle image upload if present */
    if ($request->hasFile('image')) {
        $imageUrl = uploadImageToWebpS3FromFile(
            $request,
            'image',
            env('STORAGE_ENV') . '/get_in_touch'
        );
    }

    /* Save record */
    $getInTouch = GetInTouch::create([
        'name'         => $validated['name'],
        'email'        => $validated['email'],
        'phone'        => $validated['phone'],
        'topic'        => $validated['topic'],
        'order_number' => $validated['order_number'] ?? null,
        'message'      => $validated['message'] ?? null,
        'image_url'    => $imageUrl,
    ]);

	return response()->json([
		'message' => 'Saved successfully',
		'data'    => $getInTouch,
		'form_id' => $getInTouch->id, // 👈 map `id` to `form_id`
	], 201);

}


	/**
	 * Display the specified resource.
	 */
	public function show(GetInTouch $getInTouch)
	{
		//
	}

	/**
	 * Update the specified resource in storage.
	 */
	public function update(Request $request, GetInTouch $getInTouch)
	{
		//
	}

	/**
	 * Remove the specified resource from storage.
	 */
	public function destroy(GetInTouch $getInTouch)
	{
		//
	}
}
