<?php

namespace App\Http\Controllers;

use Doctrine\Common\Annotations\Annotation\Required;
use Illuminate\Http\Request;
use App\Models\ProductAccessory;
use App\Models\AccessoryItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use OpenApi\Annotations as OA;

class ProductAccessoriesController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/product-accessories",
     *     summary="Get list of product accessories",
     *     tags={"Product Accessories"},
     *     @OA\Parameter(
     *         name="product_id",
     *         in="query",
     *         required=false,
     *         description="Filter by product ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="isapproved",
     *         in="query",
     *         required=false,
     *         description="Filter by approval status (0 or 1)",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Items per page (default: 15)",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product accessories retrieved successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *      security={{"bearerAuth":{}}}
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = ProductAccessory::with(['items', 'product']); // also eager load product if needed

            // Apply filters
            if ($request->has('product_id')) {
                $query->where('product_id', $request->product_id);
            }

            if ($request->has('isapproved')) {
                $query->where('isapproved', $request->isapproved);
            }

            $perPage = $request->get('per_page', 15);
            $accessories = $query->orderBy('created_at', 'desc')->paginate($perPage);

            $formattedProducts = $accessories->getCollection()->map(function ($accessory) {

                $accessoryItems = $accessory->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'price' => $item->price,
                    ];
                });

                return [
                    'product_id' => $accessory->product_id,
                    'accessory_id' => $accessory->id,
                    'name' => $accessory->name,
                    'isapproved' => $accessory->isapproved,
                    'approved_by' => $accessory->approved_by,
                    'created_by' => $accessory->created_by,
                    'updated_by' => $accessory->updated_by,
                    'accessory_item' => $accessoryItems,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Product accessories retrieved successfully',
                'data' => [
                    'current_page' => $accessories->currentPage(),
                    'per_page' => $accessories->perPage(),
                    'total' => $accessories->total(),
                    'data' => $formattedProducts,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve product accessories',
                'error' => $e->getMessage()
            ], 500);
        }

    }

    /**
     * @OA\Post(
     *     path="/api/product-accessories",
     *     summary="Create a new product accessory",
     *     tags={"Product Accessories"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"product_id", "name", "accessories"},
     *                 @OA\Property(property="product_id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Door"),
     *                 @OA\Property(
     *                     property="accessories",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         required={"name","price"},
     *                         @OA\Property(property="name", type="string", example="left"),
     *                         @OA\Property(property="price", type="number", format="float", example=44)
     *                     ),
     *                     example={
     *                         {"name":"left","price":44},
     *                         {"name":"right","price":45},
     *                         {"name":"top","price":50},
     *                         {"name":"button","price":52}
     *                     }
     *                 ),
     *                 @OA\Property(property="isapproved", type="boolean", example=0)
     *             )
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
    public function store(Request $request)
    {
        try {

            $validator = Validator::make($request->all(), [
                'product_id' => 'required|integer|exists:ec_products,id',
                'name' => 'required|string|max:255|unique:ec_products,name',
                'accessories' => 'required|array',
                'isapproved' => 'sometimes|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            $accessory = ProductAccessory::create([
                'product_id' => $request->product_id,
                'name' => $request->name,
                'isapproved' => $request->get('isapproved', 0),
                'created_by' => Auth::id() ?? 1
            ]);

            $accessories = collect($request->accessories)->map(function ($item) {
                $item['name'] = addslashes($item['name']);
                return $item;
            })->toArray();

            // Save all accessories at once
            $accessory->items()->createMany($accessories);
            // $accessory->items()->createMany($request->accessories);
            $accessory->load(['product', 'createdBy']);

            return response()->json([
                'success' => true,
                'message' => 'Product accessory created successfully',
                'data' => $accessory
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create product accessory',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/product-accessories/show/{id}",
     *     summary="Get a specific product accessory",
     *     tags={"Product Accessories"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Product accessory ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product accessory retrieved successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product accessory not found"
     *     ),
     *      security={{"bearerAuth":{}}}
     * )
     */
    public function show($id): JsonResponse
    {
        try {
            $accessory = ProductAccessory::with(['items', 'approvedBy', 'createdBy', 'updatedBy'])->findOrFail($id);

            // Map items properly
            $accessoryItems = $accessory->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'price' => $item->price,
                ];
            });

            // Format response
            $formattedProduct = [
                'product_id' => $accessory->product_id,
                'accessory_id' => $accessory->id,
                'name' => $accessory->name,
                'isapproved' => $accessory->isapproved,
                'approved_by' => $accessory->approved_by,
                'created_by' => $accessory->created_by,
                'updated_by' => $accessory->updated_by,
                'accessory_item' => $accessoryItems,
            ];
            return response()->json([
                'success' => true,
                'message' => 'Product accessory retrieved successfully',
                'data' => $formattedProduct
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Product accessory not found',
                'error' => $e->getMessage()
            ], 404);
        }

    }

    /**
     * @OA\Post(
     *     path="/api/product-accessories/update/{id}",
     *     summary="Update a product accessory",
     *     tags={"Product Accessories"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Product accessory ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="product_id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Color"),
     *             @OA\Property(
     *                 property="accessories",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="name", type="string", example="left"),
     *                     @OA\Property(property="price", type="integer", example=44)
     *                 ),
     *                 example={
     *                     {"name":"left","price":44},
     *                     {"name":"right","price":45},
     *                     {"name":"top","price":50},
     *                     {"name":"button","price":52}
     *                 }
     *             ),  
     *             @OA\Property(property="isapproved", type="integer", example=1),
     *             @OA\Property(property="approved_by", type="integer", example=2)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product accessory updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product accessory updated successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product accessory not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *      security={{"bearerAuth":{}}}
     * )
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'product_id' => 'required|integer|exists:ec_products,id',
                'name' => 'required|string|max:255',
                'accessories' => 'required|array',
                'isapproved' => 'sometimes|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $accessory = ProductAccessory::findOrFail($id);

            // Update main accessory
            $accessory->update([
                'product_id' => $request->product_id,
                'name' => $request->name,
                'isapproved' => $request->get('isapproved', $accessory->isapproved),
                'updated_by' => Auth::id() ?? 1
            ]);

            // Refresh accessory items (delete old and insert new)
            $accessory->items()->delete();
            $accessories = collect($request->accessories)->map(function ($item) {
                $item['name'] = addslashes($item['name']);
                return $item;
            })->toArray();
            $accessory->items()->createMany($accessories);

            $accessory->load(['product', 'createdBy', 'items']);

            return response()->json([
                'success' => true,
                'message' => 'Product accessory updated successfully',
                'data' => $accessory
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update product accessory',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/product-accessories/delete/{id}",
     *     summary="Delete a product accessory",
     *     tags={"Product Accessories"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Product accessory ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product accessory deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product accessory deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product accessory not found"
     *     ),
     *      security={{"bearerAuth":{}}}
     * )
     */
    public function destroy($id): JsonResponse
    {
        try {
            $accessory = ProductAccessory::with('items')->findOrFail($id);

            // Delete related items first
            $accessory->items()->delete();

            // Delete the accessory itself
            $accessory->delete();

            return response()->json([
                'success' => true,
                'message' => 'Product accessory and related items deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete product accessory',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/product-accessories/item/{item_id}",
     *     summary="Delete a product accessory item",
     *     tags={"Product Accessories"},
     *     @OA\Parameter(
     *         name="item_id",
     *         in="path",
     *         required=true,
     *         description="Accessory Item ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product accessory item deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product accessory item deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Accessory item not found"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */

    public function deleteItem($item_id): JsonResponse
    {
        $item = AccessoryItem::find($item_id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Accessory item not found'
            ], 404);
        }

        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Accessory item deleted successfully'
        ], 200);
    }


    /**
     * @OA\Post(
     *     path="/api/product-accessories/status/{id}",
     *     summary="Approve/Disapprove a product accessory",
     *     tags={"Product Accessories"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Product accessory ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"isapproved"},
     *             @OA\Property(property="isapproved", type="integer", example=1, description="1 for approve, 0 for disapprove")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Approval status updated successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product accessory not found"
     *     ),
     *      security={{"bearerAuth":{}}}
     * )
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        try {

            $accessory = ProductAccessory::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'isapproved' => 'required|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $accessory->update([
                'isapproved' => $request->isapproved,
                'approved_by' => $request->isapproved ? Auth::id() : null,
                'updated_by' => Auth::id()
            ]);

            // $accessory->load(['product', 'approvedBy']);

            return response()->json([
                'success' => true,
                'message' => 'Approval status updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update approval status',
                'error' => $e->getMessage()
            ], 404);
        }
    }
}
