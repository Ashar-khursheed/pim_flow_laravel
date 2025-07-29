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
     *     path="/api/frontend/menu-banners/{id}",
     *     summary="Get a single banner by ID",
     *     tags={"Frontend Menu Banners"},
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

  
}
