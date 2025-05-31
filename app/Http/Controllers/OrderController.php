<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\OrderTracking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/orders",
     *     summary="Get all orders with pagination and filters",
     *     tags={"Orders"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by order status",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="payment_status",
     *         in="query",
     *         description="Filter by payment status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"Paid", "Unpaid", "Partially Paid"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Orders retrieved successfully"
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = Order::with(['items', 'payments', 'shipments']);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('payment_status')) {
            switch ($request->payment_status) {
                case 'Paid':
                    $query->whereColumn('paid_amount', '>=', 'total_amount');
                    break;
                case 'Unpaid':
                    $query->where('paid_amount', 0);
                    break;
                case 'Partially Paid':
                    $query->where('paid_amount', '>', 0)
                          ->whereColumn('paid_amount', '<', 'total_amount');
                    break;
            }
        }

        $orders = $query->latest()->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $orders->items(),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'total_pages' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ]
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/orders",
     *     summary="Create a new order",
     *     tags={"Orders"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"customer_name", "customer_email", "customer_phone", "address", "city", "country", "items"},
     *             @OA\Property(property="customer_name", type="string"),
     *             @OA\Property(property="customer_email", type="string", format="email"),
     *             @OA\Property(property="customer_phone", type="string"),
     *             @OA\Property(property="company", type="string"),
     *             @OA\Property(property="address", type="string"),
     *             @OA\Property(property="city", type="string"),
     *             @OA\Property(property="country", type="string"),
     *             @OA\Property(property="ship_all_at_once", type="boolean"),
     *             @OA\Property(property="separate_deliveries", type="boolean"),
     *             @OA\Property(
     *                 property="items",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="item_id", type="string"),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="quantity", type="integer"),
     *                     @OA\Property(property="supplier", type="string"),
     *                     @OA\Property(property="unit_price", type="number"),
     *                     @OA\Property(property="image_url", type="string")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Order created successfully"
     *     )
     * )
     */

    public function store(Request $request)
    {
        Log::info('Order store method called.');
        Log::info('Request data:', $request->all());
    
        $request->validate([
            // your validation rules...
        ]);
    
        DB::beginTransaction();
    
        try {
            Log::info('Validation passed.');
    
            $totalAmount = 0;
            $totalProducts = count($request->items);
    
            foreach ($request->items as $item) {
                $totalAmount += $item['quantity'] * $item['unit_price'];
            }
    
            Log::info("Calculated totalAmount: $totalAmount, totalProducts: $totalProducts");
    
            $order = Order::create([
                'order_number' => 'ORD-' . strtoupper(Str::random(8)),
                'order_date' => now(),
                'order_time' => now()->format('H:i:s'),
                'customer_name' => $request->customer_name,
                'customer_email' => $request->customer_email,
                'customer_phone' => $request->customer_phone,
                'company' => $request->company,
                'address' => $request->address,
                'city' => $request->city,
                'country' => $request->country,
                'total_amount' => $totalAmount,
                'total_products' => $totalProducts,
                'pending_amount' => $totalAmount,
                'ship_all_at_once' => $request->get('ship_all_at_once', true),
                'separate_deliveries' => $request->get('separate_deliveries', false),
            ]);
    
            Log::info('Order created:', ['order_id' => $order->id]);
    
            // Create order items
            foreach ($request->items as $item) {
                $itemTotal = $item['quantity'] * $item['unit_price'];
                OrderItem::create([
                    'order_id' => $order->id,
                    'item_id' => $item['item_id'],
                    'name' => $item['name'],
                    'quantity' => $item['quantity'],
                    'remaining_quantity' => $item['quantity'],
                    'supplier' => $item['supplier'],
                    'unit_price' => $item['unit_price'],
                    'total_amount' => $itemTotal,
                    'image_url' => $item['image_url'] ?? null,
                ]);
            }
            Log::info('Order items created.');
    
            OrderTracking::create([
                'order_id' => $order->id,
                'status' => 'Order Created',
                'description' => 'Order has been successfully created',
                'tracked_at' => now(),
            ]);
    
            Log::info('Order tracking created.');
    
            DB::commit();
    
            Log::info('Transaction committed.');
    
            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'data' => $order->load(['items', 'payments', 'tracking'])
            ], 201);
    
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create order:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create order: ' . $e->getMessage()
            ], 500);
        }
    }
    

    /**
     * @OA\Get(
     *     path="/api/orders/{id}",
     *     summary="Get order details",
     *     tags={"Orders"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Order ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Order details retrieved successfully"
     *     )
     * )
     */
    public function show($id)
    {
        $order = Order::with([
            'items',
            'payments',
            'shipments.items.orderItem',
            'tracking'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $order
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/orders/{id}/status",
     *     summary="Update order status",
     *     tags={"Orders"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Order ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(property="status", type="string"),
     *             @OA\Property(property="notes", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Order status updated successfully"
     *     )
     * )
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|string|in:Pending,Confirmed,Supplier Delivery,International,Export,On hold,Ready to ship,Pickups,Out for delivery,Delivered,Re-Attempt,Returned,Cancelled',
            'notes' => 'nullable|string'
        ]);

        $order = Order::findOrFail($id);
        $oldStatus = $order->status;
        
        $order->update(['status' => $request->status]);

        // Add tracking entry
        OrderTracking::create([
            'order_id' => $order->id,
            'status' => $request->status,
            'description' => $request->notes ?? "Order status changed from {$oldStatus} to {$request->status}",
            'tracked_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Order status updated successfully',
            'data' => $order->fresh(['tracking'])
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/orders/{id}/items/{item_id}/status",
     *     summary="Update specific item status",
     *     tags={"Orders"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Order ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="item_id",
     *         in="path",
     *         description="Order Item ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(property="status", type="string"),
     *             @OA\Property(property="notes", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Item status updated successfully"
     *     )
     * )
     */
    public function updateItemStatus(Request $request, $id, $itemId)
    {
        $request->validate([
            'status' => 'required|string|in:Pending,Confirmed,Supplier Delivery,International,Export,On hold,Ready to ship,Pickups,Out for delivery,Delivered,Re-Attempt,Returned,Cancelled,Out of Stock',
            'notes' => 'nullable|string'
        ]);

        $order = Order::findOrFail($id);
        $item = $order->items()->findOrFail($itemId);
        
        $oldStatus = $item->status;
        $item->update(['status' => $request->status]);

        // Add tracking entry
        OrderTracking::create([
            'order_id' => $order->id,
            'status' => "Item Status Updated",
            'description' => $request->notes ?? "Item '{$item->name}' status changed from {$oldStatus} to {$request->status}",
            'tracked_at' => now(),
            'metadata' => [
                'item_id' => $item->id,
                'item_name' => $item->name,
                'old_status' => $oldStatus,
                'new_status' => $request->status
            ]
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Item status updated successfully',
            'data' => $item->fresh()
        ]);
    }
    /**
     * @OA\Post(
     *     path="/api/orders/{id}/shipments",
     *     summary="Create a shipment for an order (supports partial delivery)",
     *     tags={"Orders"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Order ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"items"},
     *             @OA\Property(
     *                 property="items",
     *                 type="array",
     *                 @OA\Items(
     *                     required={"order_item_id", "quantity"},
     *                     @OA\Property(property="order_item_id", type="integer"),
     *                     @OA\Property(property="quantity", type="integer")
     *                 )
     *             ),
     *             @OA\Property(property="tracking_number", type="string"),
     *             @OA\Property(property="carrier", type="string"),
     *             @OA\Property(property="notes", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Shipment created successfully"
     *     )
     * )
     */
    public function createShipment(Request $request, $id)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.order_item_id' => 'required|integer|exists:order_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'tracking_number' => 'nullable|string|max:255',
            'carrier' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:500',
        ]);

        $order = Order::with('items')->findOrFail($id);

        DB::beginTransaction();

        try {
            // Create the shipment
            $shipment = Shipment::create([
                'order_id' => $order->id,
                'shipment_date' => now(),
                'tracking_number' => $request->tracking_number,
                'carrier' => $request->carrier,
                'notes' => $request->notes,
            ]);

            foreach ($request->items as $item) {
                $orderItem = OrderItem::where('id', $item['order_item_id'])
                                    ->where('order_id', $order->id)
                                    ->firstOrFail();

                if ($item['quantity'] > $orderItem->remaining_quantity) {
                    throw new \Exception("Cannot ship more than remaining quantity for item ID {$orderItem->id}");
                }

                // Create shipment item
                ShipmentItem::create([
                    'shipment_id' => $shipment->id,
                    'order_item_id' => $orderItem->id,
                    'quantity' => $item['quantity'],
                ]);

                // Update remaining quantity
                $orderItem->remaining_quantity -= $item['quantity'];
                $orderItem->save();
            }

            // Check if all items are shipped
            $allShipped = $order->items->every(fn($item) => $item->remaining_quantity <= 0);
            if ($allShipped) {
                $order->status = 'Delivered';
            } else {
                $order->status = 'Partially Delivered';
            }
            $order->save();

            // Add tracking record
            OrderTracking::create([
                'order_id' => $order->id,
                'status' => $order->status,
                'description' => $request->notes ?? 'Shipment created with tracking number: ' . $request->tracking_number,
                'tracked_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Shipment created successfully',
                'data' => $shipment->load('items.orderItem')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create shipment: ' . $e->getMessage()
            ], 500);
        }
    }
}