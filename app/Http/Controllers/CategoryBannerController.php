<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\CategoryBanner;


class CategoryBannerController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/category-banners",
     *     tags={"Category Banners"},
     *     summary="List category banners",
     *     security={{"bearerAuth":{}}},
     *     description="Fetch category banners with pagination, sorting and search",
     *     @OA\Parameter(
    *         name="category_id",
    *         in="query",
    *         required=false,
    *         description="Category ID to filter",
    *         @OA\Schema(type="integer")
    *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search in alt text",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Field to sort by",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="order",
     *         in="query",
     *         description="Sort direction: asc or desc",
     *         @OA\Schema(type="string", enum={"asc", "desc"})
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = CategoryBanner::with('category');
    
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
    
        if ($request->filled('search')) {
            $searchTerm = $request->search;
    
            $query->where(function ($q) use ($searchTerm) {
                $q->where('image_alt_text', 'like', "%{$searchTerm}%")
                  ->orWhereHas('category', function ($subQuery) use ($searchTerm) {
                      $subQuery->where('name', 'like', "%{$searchTerm}%");
                  });
            });
        }
    
        if ($request->filled('sort_by')) {
            $query->orderBy($request->sort_by, $request->get('order', 'asc'));
        } else {
            $query->orderBy('position');
        }
    
        $perPage = $request->get('per_page', 10);
        $banners = $query->paginate($perPage);
    
        return response()->json([
            'success' => true,
            'data' => $banners,
        ]);
    }
    

    /**
     * @OA\Get(
     *     path="/api/category-banners/show/{category_id}",
     *     tags={"Category Banners"},
     *     summary="Show all banners for a category",
     *     description="Retrieve all banners for a given category ID",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="category_id",
     *         in="path",
     *         required=true,
     *         description="The ID of the category",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="category_id", type="integer"),
     *                 @OA\Property(property="image_url", type="string"),
     *                 @OA\Property(property="image_alt_text", type="string"),
     *                 @OA\Property(property="position", type="integer"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time"),
     *                 @OA\Property(property="category", type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="name", type="string")
     *                 )
     *             ))
     *         )
     *     )
     * )
     */
    public function show($category_id)
    {
        $banners = CategoryBanner::with('category')
            ->where('category_id', $category_id)
            ->orderBy('position')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $banners,
        ]);
    }

    

    /**
     * @OA\Post(
     *     path="/api/category-banners",
     *     tags={"Category Banners"},
     *     summary="Upload a new category banner",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"category_id", "image_file"},
     *                 @OA\Property(property="category_id", type="integer", example=1),
     *                 @OA\Property(property="image_file", type="file"),
     *                 @OA\Property(property="image_alt_text", type="string", example="Alt text here"),
     *                 @OA\Property(property="position", type="integer", example=1)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Banner uploaded",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|integer',
            'image_file' => 'required|image|mimes:jpeg,png,jpg,gif,webp',
            'image_alt_text' => 'nullable|string',
            'position' => 'nullable|integer',
        ]);

        $path = $request->file('image_file')->store(env('STORAGE_ENV') . '/categories/banners', 's3');
        $url = Storage::disk('s3')->url($path);

        $banner = CategoryBanner::create([
            'category_id' => $validated['category_id'],
            'image_url' => $url,
            'image_alt_text' => $validated['image_alt_text'] ?? null,
            'position' => $validated['position'] ?? 0,
        ]);

        return response()->json([
            'success' => true,
            'data' => $banner,
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/category-banners/{id}",
     *     tags={"Category Banners"},
     *     summary="Update a category banner",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Banner ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"category_id"},
     *                 @OA\Property(property="category_id", type="integer", example=1),
     *                 @OA\Property(property="image_file", type="file"),
     *                 @OA\Property(property="image_alt_text", type="string", example="Updated alt text"),
     *                 @OA\Property(property="position", type="integer", example=2)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Banner updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'category_id' => 'required|integer',
            'image_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp',
            'image_alt_text' => 'nullable|string',
            'position' => 'nullable|integer',
        ]);

        $banner = CategoryBanner::findOrFail($id);
        $banner->category_id = $validated['category_id'];

        if ($request->hasFile('image_file')) {
            $path = $request->file('image_file')->store(env('STORAGE_ENV') . '/categories/banners', 's3');
            $banner->image_url = Storage::disk('s3')->url($path);
        }

        if (isset($validated['image_alt_text'])) {
            $banner->image_alt_text = $validated['image_alt_text'];
        }

        if (isset($validated['position'])) {
            $banner->position = $validated['position'];
        }

        $banner->save();

        return response()->json([
            'success' => true,
            'data' => $banner,
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/category-banners/{id}",
     *     tags={"Category Banners"},
     *     summary="Delete a banner",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Banner ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Banner deleted",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string", example="Banner deleted successfully.")
     *         )
     *     )
     * )
     */
    public function destroy($id)
    {
        $banner = CategoryBanner::findOrFail($id);
        $banner->delete();

        return response()->json([
            'success' => true,
            'message' => 'Banner deleted successfully.',
        ]);
    }
}
