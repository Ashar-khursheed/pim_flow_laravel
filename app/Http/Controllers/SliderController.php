<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SimpleSlider;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Sliders", description="API for managing sliders")
 */
class SliderController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/sliders",
     *     summary="Get all sliders",
     *     tags={"Sliders"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="List of sliders",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/SimpleSlider"))
     *     )
     * )
     */
    public function index()
    {
        return response()->json(SimpleSlider::with('items')->get());
    }

/**
 * @OA\Post(
 *     path="/api/sliders",
 *     summary="Create a new slider",
 *     tags={"Sliders"},
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="multipart/form-data",
 *             @OA\Schema(
 *                 required={"name", "key", "status", "images"},
 *                 @OA\Property(property="name", type="string", example="Homepage Slider"),
 *                 @OA\Property(property="key", type="string", example="homepage_slider"),
 *                 @OA\Property(property="description", type="string", nullable=true, example="Main slider"),
 *                 @OA\Property(property="status", type="string", example="published"),
 *                 @OA\Property(
 *                     property="images",
 *                     type="array",
 *                     @OA\Items(
 *                         type="string",
 *                         format="binary"
 *                     )
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(response=201, description="Slider created"),
 *     @OA\Response(response=422, description="Validation error")
 * )
 */

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'key' => 'required|string|max:120|unique:simple_sliders,key',
            'description' => 'nullable|string|max:400',
            'status' => 'required|string|in:published,draft',
            'images.*' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048' // ✅ Accept multiple images
        ]);
    
        // Create the slider
        $slider = SimpleSlider::create([
            'name' => $validated['name'],
            'key' => $validated['key'],
            'description' => $validated['description'],
            'status' => $validated['status']
        ]);
    
        // Handle multiple images
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $imagePath = 'sliders';
                $path = $image->store($imagePath, 's3');
    
                // Create a new slider item (image)
                $slider->items()->create([
                    'slider_id' => $slider->id,
                    'image' => Storage::disk('s3')->url($path),
                ]);
            }
        }
    
        return response()->json($slider->load('items'), 201); // ✅ Return with images
    }
    

    /**
     * @OA\Get(
     *     path="/api/sliders/{id}",
     *     summary="Get a single slider",
     *     tags={"Sliders"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Slider ID", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Slider details",
     *         @OA\JsonContent(ref="#/components/schemas/SimpleSlider")
     *     ),
     *     @OA\Response(response=404, description="Slider not found")
     * )
     */
    public function show($id)
    {
        $slider = SimpleSlider::with('items')->findOrFail($id);
        return response()->json($slider);
    }

    /**
     * @OA\Post(
     *     path="/api/sliders/{id}",
     *     summary="Update an existing slider",
     *     tags={"Sliders"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Slider ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"_method", "name", "status"},
     *                 @OA\Property(property="_method", type="string", example="PUT"),
     *                 @OA\Property(property="name", type="string", example="Updated Slider"),
     *                 @OA\Property(property="key", type="string", example="updated_key"),
     *                 @OA\Property(property="description", type="string", nullable=true, example="Updated description"),
     *                 @OA\Property(property="status", type="string", example="draft"),
     *                 @OA\Property(
     *                     property="images",
     *                     type="array",
     *                     @OA\Items(type="string", format="binary")
     *                 ),
     *                 @OA\Property(
     *                     property="deleted_images",
     *                     type="array",
     *                     @OA\Items(type="integer"),
     *                     example="[1,2,3]"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Slider updated"),
     *     @OA\Response(response=404, description="Slider not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */

    public function update(Request $request, $id)
    {
        $slider = SimpleSlider::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'key' => 'required|string|max:120|unique:simple_sliders,key,' . $id,
            'description' => 'nullable|string|max:400',
            'status' => 'required|string|in:published,draft',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048', // ✅ Allow multiple images
            'deleted_images' => 'nullable|array',
            'deleted_images.*' => 'integer|exists:simple_slider_items,id' // ✅ Validate deleted image IDs
        ]);

        // Update slider details
        $slider->update([
            'name' => $validated['name'],
            'key' => $validated['key'],
            'description' => $validated['description'],
            'status' => $validated['status']
        ]);

        // ✅ Delete specified images
        if ($request->has('deleted_images')) {
            $slider->items()->whereIn('id', $validated['deleted_images'])->delete();
        }

        // ✅ Append new images without deleting old ones
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $imagePath = 'sliders';
                $path = $image->store($imagePath, 's3');

                // Create a new image record
                $slider->items()->create([
                    'slider_id' => $slider->id,
                    'image' => Storage::disk('s3')->url($path),
                ]);
            }
        }

        return response()->json($slider->load('items'));
    }

    

    /**
     * @OA\Delete(
     *     path="/api/sliders/{id}",
     *     summary="Delete a slider",
     *     tags={"Sliders"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Slider ID", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Slider deleted"),
     *     @OA\Response(response=404, description="Slider not found")
     * )
     */
    public function destroy($id)
    {
        $slider = SimpleSlider::findOrFail($id);
        $slider->delete();

        return response()->json(['message' => 'Slider deleted successfully']);
    }
}
