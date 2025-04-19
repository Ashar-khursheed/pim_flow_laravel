<?php
// app/Http/Controllers/API/OrderHistoryController.php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Http\Resources\OrderHistoryResource;
use App\Http\Resources\OrderHistoryCollection;
use Illuminate\Http\Request;

/**
 * @OA\Schema(
 *     schema="OrderHistory",
 *     type="object",
 *     title="OrderHistory",
 *     required={"id", "order_id", "status", "changed_by"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="order_id", type="integer", example=1001),
 *     @OA\Property(property="status", type="string", example="shipped"),
 *     @OA\Property(property="comment", type="string", example="Order shipped via FedEx"),
 *     @OA\Property(property="changed_by", type="string", example="admin"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2024-04-15T12:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-04-16T08:30:00Z")
 * )
 */


class OrderHistoryController extends Controller
{
    /**
     * Display a listing of order histories for a specific order.
     *
     * @param Order $order
     * @return OrderHistoryCollection
     */

     /**
     * @OA\Get(
     *     path="/api/orders/{order}/histories",
     *     summary="Get order history",
     *     description="Returns history entries for a specific order",
     *     tags={"Order Histories"},
     *     @OA\Parameter(
     *         name="order",
     *         in="path",
     *         required=true,
     *         description="Order ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/OrderHistory")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Order not found"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function index(Order $order)
    {
        $histories = $order->histories()->orderBy('created_at', 'desc')->get();
        
        return new OrderHistoryCollection($histories);
    }

    /**
     * Store a newly created order history in storage.
     *
     * @param Request $request
     * @param Order $order
     * @return OrderHistoryResource
     */


      /**
     * @OA\Post(
     *     path="/api/orders/{order}/histories",
     *     summary="Add history entry",
     *     description="Creates a new history entry for a specific order",
     *     tags={"Order Histories"},
     *     @OA\Parameter(
     *         name="order",
     *         in="path",
     *         required=true,
     *         description="Order ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"action"},
     *             @OA\Property(property="action", type="string", example="note"),
     *             @OA\Property(property="description", type="string", example="Customer called about delivery date"),
     *             @OA\Property(
     *                 property="extras",
     *                 type="object",
     *                 example={"phone": "1234567890", "contact_name": "John Doe"}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="History entry created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/OrderHistory")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Order not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function store(Request $request, Order $order)
    {
        $request->validate([
            'action' => 'required|string|max:120',
            'description' => 'nullable|string|max:400',
            'extras' => 'nullable|array',
        ]);
        
        $history = $order->histories()->create([
            'action' => $request->action,
            'description' => $request->description,
            'user_id' => auth()->id(),
            'extras' => $request->extras,
        ]);
        
        return new OrderHistoryResource($history);
    }

    /**
     * Display the specified order history.
     *
     * @param Order $order
     * @param OrderHistory $history
     * @return OrderHistoryResource
     */

     /**
     * @OA\Get(
     *     path="/api/orders/{order}/histories/{history}",
     *     summary="Get history entry",
     *     description="Returns a specific history entry for an order",
     *     tags={"Order Histories"},
     *     @OA\Parameter(
     *         name="order",
     *         in="path",
     *         required=true,
     *         description="Order ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="history",
     *         in="path",
     *         required=true,
     *         description="History ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/OrderHistory")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Order or history not found"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */

    public function show(Order $order, OrderHistory $history)
    {
        // Ensure the history belongs to the order
        if ($history->order_id !== $order->id) {
            return response()->json(['message' => 'History record not found for this order'], 404);
        }
        
        return new OrderHistoryResource($history);
    }
}