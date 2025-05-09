<?php

namespace App\Http\Controllers;

use App\Models\FlashSale;
use Illuminate\Http\Request;

class FlashSaleController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/flash-sales",
     *     summary="Get all flash sales",
     *     security={{"bearerAuth":{}}},
     *     tags={"Flash Sales"},
     *     @OA\Response(
     *         response=200,
     *         description="List of flash sales",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/FlashSale"))
     *     )
     * )
     */
    public function index()
    {
        if (!auth()->user()->can('list flash sale')) {
            return response()->json([
                'success' => false,
                'message' => "You don't have permission to access this module.",
            ]);
        }
        return response()->json(FlashSale::all(), 200);
    }

    /**
     * @OA\Post(
     *     path="/api/flash-sales",
     *     summary="Create a new flash sale",
     *     tags={"Flash Sales"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "end_date", "status"},
     *             @OA\Property(property="name", type="string", maxLength=191),
     *             @OA\Property(property="end_date", type="string", format="date"),
     *             @OA\Property(property="status", type="string", enum={"published", "draft"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Flash sale created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/FlashSale")
     *     )
     * )
     */
    public function store(Request $request)
    {
        if (!auth()->user()->can('add flash sale')) {
            return response()->json([
                'success' => false,
                'message' => "You don't have permission to access this module.",
            ]);
        }
        $request->validate([
            'name' => 'required|string|max:191',
            'end_date' => 'required|date|after:today',
            'status' => 'required|string|in:published,draft',
        ]);

        $flashSale = FlashSale::create($request->all());

        return response()->json($flashSale, 201);
    }

    /**
     * @OA\Get(
     *     path="/api/flash-sales/{id}",
     *     summary="Get a specific flash sale",
     *     tags={"Flash Sales"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Flash Sale ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Flash sale details",
     *         @OA\JsonContent(ref="#/components/schemas/FlashSale")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Flash Sale not found"
     *     )
     * )
     */
    public function show($id)
    {
        if (!auth()->user()->can('show flash sale')) {
            return response()->json([
                'success' => false,
                'message' => "You don't have permission to access this module.",
            ]);
        }
        $flashSale = FlashSale::find($id);
        if (!$flashSale) {
            return response()->json(['message' => 'Flash Sale not found'], 404);
        }
        return response()->json($flashSale, 200);
    }

    /**
     * @OA\Put(
     *     path="/api/flash-sales/{id}",
     *     summary="Update a flash sale",
     *     tags={"Flash Sales"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Flash Sale ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", maxLength=191),
     *             @OA\Property(property="end_date", type="string", format="date"),
     *             @OA\Property(property="status", type="string", enum={"published", "draft"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Flash sale updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/FlashSale")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Flash Sale not found"
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        if (!auth()->user()->can('update flash sale')) {
            return response()->json([
                'success' => false,
                'message' => "You don't have permission to access this module.",
            ]);
        }
        $flashSale = FlashSale::find($id);
        if (!$flashSale) {
            return response()->json(['message' => 'Flash Sale not found'], 404);
        }

        $request->validate([
            'name' => 'sometimes|string|max:191',
            'end_date' => 'sometimes|date|after:today',
            'status' => 'sometimes|string|in:published,draft',
        ]);

        $flashSale->update($request->all());

        return response()->json($flashSale, 200);
    }

    /**
     * @OA\Delete(
     *     path="/api/flash-sales/{id}",
     *     summary="Delete a flash sale",
     *     tags={"Flash Sales"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Flash Sale ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Flash Sale deleted successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Flash Sale not found"
     *     )
     * )
     */
    public function destroy($id)
    {
        if (!auth()->user()->can('delete flash sale')) {
            return response()->json([
                'success' => false,
                'message' => "You don't have permission to access this module.",
            ]);
        }
        $flashSale = FlashSale::find($id);
        if (!$flashSale) {
            return response()->json(['message' => 'Flash Sale not found'], 404);
        }

        $flashSale->delete();

        return response()->json(['message' => 'Flash Sale deleted successfully'], 200);
    }
}
