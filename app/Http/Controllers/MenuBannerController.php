<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\MenuBanner;


class MenuBannerController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/menu-banners",
     *     summary="Create a new menu banner",
     *     security={{"bearerAuth":{}}},
     *     tags={"Menu Banners"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"category_id", "desktop_image", "mobile_image"},
     *                 @OA\Property(property="category_id", type="integer"),
     *                 @OA\Property(property="desktop_image", type="file", format="binary"),
     *                 @OA\Property(property="desktop_image_alt", type="string"),
     *                 @OA\Property(property="mobile_image", type="file", format="binary"),
     *                 @OA\Property(property="mobile_image_alt", type="string"),
     *                 @OA\Property(property="url", type="string", format="uri")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Banner created"),
     * )
     */
    public function store(Request $request)
    {
        $request->validate([
            'category_id' => 'required|integer',
            'desktop_image' => 'required|image|mimes:jpg,jpeg,png',
            'desktop_image_alt' => 'nullable|string',
            'mobile_image' => 'required|image|mimes:jpg,jpeg,png',
            'mobile_image_alt' => 'nullable|string',
            'url' => 'nullable|url',
        ]);

        $folder = 'menu-categories-images';

        $desktopPath = $request->file('desktop_image')->store($folder, 's3');
        $mobilePath = $request->file('mobile_image')->store($folder, 's3');

        Storage::disk('s3')->setVisibility($desktopPath, 'public');
        Storage::disk('s3')->setVisibility($mobilePath, 'public');

        $menuBanner = MenuBanner::create([
            'category_id' => $request->category_id,
            'desktop_image' => Storage::disk('s3')->url($desktopPath),
            'desktop_image_alt' => $request->desktop_image_alt,
            'mobile_image' => Storage::disk('s3')->url($mobilePath),
            'mobile_image_alt' => $request->mobile_image_alt,
            'url' => $request->url,
        ]);

        return response()->json(['status' => 'success', 'data' => $menuBanner]);
    }

    /**
     * @OA\Get(
     *     path="/api/menu-banners",
     *     summary="Get all menu banners",
     *     security={{"bearerAuth":{}}},
     *     tags={"Menu Banners"},
     *     @OA\Response(response=200, description="List of banners"),
     * )
     */
    public function index()
    {
        return response()->json([
            'status' => 'success',
            'data' => MenuBanner::all()
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/menu-banners/{id}",
     *     summary="Get a single banner by ID",
     *     tags={"Menu Banners"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Banner found"),
     *     @OA\Response(response=404, description="Banner not found")
     * )
     */
    public function show($id)
    {
        $banner = MenuBanner::findOrFail($id);
        return response()->json(['status' => 'success', 'data' => $banner]);
    }

    /**
     * @OA\Post(
     *     path="/api/menu-banners/{id}",
     *     summary="Update a menu banner",
     *     tags={"Menu Banners"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"category_id", "_method"},
     *                 @OA\Property(property="_method", type="string", example="PUT"),
     *                 @OA\Property(property="category_id", type="integer"),
     *                 @OA\Property(property="desktop_image", type="file", format="binary"),
     *                 @OA\Property(property="desktop_image_alt", type="string"),
     *                 @OA\Property(property="mobile_image", type="file", format="binary"),
     *                 @OA\Property(property="mobile_image_alt", type="string"),
     *                 @OA\Property(property="url", type="string", format="uri")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Banner updated"),
     *     @OA\Response(response=404, description="Banner not found")
     * )
     */
    public function update(Request $request, $id)
    {
        $banner = MenuBanner::findOrFail($id);
        $folder = 'menu-categories-images';

        $request->validate([
            'category_id' => 'required|integer',
            'desktop_image' => 'nullable|image|mimes:jpg,jpeg,png',
            'desktop_image_alt' => 'nullable|string',
            'mobile_image' => 'nullable|image|mimes:jpg,jpeg,png',
            'mobile_image_alt' => 'nullable|string',
            'url' => 'nullable|url',
        ]);

        if ($request->hasFile('desktop_image')) {
            $desktopPath = $request->file('desktop_image')->store($folder, 's3');
            Storage::disk('s3')->setVisibility($desktopPath, 'public');
            $banner->desktop_image = Storage::disk('s3')->url($desktopPath);
        }

        if ($request->hasFile('mobile_image')) {
            $mobilePath = $request->file('mobile_image')->store($folder, 's3');
            Storage::disk('s3')->setVisibility($mobilePath, 'public');
            $banner->mobile_image = Storage::disk('s3')->url($mobilePath);
        }

        $banner->update([
            'category_id' => $request->category_id,
            'desktop_image_alt' => $request->desktop_image_alt,
            'mobile_image_alt' => $request->mobile_image_alt,
            'url' => $request->url,
        ]);

        return response()->json(['status' => 'success', 'data' => $banner]);
    }

    /**
     * @OA\Delete(
     *     path="/api/menu-banners/{id}",
     *     summary="Delete a menu banner",
     *     tags={"Menu Banners"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Banner deleted"),
     *     @OA\Response(response=404, description="Banner not found")
     * )
     */
    public function destroy($id)
    {
        $banner = MenuBanner::findOrFail($id);

        foreach (['desktop_image', 'mobile_image'] as $imageKey) {
            $url = $banner->{$imageKey};
            if ($url) {
                $parsedUrl = parse_url($url, PHP_URL_PATH);
                $s3Path = ltrim($parsedUrl, '/');
                Storage::disk('s3')->delete($s3Path);
            }
        }

        $banner->delete();

        return response()->json(['status' => 'success', 'message' => 'Banner deleted']);
    }
}
