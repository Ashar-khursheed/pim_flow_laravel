<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Models\FrontEnd\SupportCategory;
use App\Models\FrontEnd\SupportPriority;


class SupportMetaController extends Controller
{
    /**
     * Get all support categories
     *
     * @OA\Get(
     *     path="/api/frontend/support-categories",
     *     operationId="getSupportCategories",
     *     tags={"FrontEnd-SupportTickets"},
     *     summary="Get all support ticket categories",
     *     description="Returns a list of all available support categories.",
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Technical Support"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getCategories()
    {
        return response()->json([
            'success' => true,
            'data' =>SupportCategory::all()
        ]);
    }

    /**
     * Get all support priorities
     *
     * @OA\Get(
     *     path="/api/frontend/support-priorities",
     *     operationId="getSupportPriorities",
     *     tags={"FrontEnd-SupportTickets"},
     *     summary="Get all support ticket priorities",
     *     description="Returns a list of all available support priorities (ordered by severity level).",
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="High"),
     *                     @OA\Property(property="level", type="integer", example=3),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getPriorities()
    {
        return response()->json([
            'success' => true,
            'data' =>SupportPriority::orderBy('level')->get()
        ]);
    }
}
