<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\BaseController;
use App\Models\FrontEnd\Order;
use App\Models\FrontEnd\OrderProduct;
use App\Models\FrontEnd\OrderTracking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController extends BaseController
{
	/**
	 * @OA\Post(
	 *     path="/api/frontend/orders",
	 *     summary="Create a new order",
	 *     tags={"FrontEnd-Order"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"customer_id", "customer_address", "shipping_charge", "products"},
	 *             @OA\Property(property="customer_id", type="integer", example=1),
	 *             @OA\Property(property="customer_address", type="string", example="123 Main St, City, Country"),
	 *             @OA\Property(property="shipping_charge", type="number", format="float", example=50.00),
	 *             @OA\Property(property="ship_all_at_once", type="boolean", example=true),
	 *             @OA\Property(property="separate_deliveries", type="boolean", example=false),
	 *             @OA\Property(
	 *                 property="products",
	 *                 type="array",
	 *                 @OA\Items(
	 *                     required={"product_id", "vendor_id", "quantity", "unit_price"},
	 *                     @OA\Property(property="product_id", type="integer", example=101),
	 *                     @OA\Property(property="vendor_id", type="integer", example=22),
	 *                     @OA\Property(property="quantity", type="integer", example=5),
	 *                     @OA\Property(property="unit_price", type="number", format="float", example=199.99)
	 *                 )
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=201, description="Order created successfully", @OA\MediaType(mediaType="application/json")),
	 * )
	 */
	public function store(Request $request)
	{
		$request->validate([
			// 'customer_id' => 'required|integer|exists:customers,id',
			'customer_id' => 'required|integer',
			'customer_address' => 'required|string|max:1000',
			'shipping_charge' => 'required|numeric|min:0',
			'ship_all_at_once' => 'nullable|boolean',
			'separate_deliveries' => 'nullable|boolean',
			'products' => 'required|array|min:1',
			'products.*.product_id' => 'required|integer|exists:ec_products,id',
			'products.*.vendor_id' => 'required|integer|exists:vendors,id',
			'products.*.quantity' => 'required|integer|min:1',
			'products.*.unit_price' => 'required|numeric|min:0',
		]);

		DB::beginTransaction();

		try {
			$totalProducts = 0;
			$totalAmount = 0;

			foreach ($request->products as $product) {
				$totalProducts += $product['quantity'];
				$totalAmount += $product['quantity'] * $product['unit_price'];
			}


			$order = Order::create([
				'order_number' => 'ORD-' . strtoupper(Str::random(8)),
				'customer_id' => $request->customer_id,
				'customer_address' => $request->customer_address,
				'shipping_charge' => $request->shipping_charge,
				'total_amount' => $totalAmount,
				'total_products' => $totalProducts,
				'ship_all_at_once' => $request->get('ship_all_at_once', true),
				'separate_deliveries' => $request->get('separate_deliveries', false),
				'is_paid' => false,
				'paid_amount' => 0,
				'pending_amount' => $totalAmount,
				'status' => 'Pending',
				'created_by' => 0,
				'updated_by' => null,
			]);

			foreach ($request->products as $product) {
				$total = $product['quantity'] * $product['unit_price'];
				OrderProduct::create([
					'order_id' => $order->id,
					'product_id' => $product['product_id'],
					'vendor_id' => $product['vendor_id'],
					'quantity' => $product['quantity'],
					'shipped_quantity' => 0,
					'remaining_quantity' => $product['quantity'],
					'unit_price' => $product['unit_price'],
					'total_amount' => $total,
					'status' => 'Pending',
				]);
			}

			OrderTracking::create([
				'order_id' => $order->id,
				'status' => 'Order Created',
				'description' => 'Order has been successfully created',
				'tracked_at' => now(),
			]);

			DB::commit();

			return response()->json([
				'success' => true,
				'message' => 'Order created successfully',
				'data' => $order->load(['products', 'tracking'])
			], 201);

		} catch (\Exception $e) {
			DB::rollBack();

			return response()->json([
				'success' => false,
				'message' => 'Failed to create order: ' . $e->getMessage()
			], 500);
		}
	}
}