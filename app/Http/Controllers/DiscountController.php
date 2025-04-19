<?php
namespace App\Http\Controllers;

use App\Models\Discount;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Discounts",
 *     description="API Endpoints for managing discounts"
 * )
 */
class DiscountController extends Controller
{
    /**
 * @OA\Get(
 *     path="/api/discounts",
 *     summary="Get paginated discounts",
 *     tags={"Discounts"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="page",
 *         in="query",
 *         description="Page number",
 *         required=false,
 *         @OA\Schema(type="integer", example=1)
 *     ),
 *     @OA\Parameter(
 *         name="limit",
 *         in="query",
 *         description="Number of records per page (default: 20)",
 *         required=false,
 *         @OA\Schema(type="integer", example=20)
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="List of discounts with pagination",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="current_page", type="integer", example=1),
 *             @OA\Property(property="last_page", type="integer", example=5),
 *             @OA\Property(property="per_page", type="integer", example=20),
 *             @OA\Property(property="total", type="integer", example=100),
 *             @OA\Property(
 *                 property="data",
 *                 type="array",
 *                 @OA\Items(ref="#/components/schemas/Discount")
 *             )
 *         )
 *     )
 * )
 */
public function index(Request $request)
{
    if (!auth()->user()->can('list discount')) {
        return response()->json([
            'success' => false,
            'message' => "You don't have permission to access this module.",
        ]);
    }
    // Set default limit (20) and fetch paginated results
    $limit = $request->query('limit', 20); // Default limit 20 if not provided
    $discounts = Discount::paginate($limit);

    return response()->json($discounts, 200);
}


    /**
     * @OA\Post(
     *     path="/api/discounts",
     *     summary="Create a new discount",
     *     tags={"Discounts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title", "value", "type", "type_option", "target"},
     *             @OA\Property(property="title", type="string", maxLength=120),
     *             @OA\Property(property="code", type="string", maxLength=20, nullable=true),
     *             @OA\Property(property="start_date", type="string", format="date-time", nullable=true),
     *             @OA\Property(property="end_date", type="string", format="date-time", nullable=true),
     *             @OA\Property(property="quantity", type="integer", nullable=true),
     *             @OA\Property(property="value", type="number"),
     *             @OA\Property(property="type", type="string"),
     *             @OA\Property(property="type_option", type="string"),
     *             @OA\Property(property="target", type="string"),
     *             @OA\Property(property="min_order_price", type="number", nullable=true),
     *             @OA\Property(property="apply_via_url", type="boolean", nullable=true),
     *             @OA\Property(property="display_at_checkout", type="boolean", nullable=true),
     *             @OA\Property(property="description", type="string", nullable=true),
     *             @OA\Property(property="store_id", type="integer", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Discount created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Discount")
     *     )
     * )
     */
    public function store(Request $request)
    {
        if (!auth()->user()->can('add discount')) {
            return response()->json([
                'success' => false,
                'message' => "You don't have permission to access this module.",
            ]);
        }
        $request->validate([
            'title' => 'required|string|max:120',
            'code' => 'nullable|string|max:20|unique:ec_discounts,code',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'quantity' => 'nullable|integer|min:0',
            'value' => 'required|numeric|min:0',
            'type' => 'required|string',
            'type_option' => 'required|string',
            'target' => 'required|string',
            'min_order_price' => 'nullable|numeric|min:0',
            'apply_via_url' => 'boolean',
            'display_at_checkout' => 'boolean',
            'description' => 'nullable|string',
            'store_id' => 'nullable|integer|exists:stores,id',
        ]);

        $discount = Discount::create($request->all());

        return response()->json($discount, 201);
    }

    /**
     * @OA\Get(
     *     path="/api/discounts/{id}",
     *     summary="Get a specific discount",
     *     tags={"Discounts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Discount ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Discount details",
     *         @OA\JsonContent(ref="#/components/schemas/Discount")
     *     ),
     *     @OA\Response(response=404, description="Discount not found")
     * )
     */
    public function show($id)
    {
        if (!auth()->user()->can('show discount')) {
            return response()->json([
                'success' => false,
                'message' => "You don't have permission to access this module.",
            ]);
        }
        $discount = Discount::find($id);
        if (!$discount) {
            return response()->json(['message' => 'Discount not found'], 404);
        }
        return response()->json($discount, 200);
    }

    /**
     * @OA\Put(
     *     path="/api/discounts/{id}",
     *     summary="Update a discount",
     *     tags={"Discounts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Discount ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", maxLength=120),
     *             @OA\Property(property="value", type="number"),
     *             @OA\Property(property="type", type="string"),
     *             @OA\Property(property="type_option", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Discount updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Discount")
     *     ),
     *     @OA\Response(response=404, description="Discount not found")
     * )
     */
    public function update(Request $request, $id)
    {
        if (!auth()->user()->can('update discount')) {
            return response()->json([
                'success' => false,
                'message' => "You don't have permission to access this module.",
            ]);
        }
        $discount = Discount::find($id);
        if (!$discount) {
            return response()->json(['message' => 'Discount not found'], 404);
        }

        $request->validate([
            'title' => 'sometimes|string|max:120',
            'value' => 'sometimes|numeric|min:0',
            'type' => 'sometimes|string',
            'type_option' => 'sometimes|string',
        ]);

        $discount->update($request->all());

        return response()->json($discount, 200);
    }

    /**
     * @OA\Delete(
     *     path="/api/discounts/{id}",
     *     summary="Delete a discount",
     *     tags={"Discounts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Discount ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Discount deleted successfully"),
     *     @OA\Response(response=404, description="Discount not found")
     * )
     */
    public function destroy($id)
    {
        if (!auth()->user()->can('delete discount')) {
            return response()->json([
                'success' => false,
                'message' => "You don't have permission to access this module.",
            ]);
        }
        $discount = Discount::find($id);
        if (!$discount) {
            return response()->json(['message' => 'Discount not found'], 404);
        }

        $discount->delete();

        return response()->json(['message' => 'Discount deleted successfully'], 200);
    }
}
