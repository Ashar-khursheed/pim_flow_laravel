<?php
// app/Http/Controllers/API/OrderController.php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Requests\Order\UpdateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\OrderCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * @OA\Schema(
 *     schema="Order",
 *     type="object",
 *     title="Order",
 *     required={"id", "code", "user_id", "store_id", "amount", "status"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="code", type="string", example="ORD-2024-0001"),
 *     @OA\Property(property="user_id", type="integer", example=42),
 *     @OA\Property(property="store_id", type="integer", example=5),
 *     @OA\Property(property="amount", type="number", format="float", example=150.75),
 *     @OA\Property(property="status", type="string", example="pending"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2024-04-01T12:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-04-02T08:30:00Z")
 * )
 */

class OrderController extends Controller
{
    /**
     * Display a listing of orders.
     *
     * @param Request $request
     * @return OrderCollection
     */

     /**
     * @OA\Get(
     *     path="/api/orders",
     *     summary="Get list of orders",
     *     description="Returns paginated list of orders with optional filters",
     *     tags={"Orders"},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         description="Filter by order status",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         required=false,
     *         description="Filter by store ID",
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
     *         name="code",
     *         in="query",
     *         required=false,
     *         description="Search by order code",
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
     *         description="Field to sort by (id, created_at, amount, status)",
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
     *                 @OA\Items(ref="#/components/schemas/Order")
     *             ),
     *             @OA\Property(
     *                 property="pagination",
     *                 type="object",
     *                 @OA\Property(property="total", type="integer", example=50),
     *                 @OA\Property(property="count", type="integer", example=15),
     *                 @OA\Property(property="per_page", type="integer", example=15),
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="total_pages", type="integer", example=4),
     *                 @OA\Property(
     *                     property="links",
     *                     type="object",
     *                     @OA\Property(property="previous", type="string", example=null),
     *                     @OA\Property(property="next", type="string", example="http://example.com/api/orders?page=2")
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
        $query = Order::with(['address', 'histories', 'returns', 'referral']);
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // Filter by store
        if ($request->has('store_id')) {
            $query->where('store_id', $request->store_id);
        }
        
        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
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
        
        // Ensure the sort field is valid
        $allowedSortFields = ['id', 'created_at', 'amount', 'status'];
        if (!in_array($sortField, $allowedSortFields)) {
            $sortField = 'created_at';
        }
        
        $query->orderBy($sortField, $sortOrder);
        
        // Paginate results
        $perPage = $request->input('per_page', 15);
        $orders = $query->paginate($perPage);
        
        return new OrderCollection($orders);
    }


    /**
     * @OA\Post(
     *     path="/api/orders",
     *     summary="Create a new order",
     *     description="Creates a new order with optional address and referral data",
     *     tags={"Orders"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"user_id", "shipping_method", "status", "amount", "sub_total"},
     *             @OA\Property(property="user_id", type="integer", example=1),
     *             @OA\Property(property="shipping_option", type="string", example="standard"),
     *             @OA\Property(property="shipping_method", type="string", example="flat_rate"),
     *             @OA\Property(property="status", type="string", example="pending"),
     *             @OA\Property(property="amount", type="number", format="decimal", example=125.50),
     *             @OA\Property(property="tax_amount", type="number", format="decimal", example=10.50),
     *             @OA\Property(property="shipping_amount", type="number", format="decimal", example=5.00),
     *             @OA\Property(property="description", type="string", example="Order from website"),
     *             @OA\Property(property="coupon_code", type="string", example="SUMMER10"),
     *             @OA\Property(property="discount_amount", type="number", format="decimal", example=10.00),
     *             @OA\Property(property="sub_total", type="number", format="decimal", example=130.00),
     *             @OA\Property(property="is_confirmed", type="boolean", example=false),
     *             @OA\Property(property="discount_description", type="string", example="10% off summer sale"),
     *             @OA\Property(property="store_id", type="integer", example=1),
     *             @OA\Property(property="payment_id", type="integer", example=null),
     *             @OA\Property(
     *                 property="address",
     *                 type="object",
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="phone", type="string", example="1234567890"),
     *                 @OA\Property(property="email", type="string", example="john@example.com"),
     *                 @OA\Property(property="country", type="string", example="USA"),
     *                 @OA\Property(property="state", type="string", example="New York"),
     *                 @OA\Property(property="city", type="string", example="New York City"),
     *                 @OA\Property(property="address", type="string", example="123 Main St"),
     *                 @OA\Property(property="zip_code", type="string", example="10001"),
     *                 @OA\Property(property="type", type="string", example="shipping_address")
     *             ),
     *             @OA\Property(
     *                 property="referral",
     *                 type="object",
     *                 @OA\Property(property="ip", type="string", example="192.168.1.1"),
     *                 @OA\Property(property="landing_domain", type="string", example="example.com"),
     *                 @OA\Property(property="landing_page", type="string", example="/products"),
     *                 @OA\Property(property="landing_params", type="string", example="source=google"),
     *                 @OA\Property(property="referral", type="string", example="affiliate123"),
     *                 @OA\Property(property="utm_source", type="string", example="google"),
     *                 @OA\Property(property="utm_medium", type="string", example="cpc"),
     *                 @OA\Property(property="utm_campaign", type="string", example="summer_sale")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Order created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Order")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="user_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The user id field is required.")
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


    /**
     * Store a newly created order in storage.
     *
     * @param StoreOrderRequest $request
     * @return OrderResource
     */
    public function store(StoreOrderRequest $request)
    {
        $order = Order::create($request->validated());
        
        // Create address if provided
        if ($request->has('address')) {
            $order->address()->create($request->address);
        }
        
        // Create history entry for order creation
        $order->histories()->create([
            'action' => 'create',
            'description' => 'Order was created',
            'user_id' => auth()->id(),
        ]);
        
        // Create referral if provided
        if ($request->has('referral')) {
            $order->referral()->create($request->referral);
        }
        
        return new OrderResource($order->load(['address', 'histories', 'referral']));
    }



    /**
     * Display the specified order.
     *
     * @param Order $order
     * @return OrderResource
     */

     /**
     * @OA\Get(
     *     path="/api/orders/{order}",
     *     summary="Get order details",
     *     description="Returns detailed information for a specific order",
     *     tags={"Orders"},
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
     *         @OA\JsonContent(ref="#/components/schemas/Order")
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
    public function show(Order $order)
    {
        return new OrderResource($order->load(['address', 'histories', 'returns.items', 'referral']));
    }

    /**
     * Update the specified order in storage.
     *
     * @param UpdateOrderRequest $request
     * @param Order $order
     * @return OrderResource
     */

     /**
     * @OA\Put(
     *     path="/api/orders/{order}",
     *     summary="Update an existing order",
     *     description="Updates an order's information",
     *     tags={"Orders"},
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
     *             @OA\Property(property="shipping_option", type="string", example="express"),
     *             @OA\Property(property="shipping_method", type="string", example="flat_rate"),
     *             @OA\Property(property="status", type="string", example="processing"),
     *             @OA\Property(property="amount", type="number", format="decimal", example=125.50),
     *             @OA\Property(property="tax_amount", type="number", format="decimal", example=10.50),
     *             @OA\Property(property="shipping_amount", type="number", format="decimal", example=5.00),
     *             @OA\Property(property="description", type="string", example="Updated order from website"),
     *             @OA\Property(property="coupon_code", type="string", example="SUMMER10"),
     *             @OA\Property(property="discount_amount", type="number", format="decimal", example=10.00),
     *             @OA\Property(property="sub_total", type="number", format="decimal", example=130.00),
     *             @OA\Property(property="is_confirmed", type="boolean", example=true),
     *             @OA\Property(property="discount_description", type="string", example="10% off summer sale"),
     *             @OA\Property(property="payment_id", type="integer", example=123),
     *             @OA\Property(
     *                 property="address",
     *                 type="object",
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="phone", type="string", example="1234567890"),
     *                 @OA\Property(property="email", type="string", example="john@example.com"),
     *                 @OA\Property(property="country", type="string", example="USA"),
     *                 @OA\Property(property="state", type="string", example="New York"),
     *                 @OA\Property(property="city", type="string", example="New York City"),
     *                 @OA\Property(property="address", type="string", example="123 Main St"),
     *                 @OA\Property(property="zip_code", type="string", example="10001"),
     *                 @OA\Property(property="type", type="string", example="shipping_address")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Order updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Order")
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
    public function update(UpdateOrderRequest $request, Order $order)
    {
        $order->update($request->validated());
        
        // Update address if provided
        if ($request->has('address') && $order->address) {
            $order->address->update($request->address);
        } elseif ($request->has('address')) {
            $order->address()->create($request->address);
        }
        
        // Create history entry for order update
        $order->histories()->create([
            'action' => 'update',
            'description' => 'Order was updated',
            'user_id' => auth()->id(),
        ]);
        
        return new OrderResource($order->load(['address', 'histories', 'returns.items', 'referral']));
    }

    /**
     * Update the order status.
     *
     * @param Request $request
     * @param Order $order
     * @return OrderResource
     */
    /**
     * @OA\Patch(
     *     path="/api/orders/{order}/status",
     *     summary="Update order status",
     *     description="Updates the status of an existing order",
     *     tags={"Orders"},
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
     *             required={"status"},
     *             @OA\Property(property="status", type="string", example="processing"),
     *             @OA\Property(property="description", type="string", example="Order has been processed and is being prepared for shipping")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Status updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Order")
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
    public function updateStatus(Request $request, Order $order)
    {
        $request->validate([
            'status' => 'required|string|max:120',
            'description' => 'nullable|string|max:400',
        ]);
        
        $oldStatus = $order->status;
        $order->status = $request->status;
        
        // Mark as completed if the status is "completed"
        if ($request->status === 'completed' && !$order->completed_at) {
            $order->completed_at = now();
            $order->is_finished = true;
        }
        
        $order->save();
        
        // Create history entry for status change
        $order->histories()->create([
            'action' => 'status_update',
            'description' => $request->description ?? "Order status changed from {$oldStatus} to {$request->status}",
            'user_id' => auth()->id(),
            'extras' => [
                'old_status' => $oldStatus,
                'new_status' => $request->status,
            ],
        ]);
        
        return new OrderResource($order->load(['address', 'histories', 'returns.items', 'referral']));
    }

    /**
     * Cancel the specified order.
     *
     * @param Request $request
     * @param Order $order
     * @return OrderResource
     */

     /**
     * @OA\Post(
     *     path="/api/orders/{order}/cancel",
     *     summary="Cancel an order",
     *     description="Cancels an existing order and records the cancellation reason",
     *     tags={"Orders"},
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
     *             required={"cancellation_reason"},
     *             @OA\Property(property="cancellation_reason", type="string", example="Customer request"),
     *             @OA\Property(property="cancellation_reason_description", type="string", example="Customer found a better price elsewhere")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Order cancelled successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Order")
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
    public function cancel(Request $request, Order $order)
    {
        $request->validate([
            'cancellation_reason' => 'required|string|max:191',
            'cancellation_reason_description' => 'nullable|string|max:191',
        ]);
        
        $order->status = 'canceled';
        $order->cancellation_reason = $request->cancellation_reason;
        $order->cancellation_reason_description = $request->cancellation_reason_description;
        $order->save();
        
        // Create history entry for cancellation
        $order->histories()->create([
            'action' => 'cancel',
            'description' => "Order was canceled. Reason: {$request->cancellation_reason}",
            'user_id' => auth()->id(),
            'extras' => [
                'cancellation_reason' => $request->cancellation_reason,
                'cancellation_reason_description' => $request->cancellation_reason_description,
            ],
        ]);
        
        return new OrderResource($order->load(['address', 'histories', 'returns.items', 'referral']));
    }

    /**
     * Remove the specified order from storage.
     *
     * @param Order $order
     * @return Response
     */

     /**
     * @OA\Delete(
     *     path="/api/orders/{order}",
     *     summary="Delete an order",
     *     description="Deletes an order and all related records",
     *     tags={"Orders"},
     *     @OA\Parameter(
     *         name="order",
     *         in="path",
     *         required=true,
     *         description="Order ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Order deleted successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Order not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Cannot delete an order that is in progress or completed"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function destroy(Order $order)
    {
        // Optional: Check if the order can be deleted (e.g., not completed or in progress)
        if (in_array($order->status, ['completed', 'processing', 'shipping'])) {
            return response()->json([
                'message' => 'Cannot delete an order that is in progress or completed',
            ], 422);
        }
        
        // Delete related records
        $order->address()->delete();
        $order->histories()->delete();
        $order->referral()->delete();
        
        // Delete returns and return items
        foreach ($order->returns as $return) {
            $return->items()->delete();
            $return->delete();
        }
        
        $order->delete();
        
        return response()->json(null, 204);
    }
}
