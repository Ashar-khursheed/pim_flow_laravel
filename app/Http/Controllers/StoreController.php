<?php

namespace App\Http\Controllers;
use Illuminate\Http\JsonResponse;

use Illuminate\Http\Request;
use App\Models\Store;

class StoreController extends BaseController
{
	/**
	 * Display a listing of the resource.
	 */
	/**
	 * @OA\Get(
	 *     path="/api/stores",
	 *     summary="Get stores List",
	 *     description="Fetches a list of all stores.",
	 *     tags={"Stores"},
	 *     @OA\Parameter(
	 *         name="page",
	 *         in="query",
	 *         description="Page number for pagination. Starts from 1.",
	 *         required=true,
	 *         example=1,
	 *         @OA\Schema(
	 *             type="integer",
	 *             minimum=1
	 *         )
	 *     ),
	 *     @OA\Parameter(
	 *         name="length",
	 *         in="query",
	 *         description="Number of records per page.",
	 *         required=true,
	 *         example=20,
	 *         @OA\Schema(
	 *             type="integer",
	 *             minimum=1
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=201,
	 *         description="Success",
	 *          @OA\MediaType(
	 *              mediaType="application/json",
	 *          )
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function index(Request $request)
	{
		$stores = Store::query();

		if($request->filled('page') && $request->filled('length')){
			$page = $request->input('page');
			$length = $request->input('length');
			$stores = $stores->offset(($page - 1)*$length)->limit($length);
		}

		$stores = $stores->pluck('name', 'id');

		return response()->json([
			'message' => 'Store List',
			'stores' => $stores
		]);
	}

	
    /**
     * @OA\Post(
     *     path="/api/stores",
     *     summary="Create a new store",
     *     tags={"Stores"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="New Store"),
     *             @OA\Property(property="description", type="string", example="Store Description"),
     *             @OA\Property(property="website", type="string", example="https://store.com"),
     *             @OA\Property(property="status", type="string", example="active")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Store Created"),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:191',
            'description' => 'nullable|string',
            'website' => 'nullable|url',
            'status' => 'nullable|string|max:60'
        ]);

        $store = Store::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Store Created',
            'store' => $store
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/stores/{id}",
     *     summary="Get store details",
     *     tags={"Stores"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Store ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Store Details"),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function show($id): JsonResponse
    {
        $store = Store::find($id);

        if (!$store) {
            return response()->json(['success' => false, 'message' => 'Store Not Found'], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Store Details',
            'store' => $store
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/stores/{id}",
     *     summary="Update an existing store",
     *     tags={"Stores"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Store ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Updated Store"),
     *             @OA\Property(property="description", type="string", example="Updated Description"),
     *             @OA\Property(property="website", type="string", example="https://updatedstore.com"),
     *             @OA\Property(property="status", type="string", example="inactive")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Store Updated"),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function update(Request $request, $id): JsonResponse
    {
        $store = Store::find($id);

        if (!$store) {
            return response()->json(['success' => false, 'message' => 'Store Not Found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:191',
            'description' => 'nullable|string',
            'website' => 'nullable|url',
            'status' => 'nullable|string|max:60'
        ]);

        $store->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Store Updated',
            'store' => $store
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/stores/{id}",
     *     summary="Delete a store",
     *     tags={"Stores"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Store ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Store Deleted"),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function destroy($id): JsonResponse
    {
        $store = Store::find($id);

        if (!$store) {
            return response()->json(['success' => false, 'message' => 'Store Not Found'], 404);
        }

        $store->delete();

        return response()->json([
            'success' => true,
            'message' => 'Store Deleted'
        ]);
    }
}
