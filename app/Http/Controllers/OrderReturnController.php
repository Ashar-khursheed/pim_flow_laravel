<?php
// app/Http/Controllers/API/OrderReturnController.php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Http\Requests\OrderReturn\StoreOrderReturnRequest;
use App\Http\Requests\OrderReturn\UpdateOrderReturnRequest;
use App\Http\Resources\OrderReturnResource;
use App\Http\Resources\OrderReturnCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

    /**
     * @OA\Schema(
     *     schema="OrderReturn",
     *     type="object",
     *     title="OrderReturn",
     *     required={"id", "order_id", "user_id", "return_status"},
     *     @OA\Property(property="id", type="integer", example=1),
     *     @OA\Property(property="order_id", type="integer", example=1001),
     *     @OA\Property(property="user_id", type="integer", example=500),
     *     @OA\Property(property="store_id", type="integer", example=10),
     *     @OA\Property(property="code", type="string", example="RET-12345"),
     *     @OA\Property(property="return_status", type="string", example="pending"),
     *     @OA\Property(property="reason", type="string", example="Damaged item"),
     *     @OA\Property(property="created_at", type="string", format="date-time", example="2024-04-15T12:00:00Z"),
     *     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-04-16T08:30:00Z")
     * )
    */



class OrderReturnController extends Controller
{
    /**
     * Display a listing of order returns.
     *
     * @param Request $request
     * @return OrderReturnCollection
     */


    /**
     * @OA\Get(
     *     path="/api/order-returns",
     *     summary="Get list of order returns",
     *     description="Returns paginated list of order returns with optional filters",
     *     tags={"Order Returns"},
     *     @OA\Parameter(
     *         name="return_status",
     *         in="query",
     *         required=false,
     *         description="Filter by return status",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="order_id",
     *         in="query",
     *         required=false,
     *         description="Filter by order ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="user_id",
     *         in="query",
     *         required=false,
     *         description="Filter by user ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         required=false,
     *         description="Filter by store ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="code",
     *         in="query",
     *         required=false,
     *         description="Search by return code",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         required=false,
     *         description="Filter by start date (Y-m-d)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="to_date",
     *         in="query",
     *         required=false,
     *         description="Filter by end date (Y-m-d)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="sort_field",
     *         in="query",
     *         required=false,
     *         description="Field to sort by",
     *         @OA\Schema(type="string", default="created_at")
     *     ),
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         required=false,
     *         description="Sort direction (asc, desc)",
     *         @OA\Schema(type="string", default="desc")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Items per page",
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/OrderReturn")
     *             ),
     *             @OA\Property(
     *                 property="pagination",
     *                 type="object",
     *                 @OA\Property(property="total", type="integer", example=30),
     *                 @OA\Property(property="count", type="integer", example=15),
     *                 @OA\Property(property="per_page", type="integer", example=15),
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="total_pages", type="integer", example=2),
     *                 @OA\Property(
     *                     property="links",
     *                     type="object",
     *                     @OA\Property(property="previous", type="string", example=null),
     *                     @OA\Property(property="next", type="string", example="http://example.com/api/order-returns?page=2")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function index(Request $request)
    {
        $query = OrderReturn::with(['items', 'order']);
        
        // Filter by return status
        if ($request->has('return_status')) {
            $query->where('return_status', $request->return_status);
        }
        
        // Filter by order ID
        if ($request->has('order_id')) {
            $query->where('order_id', $request->order_id);
        }
        
        // Filter by user ID
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        
        // Filter by store ID
        if ($request->has('store_id')) {
            $query->where('store_id', $request->store_id);
        }
        
        // Search by code
        if ($request->has('code')) {
            $query->where('code', 'like', '%' . $request->code . '%');
        }
        
        // Date range filter
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        
        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }
        
        // Sort options
        $sortField = $request->input('sort_field', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        
        $query->orderBy($sortField, $sortOrder);
        
        // Paginate results
        $perPage = $request->input('per_page', 15);
        $returns = $query->paginate($perPage);
        
        return new OrderReturnCollection($returns);
    }

    /**
     * Store a newly created order return in storage.
     *
     * @param StoreOrderReturnRequest $request
     * @return OrderReturnResource
     */


     /**
     * @OA\Post(
     *     path="/api/order-returns",
     *     summary="Create a new order return",
     *     description="Creates a new return request for an order",
     *     tags={"Order Returns"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"order_id", "items"},
     *             @OA\Property(property="order_id", type="integer", example=123),
     *             @OA\Property(property="user_id", type="integer", example=1),
     *             @OA\Property(property="reason", type="string", example="Received wrong items"),
     *             @OA\Property(
     *                 property="items",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"order_product_id", "product_id", "product_name", "qty", "price"},
     *                     @OA\Property(property="order_product_id", type="integer", example=456),
     *                     @OA\Property(property="product_id", type="integer", example=789),
     *                     @OA\Property(property="product_name", type="string", example="Blue T-Shirt"),
     *                     @OA\Property(property="product_image", type="string", example="products/tshirt.jpg"),
     *                     @OA\Property(property="qty", type="integer", example=1),
     *                     @OA\Property(property="price", type="number", format="decimal", example=29.99),
     *                     @OA\Property(property="reason", type="string", example="Wrong size"),
     *                     @OA\Property(property="refund_amount", type="number", format="decimal", example=29.99)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Return request created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/OrderReturn")
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
    public function store(StoreOrderReturnRequest $request)
    {
        $order = Order::findOrFail($request->order_id);
        
        $orderReturn = OrderReturn::create([
            'code' => 'RET-' . Str::random(6),
            'order_id' => $order->id,
            'store_id' => $order->store_id,
            'user_id' => $request->user_id ?? auth()->id(),
            'reason' => $request->reason,
            'order_status' => $order->status,
            'return_status' => 'pending',
        ]);
        
        // Create return items
        if ($request->has('items') && is_array($request->items)) {
            foreach ($request->items as $item) {
                $orderReturn->items()->create([
                    'order_product_id' => $item['order_product_id'],
                    'product_id' => $item['product_id'],
                    'product_name' => $item['product_name'],
                    'product_image' => $item['product_image'] ?? null,
                    'qty' => $item['qty'],
                    'price' => $item['price'],
                    'reason' => $item['reason'] ?? null,
                    'refund_amount' => $item['refund_amount'] ?? ($item['price'] * $item['qty']),
                ]);
            }
        }
        
        // Create history entry for the order
        $order->histories()->create([
            'action' => 'return_request',
            'description' => 'Return request created',
            'user_id' => auth()->id(),
            'extras' => [
                'return_id' => $orderReturn->id,
                'return_code' => $orderReturn->code,
            ],
        ]);
        
        return new OrderReturnResource($orderReturn->load(['items', 'order']));
    }

    /**
     * Display the specified order return.
     *
     * @param OrderReturn $orderReturn
     * @return OrderReturnResource
     */

     /**
     * @OA\Get(
     *     path="/api/order-returns/{orderReturn}",
     *     summary="Get order return details",
     *     description="Returns detailed information for a specific order return",
     *     tags={"Order Returns"},
     *     @OA\Parameter(
     *         name="orderReturn",
     *         in="path",
     *         required=true,
     *         description="Order Return ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/OrderReturn")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Order return not found"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function show(OrderReturn $orderReturn)
    {
        return new OrderReturnResource($orderReturn->load(['items', 'order']));
    }

    /**
     * Update the specified order return in storage.
     *
     * @param UpdateOrderReturnRequest $request
     * @param OrderReturn $orderReturn
     * @return OrderReturnResource
     */


     /**
     * @OA\Put(
     *     path="/api/order-returns/{orderReturn}",
     *     summary="Update a specific order return",
     *     description="Updates the given order return with new data",
     *     tags={"Order Returns"},
     *     @OA\Parameter(
     *         name="orderReturn",
     *         in="path",
     *         required=true,
     *         description="Order Return ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/UpdateOrderReturnRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Order return updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/OrderReturn")
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
    public function update(UpdateOrderReturnRequest $request, OrderReturn $orderReturn)
    {
        $orderReturn->update($request->validated());
        
        // Create history entry for the order
        $orderReturn->order->histories()->create([
            'action' => 'return_update',
            'description' => 'Return request updated',
            'user_id' => auth()->id(),
            'extras' => [
                'return_id' => $orderReturn->id,
                'return_code' => $orderReturn->code,
                'return_status' => $orderReturn->return_status,
            ],
        ]);
        
        return new OrderReturnResource($orderReturn->load(['items', 'order']));
    }

    /**
     * Update the status of the specified order return.
     *
     * @param Request $request
     * @param OrderReturn $orderReturn
     * @return OrderReturnResource
     */

    /**
     * @OA\Patch(
     *     path="/api/order-returns/{orderReturn}/status",
     *     summary="Update the status of an order return",
     *     description="Updates only the return_status field of the order return",
     *     tags={"Order Returns"},
     *     @OA\Parameter(
     *         name="orderReturn",
     *         in="path",
     *         required=true,
     *         description="Order Return ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"return_status"},
     *             @OA\Property(property="return_status", type="string", maxLength=191)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Order return status updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/OrderReturn")
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

    public function updateStatus(Request $request, OrderReturn $orderReturn)
    {
        $request->validate([
            'return_status' => 'required|string|max:191',
        ]);
        
        $oldStatus = $orderReturn->return_status;
        $orderReturn->return_status = $request->return_status;
        $orderReturn->save();
        
        // Create history entry for the order
        $orderReturn->order->histories()->create([
            'action' => 'return_status_update',
            'description' => "Return status changed from {$oldStatus} to {$request->return_status}",
            'user_id' => auth()->id(),
            'extras' => [
                'return_id' => $orderReturn->id,
                'return_code' => $orderReturn->code,
                'old_status' => $oldStatus,
                'new_status' => $request->return_status,
            ],
        ]);
        
        return new OrderReturnResource($orderReturn->load(['items', 'order']));
    }

    /**
     * Remove the specified order return from storage.
     *
     * @param OrderReturn $orderReturn
     * @return Response
     */


     /**
     * @OA\Delete(
     *     path="/api/order-returns/{orderReturn}",
     *     summary="Delete a specific order return",
     *     description="Deletes an order return if its status is pending",
     *     tags={"Order Returns"},
     *     @OA\Parameter(
     *         name="orderReturn",
     *         in="path",
     *         required=true,
     *         description="Order Return ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Order return deleted successfully"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Cannot delete a return that is already being processed"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */

    public function destroy(OrderReturn $orderReturn)
    {
        // Check if the return can be deleted (e.g., only if it's pending)
        if ($orderReturn->return_status !== 'pending') {
            return response()->json([
                'message' => 'Cannot delete a return that is already being processed',
            ], 422);
        }
        
        // Delete related items
        $orderReturn->items()->delete();
        
        // Create history entry for the order
        $orderReturn->order->histories()->create([
            'action' => 'return_deleted',
            'description' => 'Return request deleted',
            'user_id' => auth()->id(),
            'extras' => [
                'return_id' => $orderReturn->id,
                'return_code' => $orderReturn->code,
            ],
        ]);
        
        $orderReturn->delete();
        
        return response()->json(null, 204);
    }
}