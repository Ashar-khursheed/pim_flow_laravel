<?php

namespace App\Http\Controllers;

use App\Models\BrandTemp2;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BrandTemp2Controller extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/brand-temp-2",
     *     summary="Get all brand temp records",
     *     tags={"BrandTemp2"},
     *     @OA\Response(response=200, description="Success", @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/BrandTemp2"))),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function index()
    {
        return response()->json(BrandTemp2::all());
    }

    /**
     * @OA\Post(
     *     path="/api/brand-temp-2",
     *     summary="Create brand temp",
     *     tags={"BrandTemp2"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"brand_id"},
     *                 @OA\Property(property="brand_id", type="integer"),
     *                 @OA\Property(property="page_top_banners_desktop[]", type="array", @OA\Items(type="string", format="binary")),
     *                 @OA\Property(property="page_top_banners_mobile[]", type="array", @OA\Items(type="string", format="binary")),
     *                 @OA\Property(property="category_banners[]", type="array", @OA\Items(type="string", format="binary")),
     *                  @OA\Property(
     *                           property="category_id",
     *                           type="string",
     *                           description="A JSON string containing category_id and product_ids"
     *                  ),
     *                 @OA\Property(property="page_middle_banners_desktop[]", type="array", @OA\Items(type="string", format="binary")),
     *                 @OA\Property(property="page_middle_banners_mobile[]", type="array", @OA\Items(type="string", format="binary")),
     *                 @OA\Property(property="website_banners_videos[]", type="array", @OA\Items(type="string", format="binary")),
     *                 @OA\Property(property="website_banners_videos_mobile[]", type="array", @OA\Items(type="string", format="binary"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Created", @OA\JsonContent(ref="#/components/schemas/BrandTemp2")),
     *     @OA\Response(response=422, description="Validation error"),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function store(Request $request)
    {
        $data = $this->handleUploads($request);
          // Ensure category_id is stored as a JSON string
        if ($request->has('category_id')) {
            // Convert the category_id to a JSON string
            $data['category_id'] = $request->category_id;
        }

        $brand = BrandTemp2::create($data);
        return response()->json($brand, 201);
    }

    /**
     * @OA\Get(
     *     path="/api/brand-temp-2/{id}",
     *     summary="Get specific brand temp",
     *     tags={"BrandTemp2"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Success", @OA\JsonContent(ref="#/components/schemas/BrandTemp2")),
     *     @OA\Response(response=404, description="Not found"),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function show($id)
    {
        return response()->json(BrandTemp2::findOrFail($id));
    }

    /**
     * @OA\Post(
     *     path="/api/brand-temp-2/{id}",
     *     summary="Update brand temp",
     *     tags={"BrandTemp2"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="brand_id", type="integer"),
     *                 @OA\Property(property="page_top_banners_desktop[]", type="array", @OA\Items(type="string", format="binary")),
     *                 @OA\Property(property="page_top_banners_mobile[]", type="array", @OA\Items(type="string", format="binary")),
     *                 @OA\Property(property="category_banners[]", type="array", @OA\Items(type="string", format="binary")),
     *                 @OA\Property(
     *                       property="category_id",
     *                       type="string",
     *                       description="A JSON string containing category_id and product_ids"
     *                   ),
     *                 @OA\Property(property="page_middle_banners_desktop[]", type="array", @OA\Items(type="string", format="binary")),
     *                 @OA\Property(property="page_middle_banners_mobile[]", type="array", @OA\Items(type="string", format="binary")),
     *                 @OA\Property(property="website_banners_videos[]", type="array", @OA\Items(type="string", format="binary")),
     *                 @OA\Property(property="website_banners_videos_mobile[]", type="array", @OA\Items(type="string", format="binary")),
     *                 @OA\Property(property="_method", type="string", example="PUT")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Updated", @OA\JsonContent(ref="#/components/schemas/BrandTemp2")),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function update(Request $request, $id)
    {
        $brand = BrandTemp2::findOrFail($id);
        $data = $this->handleUploads($request, $brand->brand_id ?? $request->brand_id);
           // Ensure category_id is stored as a JSON string if provided
    if ($request->has('category_id')) {
        $data['category_id'] = $request->category_id;
    }
        $brand->update($data);
        return response()->json($brand);
    }

    /**
     * @OA\Delete(
     *     path="/api/brand-temp-2/{id}",
     *     summary="Delete brand temp",
     *     tags={"BrandTemp2"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deleted", @OA\JsonContent(@OA\Property(property="message", type="string", example="Deleted"))),
     *     @OA\Response(response=404, description="Not found"),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function destroy($id)
    {
        BrandTemp2::findOrFail($id)->delete();
        return response()->json(['message' => 'Deleted']);
    }

    private function handleUploads(Request $request, $brandId = null)
    {
        $fields = [
            'page_top_banners_desktop',
            'page_top_banners_mobile',
            'category_banners',
            'page_middle_banners_desktop',
            'page_middle_banners_mobile',
            'website_banners_videos',
            'website_banners_videos_mobile',
        ];

        $data = $request->except($fields);

        $brandName = 'unknown';
        if ($brandId || $request->brand_id) {
            $brandModel = Brand::find($brandId ?? $request->brand_id);
            if ($brandModel) {
                $brandName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $brandModel->name);
            }
        }

        $baseFolder = env('STORAGE_ENV', 'local') . "/Brand/{$brandName}";

        foreach ($fields as $field) {
            if ($request->hasFile($field)) {
                $files = [];
                foreach ($request->file($field) as $file) {
                    $path = Storage::disk('s3')->put("{$baseFolder}/{$field}", $file);
                    $url = Storage::disk('s3')->url($path);
                    $files[] = ['file' => $url];
                }
                $data[$field] = $files;
            }
        }

        return $data;
    }
}
