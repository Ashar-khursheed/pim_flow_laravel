<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\MenuBanner;


class MenuBannerController extends Controller
{
   

   /**
     * @OA\Get(
     *     path="/api/frontend/menu-banners",
     *     summary="Get all menu banners grouped by category_id",
     *     tags={"Frontend Menu Banners"},
     *     @OA\Response(response=200, description="Grouped list of banners"),
     * )
     */
    public function index()
    {
        $grouped = MenuBanner::all()->groupBy('category_id')->map(function ($items) {
            return $items->values(); // reset array keys
        });

        return response()->json([
            'status' => 'success',
            'data' => $grouped,
        ]);
    }


    /**
     * @OA\Get(
     *     path="/api/frontend/menu-banners/category/{category_id}",
     *     summary="Get all banners by category ID",
     *     tags={"Frontend Menu Banners"},
     *     @OA\Parameter(
     *         name="category_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Banners found"),
     *     @OA\Response(response=404, description="No banners found")
     * )
     */
    public function show($category_id)
    {
        $banners = MenuBanner::where('category_id', $category_id)->get();

        if ($banners->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No banners found for this category'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $banners
        ]);
    }

  
}
