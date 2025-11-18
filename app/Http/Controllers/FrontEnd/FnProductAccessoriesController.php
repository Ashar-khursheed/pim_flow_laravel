<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProductAccessory;
use App\Models\AccessoryItem;
class FnProductAccessoriesController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/frontend/product-accessories",
     *     summary="Get list of Frontend product accessories",
     *     tags={"Frontend Product Accessories"},
     *     @OA\Parameter(
     *         name="product_id",
     *         in="query",
     *         required=false,
     *         description="Filter by product ID",
     *         @OA\Schema(type="integer")
     *     ),
     *      
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
    public function index(Request $request)
    {
        try {
            $accessories = ProductAccessory::with(['items', 'approvedBy', 'createdBy', 'updatedBy'])
                ->where('product_id', $request->product_id)
                ->get();

            if ($accessories->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No product accessories found',
                ], 404);
            }

            // Map each accessory with its items
            $formattedProducts = $accessories->map(function ($accessory) {
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
                    'isapproved' => $accessory->isapproved,
                    'isRequired' => $accessory->isRequired,
                    'name' => $accessory->name,
                    'accessory_item' => $accessoryItems,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Product accessories retrieved successfully',
                'data' => $formattedProducts
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
