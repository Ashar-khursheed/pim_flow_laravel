<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Models\FrontEnd\Slider;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;
use App\Models\Simple;
use App\Models\SimpleSlider;
use App\Models\SimpleSliderItem;
class SliderController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/frontend/sliders",
     *     summary="Get list of sliders",
     *     tags={"Frontend-Sliders"},
     *     @OA\Response(
     *         response=200,
     *         description="List of sliders with slider items",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/SimpleSlider")
     *         )
     *     )
     * )
     */
    public function index()
    {
        return response()->json(SimpleSlider::with('items')->get());
    }

    /**
     * @OA\Get(
     *     path="/api/frontend/sliders/{id}",
     *     summary="Get a specific slider by ID",
     *     tags={"Frontend-Sliders"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Slider ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Slider with slider items",
     *         @OA\JsonContent(ref="#/components/schemas/SimpleSlider")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Slider not found"
     *     )
     * )
     */
   public function show($id)
{
    $slider = SimpleSlider::with('items')->findOrFail($id);

    return response()
        ->json($slider)
        ; // cache for 1 day
}

}
