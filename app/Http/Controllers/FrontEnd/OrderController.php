<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\BaseController;

use App\Models\FrontEnd\Order;
use App\Models\FrontEnd\OrderProduct;
use App\Models\FrontEnd\OrderTracking;
use App\Models\FrontEnd\CustomerAddress;
use App\Models\FrontEnd\Finance;
use App\Models\FrontEnd\FinancesPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Utm;
use Illuminate\Support\Facades\Bus;
use Illuminate\Bus\Batch;

use App\Jobs\Order\OrderPlacedMailJob;
use App\Jobs\Order\OrderCancelledMailJob;

class OrderController extends BaseController
{
	/**
	 * @OA\Get(
	 *     path="/api/frontend/orders",
	 *     summary="Get all orders with pagination and filters",
	 *     tags={"FrontEnd-Orders"},
	 *     @OA\Parameter(name="page", in="query", description="Page number for pagination", example=1, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="length", in="query", description="Number of records per page.", example=20, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="status", in="query", description="Filter by order status.", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="payment_status", in="query", description="Filter by payment status.", @OA\Schema(type="string", enum={"Paid", "Unpaid", "Partially Paid"})),
	 *     @OA\Parameter(name="global", in="query", description="Global search for all fields", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="from_date", in="query", @OA\Schema(type="string", format="date")),
	 *     @OA\Parameter(name="to_date", in="query", @OA\Schema(type="string", format="date")),
	 *     @OA\Parameter(name="sort_by", in="query", description="Column name to sort by", @OA\Schema(type="string", enum={"id", "order_number", "shipping_charge", "total_amount", "total_products", "created_at", "updated_at"})),
	 *     @OA\Parameter(name="sort_dir", in="query", description="Sort direction (asc or desc)", example="asc", @OA\Schema(type="string", enum={"asc", "desc"})),
	 *     @OA\Response(response=200, description="List retrieved successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function index(Request $request)
	{
		$searchableColumns = ['id', 'order_number'];
		$sortableColumns = array_merge($searchableColumns, ['shipping_charge', 'total_amount', 'total_products', 'created_at', 'updated_at']);

		$sortBy = in_array($request->input('sort_by'), $sortableColumns) ? $request->input('sort_by') : 'id';
		$sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

		$recordsQuery = Order::where('customer_id', auth()->id());
		/* Check if pagination requested */
		if ($request->filled('page') && $request->filled('length')) {
			/* Eager load relationships */
			$recordsQuery->with([
				'orderProducts:id,order_id,product_id,vendor_id,quantity,unit_price,amount,shipping_charge,total_amount,status,accessory_item_charge',
				'orderProducts.accessoryCharges:id,relation_type,relation_id,accessory_item_id,amount',
				'orderProducts.accessoryCharges.accessoryItem:id,product_accessory_id,name,price',
				'orderProducts.accessoryCharges.accessoryItem.accessory:id,name',
				'orderProducts.product:id,name,images,sku,brand_id,currency_id,barcode',
				'orderProducts.product.brand:id,name',
				'orderProducts.product.currency:id,symbol',
				'payments:id,order_id,transaction_id,payment_mode,amount,status,notes,created_at',
				'shipments',
			]);

			/* Filter by status */
			if ($request->has('status')) {
				$recordsQuery->where('orders.status', $request->status);
			}

			if ($request->has('from_date') && $request->has('to_date')) {
				$from = $request->from_date . ' 00:00:00';
				$to = $request->to_date . ' 23:59:59';
				$recordsQuery->whereBetween('orders.created_at', [$from, $to]);
			} elseif ($request->has('from_date')) {
				$from = $request->from_date . ' 00:00:00';
				$recordsQuery->where('orders.created_at', '>=', $from);
			} elseif ($request->has('to_date')) {
				$to = $request->to_date . ' 23:59:59';
				$recordsQuery->where('orders.created_at', '<=', $to);
			}

			/* Filter by payment status */
			if ($request->has('payment_status')) {
				switch ($request->payment_status) {
					case 'Paid':
					$recordsQuery->whereColumn('orders.paid_amount', '>=', 'orders.total_amount');
					break;
					case 'Unpaid':
					$recordsQuery->where('orders.paid_amount', 0);
					break;
					case 'Partially Paid':
					$recordsQuery->where('orders.paid_amount', '>', 0)
					->whereColumn('orders.paid_amount', '<', 'orders.total_amount');
					break;
				}
			}

			/* Global search */
			if ($request->filled('global')) {
				$search = $request->input('global');
				$recordsQuery->where(function ($q) use ($searchableColumns, $search) {
					foreach ($searchableColumns as $col) {
						$q->orWhere("orders.$col", 'like', '%' . $search . '%');
					}
				});
			}

			/* Sorting */
			$recordsQuery->orderBy($sortBy, $sortDir);

			/* Pagination */
			$length = (int) $request->input('length');
			$page = (int) $request->input('page');

			$totalRecords = (clone $recordsQuery)->count();
			$totalPages = (int) ceil($totalRecords / $length);

			if ($page > $totalPages && $totalPages > 0) {
				$page = 1;
			}

			$records = $recordsQuery
			->offset(($page - 1) * $length)
			->limit($length)
			->get();

			/* Transform results */
			$records->transform(function ($record) {
				/* Process each product in order products */
				foreach ($record->orderProducts as $orderProduct) {
					$product = $orderProduct->product;
					if ($product) {
						$product->images = is_array($product->images) ? $product->images : (is_array($decoded = json_decode($product->images, true)) ? $decoded : null);
						$product->brand_name = $product->brand->name ?? null;
						$product->currency_symbol = $product->currency->symbol ?? null;
						unset($product->brand, $product->currency);
					}
					$orderProduct->product_supplier = optional($orderProduct->vendor_product_supplier)->only(['price', 'sale_price', 'shipping_charge', 'delivery_days', 'return_policy']);
					$orderProduct->expectedShippingDate = $orderProduct->product_supplier
					? getDateRange($record->created_at, $orderProduct->product_supplier['delivery_days'])
					: null;

					if ($orderProduct->accessoryCharges) {
						$orderProduct->accessory_charges = $orderProduct->accessoryCharges->map(function ($charge) {
							return [
								'id' => $charge->id,
								'accessory_item_id' => $charge->accessory_item_id,
								'accessory_item_name' => $charge->accessoryItem->name ?? null,
								'accessory_item_price' => $charge->accessoryItem->price ?? null,
								'product_accessory_id' => $charge->accessoryItem->accessory->id ?? null,
								'product_accessory_name' => $charge->accessoryItem->accessory->name ?? null,
								'amount' => $charge->amount,
							];
						});

						unset($orderProduct->accessoryCharges);
					}
				}
				foreach (['amount', 'tax_amount', 'discount', 'additional_discount', 'cheque_discount', 'total_amount', 'paid_amount', 'pending_amount'] as $key) {
					if (isset($record->$key)) {
						$record->$key = number_format($record->$key, 2, '.', '');
					}
				}

				return $record;
			});
		} else {
			/* No pagination: just fetch id and order_number */
			$records = Order::orderBy('order_number', 'asc')->get(['id', 'order_number']);
			$totalRecords = $records->count();
			$totalPages = 1;
		}

		return response()->json([
			'success' => true,
			'message' => __('msg_rec_list'),
			'data' => $records,
			'total_pages' => $totalPages,
			'total_records' => $totalRecords,
		]);
	}

	/**
	 * @OA\Post(
	 *     path="/api/frontend/orders",
	 *     summary="Create a new order",
	 *     tags={"FrontEnd-Orders"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="multipart/form-data",
	 *             @OA\Schema(
	 *                 required={"customer_address_id", "tax_percentage", "products"},
	 *                 @OA\Property(property="customer_address_id", type="integer", example=1, description="Customer address ID"),
	 *                 @OA\Property(property="is_lift_gate", type="boolean", example=true, description="Lift gate required"),
	 *                 @OA\Property(property="is_residential_address", type="boolean", example=true, description="Residential address"),
	 *                 @OA\Property(property="is_inside_delivery", type="boolean", example=true, description="Inside delivery required"),
	 *                 @OA\Property(property="tax_percentage", type="number", format="float", example=5, description="Tax percentage"),
	 *                 @OA\Property(property="ship_all_at_once", type="boolean", example=true, description="Ship all items together"),
	 *                 @OA\Property(property="separate_deliveries", type="boolean", example=false, description="Separate deliveries"),
	 *                 @OA\Property(property="is_cod", type="boolean", example=false, description="Cash on delivery"),
	 *                 @OA\Property(property="pay_with_cheque", type="boolean", example=false, description="Pay with cheque"),
	 *                 @OA\Property(property="cheque_img", type="string", format="binary", description="Cheque image (jpeg, png, webp only, max 5 MB)"),
	 *                 @OA\Property(property="cheque_img_back", type="string", format="binary", description="Cheque image (jpeg, png, webp only, max 5 MB)"),
	 *                 @OA\Property(property="coupon_id", type="integer", example=1, description="Coupon ID"),
	 *                 @OA\Property(property="discount", type="number", format="float", example=200, description="Discount amount"),
	 *                 @OA\Property(property="is_reserved", type="boolean", example=false, description="Reserved order"),
	 *                 @OA\Property(property="is_payment", type="boolean", example=false, description="Payment gateway"),
	 *                 @OA\Property(property="is_paymob", type="boolean", example=false, description="Paymob payment"),
	 *                 @OA\Property(property="is_squarePayment", type="boolean", example=false, description="Square payment"),
	 *                 @OA\Property(property="is_customer_pickup", type="boolean", example=false, description="Customer pickup"),
	 *                 @OA\Property(
	 *                     property="products",
	 *                     type="array",
	 *                     description="Array of products to order",
	 *                     @OA\Items(
	 *                         required={"product_id", "vendor_id", "quantity"},
	 *                         @OA\Property(property="product_id", type="integer", example=101, description="Product ID"),
	 *                         @OA\Property(property="vendor_id", type="integer", example=22, description="Vendor ID"),
	 *                         @OA\Property(property="quantity", type="integer", example=5, description="Product quantity"),
	 *                         @OA\Property(
	 *                             property="accessory_item_ids",
	 *                             type="array",
	 *                             description="Array of accessory item IDs",
	 *                             @OA\Items(type="integer", example=50)
	 *                         )
	 *                     )
	 *                 )
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=201, description="Created successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function store(Request $request)
	{
		$request->validate([
			'customer_address_id' => 'required|integer|exists:customer_addresses,id',
			'is_lift_gate' => 'nullable|boolean',
			'is_residential_address' => 'nullable|boolean',
			'is_inside_delivery' => 'nullable|boolean',
			'tax_percentage' => 'required|numeric|min:0',
			'ship_all_at_once' => 'nullable|boolean',
			'separate_deliveries' => 'nullable|boolean',
			'is_cod' => 'nullable|boolean',

			'pay_with_cheque' => 'nullable|boolean',
			'pay_with_netTerm' => 'nullable|boolean',
			'cheque_img' => 'required_if:pay_with_cheque,1|file|mimes:jpeg,jpg,png,webp|max:5024',
			'cheque_img_back' => 'nullable|file|mimes:jpeg,jpg,png,webp|max:5024',


			'coupon_id' => 'nullable|integer',
			'discount' => 'nullable|numeric|min:0',
			'is_reserved' => 'nullable|boolean',
			'is_payment' => 'nullable|boolean',
			'is_paymob' => 'nullable|boolean',
			'is_squarePayment' => 'nullable|boolean',
			'is_customer_pickup' => 'nullable|boolean',
			'products' => 'required|array|min:1',
			'products.*.product_id' => 'required|integer|exists:ec_products,id',
			'products.*.vendor_id' => 'required|integer|exists:vendors,id',
			'products.*.quantity' => 'required|integer|min:1',
			'products.*.accessory_item_ids' => 'nullable|array',
			'products.*.accessory_item_ids.*' => 'integer|exists:accessory_items,id',

		]);

		$customerId = auth()->id();
		$address = CustomerAddress::where('id', $request->customer_address_id)->where('customer_id', $customerId)->first();

		if (!$address) {
			return response()->json([
				'success' => false,
				'message' => 'The selected address does not belong to the customer.'
			], 422);
		}

		DB::beginTransaction();

		try {
			$specificShipping = in_array(config('app.website'), ['US', 'US_T']) ? ($address->state === 'Texas' ? 99 : 199) : 0;

			/* Collect all product supplier details in one go */
			$productDetails = [];
			foreach ($request->products as $product) {
				$fetchedDetail = productSupplierDetail($product['product_id'], $product['vendor_id']);
				if (!$fetchedDetail) {
					throw new \Exception("Product supplier not found for Product {$product['product_id']} & Vendor {$product['vendor_id']}");
				}
				$accessoryIds = $product['accessory_item_ids'] ?? [];
				$accessoryItems = getAccessoryItemIDPrice($accessoryIds);
				$accessoryPriceSum = array_sum(array_column($accessoryItems, 'price'));

				$charge = empty($fetchedDetail->shipping_charge) ? $specificShipping : $fetchedDetail->shipping_charge;
				$shipping = $request->boolean('is_customer_pickup') ? 0 : ($charge * $product['quantity']);
				$productDetails[] = [
					'product_id' => $product['product_id'],
					'vendor_id' => $product['vendor_id'],
					'quantity' => $product['quantity'],
					'unit_price' => $fetchedDetail->unit_price,
					'accessoryItems' => $accessoryItems,
					'accessory_item_charge'=> $accessoryPriceSum * $product['quantity'],
					'shipping_charge' => $shipping,
				];
			}

			$payWithCheque = $request->boolean('pay_with_cheque', false);
			$discount = $request->discount ?? 0;
			$totalProducts = 0;
			$orderAmount = 0;
			$orderShipping = 0;

			foreach ($productDetails as $product) {
				$totalProducts += $product['quantity'];
				$orderAmount += ($product['quantity'] * $product['unit_price']) + $product['accessory_item_charge'];
				$orderShipping += $product['shipping_charge'];
			}

			$discountedAmount = $orderAmount - $discount;

			/* Handle cheque payment discount */
			if ($payWithCheque) {
				$chequeImg = uploadImageToWebpS3FromFile(
					$request,
					'cheque_img',
					env('STORAGE_ENV') . '/customer/orders'
				);
				$chequeImgBack = uploadImageToWebpS3FromFile(
					$request,
					'cheque_img_back',
					env('STORAGE_ENV') . '/customer/orders'
				);
				$cartCreatedByStaff = auth()->user()->customerCarts()->where('created_by', '>', 0)->exists();
				$chequeDiscountPercentage = $cartCreatedByStaff ? 0 : cheque_discount_percentage();
				$chequeDiscount = round($discountedAmount * $chequeDiscountPercentage / 100, 2);
				$discountedAmount -= $chequeDiscount;
			} else {
				$chequeImg = null;
				$chequeImgBack = null;
				$chequeDiscountPercentage = 0;
				$chequeDiscount = 0;
			}

			$paynetTerm = $request->boolean('pay_with_netTerm', false);
			if (!empty($paynetTerm)) {
				$this->byNetTermpayment($orderAmount);				 
			}
			$discountedAmount += $request->boolean('is_lift_gate') ? 75 : 0;
			$discountedAmount += $request->boolean('is_residential_address') ? 199 : 0;
			$discountedAmount += $request->boolean('is_inside_delivery') ? 249 : 0;

			$customer = auth()->user();
			$taxPercentage = $customer->is_tax_free ? 0 : $request->tax_percentage;

			if (in_array(config('app.website'), ['UAE', 'UAE_T'])) {
				$taxAmount = round($discountedAmount * ($taxPercentage / 100), 2);
				$orderShipping = (($discountedAmount + $taxAmount) < 300) ? 25 : 0;
				$totalAmount = $discountedAmount + $taxAmount + $orderShipping;
			} elseif (in_array(config('app.website'), ['US', 'US_T'])) {
				$taxableAmount = $discountedAmount + $orderShipping;
				$taxAmount = round($taxableAmount * ($taxPercentage / 100), 2);
				$totalAmount = $discountedAmount + $orderShipping + $taxAmount;
			} else {
				$taxAmount = round($discountedAmount * ($taxPercentage / 100), 2);
				$totalAmount = $discountedAmount + $taxAmount + $orderShipping;
			}

			/* Get the latest order by ID (most recent) */
			$latestOrder = Order::orderBy('order_number', 'desc')->first();

			/* Generate the next order number */
			if ($latestOrder && is_numeric($latestOrder->order_number)) {
				$orderNumber = (int) $latestOrder->order_number + 1;
			} else {
				$orderNumber = in_array(config('app.website'), ['US', 'US_T']) ? 10001 : (in_array(config('app.website'), ['UAE', 'UAE_T']) ? 1001 : 101);
			}

			$order = Order::create([
				'order_number' => $orderNumber,
				'customer_id' => $customerId,
				'customer_address_id' => $request->customer_address_id,
				'is_lift_gate' => $request->is_lift_gate,
				'is_residential_address' => $request->is_residential_address,
				'is_inside_delivery' => $request->is_inside_delivery,
				'amount' => $orderAmount,

				'pay_with_cheque' => $payWithCheque,
				'cheque_discount_percentage' => $chequeDiscountPercentage,
				'cheque_discount' => $chequeDiscount,
				'cheque_img' => $chequeImg,
				'cheque_img_back' => $chequeImgBack,

				'coupon_id' => $request->coupon_id ?? null,
				'discount' => $discount,

				'tax_percentage' => $taxPercentage,
				'tax_amount' => $taxAmount,
				'shipping_charge' => $orderShipping,

				'total_amount' => $totalAmount,
				'total_products' => $totalProducts,
				'ship_all_at_once' => $request->get('ship_all_at_once', true),
				'separate_deliveries' => $request->get('separate_deliveries', false),
				'pending_amount' => $totalAmount,
				'status' => 'Pending',
				'is_reserved' => $request->boolean('is_reserved'),
				'is_payment' => $request->boolean('is_payment'),
				'is_paymob' => $request->boolean('is_paymob'),
				'is_squarePayment' => $request->boolean('is_squarePayment'),
				'is_customer_pickup' => $request->boolean('is_customer_pickup'),
				'is_cod' => $request->boolean('is_cod'),
				'created_by' => 0,
				'utm_id' => $request->utm_id,
			]);

			foreach ($productDetails as $product) {
				$total = $product['quantity'] * $product['unit_price'];
				$orderProduct = OrderProduct::create([
					'order_id' => $order->id,
					'product_id' => $product['product_id'],
					'vendor_id' => $product['vendor_id'],
					'quantity' => $product['quantity'],
					'shipped_quantity' => 0,
					'remaining_quantity' => $product['quantity'],
					'unit_price' => $product['unit_price'],
					'amount' => $total,
					'accessory_item_charge' => $product['accessory_item_charge'],
					'shipping_charge' => $product['shipping_charge'],
					'total_amount' => $total + $product['shipping_charge'] + $product['accessory_item_charge'],
					'status' => 'Pending',
				]);
				foreach ($product['accessoryItems'] as $accessoryItem) {
					$orderProduct->accessoryCharges()->create([
						'accessory_item_id' => $accessoryItem['id'],
						'amount' => $accessoryItem['price'] * $product['quantity'],
						'created_at' => now(),
					]);
				}
			}

			OrderTracking::create([
				'order_id' => $order->id,
				'status' => 'Order Created',
				'description' => 'Order has been successfully created',
			]);

			/* Remove all customer's carts along with their products */
			$order->customer->customerCarts->each(function ($cart) {
				$cart->customerCartProducts()->delete();
				$cart->delete();
			});

			DB::commit();

			if ($request->boolean('pay_with_cheque')) {
				$batch = Bus::batch([])->name("Order Placed by Customer (CHECK) - #{$order->order_number}")->dispatch();
				$batch->options['queue'] = config('app.website') . '_ORD_PLC';
				$batch->add(new OrderPlacedMailJob([
					'recordId' => $order->id
				]));
			} else if ($request->boolean('is_cod')) {
				$batch = Bus::batch([])->name("Order Placed by Customer (COD) - #{$order->order_number}")->dispatch();
				$batch->options['queue'] = config('app.website') . '_ORD_PLC';
				$batch->add(new OrderPlacedMailJob([
					'recordId' => $order->id
				]));
			}

			/* Load relationships */
			$order->load([
				'orderProducts:id,order_id,product_id,vendor_id,quantity,unit_price,amount,shipping_charge,total_amount,status,accessory_item_charge',
				'orderProducts.accessoryCharges:id,relation_type,relation_id,accessory_item_id,amount',
				'orderProducts.accessoryCharges.accessoryItem:id,product_accessory_id,name,price',
				'orderProducts.accessoryCharges.accessoryItem.accessory:id,name',
				'orderProducts.product:id,name,images,sku,brand_id,currency_id,barcode',
				'orderProducts.product.brand:id,name',
				'orderProducts.product.currency:id,symbol',
				'tracking',
				'payments:id,order_id,transaction_id,payment_mode,amount,status,notes,created_at'
			]);

			/* Mutate the data for each order product */
			foreach ($order->orderProducts as $orderProduct) {
				$product = $orderProduct->product;
				if ($product) {
					$product->images = is_array($product->images) ? $product->images : (is_array($decoded = json_decode($product->images, true)) ? $decoded : null);
					$product->brand_name = $product->brand->name ?? null;
					$product->currency_symbol = $product->currency->symbol ?? null;
					unset($product->brand, $product->currency);
				}
				$orderProduct->product_supplier = optional($orderProduct->vendor_product_supplier)->only(['price', 'sale_price', 'shipping_charge', 'delivery_days', 'return_policy']);
				$orderProduct->expectedShippingDate = $orderProduct->product_supplier
				? getDateRange($order->created_at, $orderProduct->product_supplier['delivery_days'])
				: null;

				if ($orderProduct->accessoryCharges) {
					$orderProduct->accessory_charges = $orderProduct->accessoryCharges->map(function ($charge) {
						return [
							'id' => $charge->id,
							'accessory_item_id' => $charge->accessory_item_id,
							'accessory_item_name' => $charge->accessoryItem->name ?? null,
							'accessory_item_price' => $charge->accessoryItem->price ?? null,
							'product_accessory_id' => $charge->accessoryItem->accessory->id ?? null,
							'product_accessory_name' => $charge->accessoryItem->accessory->name ?? null,
							'amount' => $charge->amount,
						];
					});

					unset($orderProduct->accessoryCharges);
				}
			}

			foreach (['amount', 'tax_amount', 'discount', 'additional_discount', 'cheque_discount', 'total_amount', 'paid_amount', 'pending_amount'] as $key) {
				if (isset($order->$key)) {
					$order->$key = number_format($order->$key, 2, '.', '');
				}
			}

			return response()->json([
				'success' => true,
				'message' => 'Order created successfully',
				'data' => $order
			], 201);
		} catch (\Exception $e) {
			DB::rollBack();

			return response()->json([
				'success' => false,
				'message' => 'Failed to create order: ' . $e->getMessage()
			], 500);
		}
	}

	public function byNetTermpayment($orderAmount){

		$orderAmount = $orderAmount;
		$customerId = auth()->id();
		$finance = Finance::where('customer_id', $customerId)->where('status', 'Active')->orderBy('id', 'desc')->first();
		if(!$finance){
			return response()->json([
			'success' => false,
			'message' => 'Net Term finance is either not approved or not active'
			], 422);
		}
		$orderCredit = $orderAmount + $finance->usedCreditAmount;

		if ($orderCredit > $finance->approvedAmount) {
			
			if ($finance->availableCreditAmount> 0){

			return response()->json([
			'success' => false,
			'message' => "Offer Split Payment Option",
			], 422);
			}else{

			return response()->json([
			'success' => false,
			'message' => "Force Card Payment Only",
			], 422);

			}

			return response()->json([
				'success' => false,
				'message' => "The order amount (" . number_format($orderCredit, 2) . ") is less than the approved amount (" . number_format($finance->approvedAmount, 2) . ").",
			], 422);
		}
			
		if ($finance->approvedAmount == $orderCredit) {
			$finance->availableCreditAmount = '0';
			$finance->usedCreditAmount = $orderCredit;
			$finance->dueCreditAmount = '0';
		}

		if ($finance->approvedAmount > $orderCredit) {

			$finance->dueCreditAmount = $finance->approvedAmount - $orderCredit;
			$finance->usedCreditAmount = $orderCredit;
			$finance->availableCreditAmount = $finance->approvedAmount - $orderCredit;
			$nextPaymentDue = "";
			if ($finance->term_selection == 'Net 30 Days') {
				$nextPaymentDue = "+30 Days";
			} elseif ($finance->term_selection == 'Net 45 Days') {
				$nextPaymentDue = "+45 Days";
			} else if ($finance->term_selection == 'Net 60 Days') {
				$nextPaymentDue = "+60 Days";
			}
			if (!empty($nextPaymentDue)) {
				$finance->next_due_date = date('Y-m-d', strtotime($nextPaymentDue));
			}


			// FinancesPayment::create([
			// 	'finances_id'=>$finance->id,
			// 	'limitAmount'=>$finance->approvedAmount,
			// 	'usedAmount'=>$finance->id,
			// 	'availableAmount'=>$finance->id,
			// 	'dueAmount'=>$finance->id,
			// 	'creditTerms'=>$finance->term_selection,
			// ]);
		}
	}
	/**
	 * @OA\Get(
	 *     path="/api/frontend/orders/{id}",
	 *     summary="Get order details",
	 *     tags={"FrontEnd-Orders"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         description="Order ID",
	 *         required=true,
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\Response(response=200, description="Details retrieved successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function show($id)
	{
		$order = Order::where('customer_id', auth()->id())
		->where('id', $id)
		->first();

		if (!$order) {
			return response()->json([
				'success' => false,
				'message' => "Order not found."
			]);
		}

		/* Load relationships */
		$order->load([
			'customer:id,name,email,type,country_code,mobile_number',
			'customerAddress',
			'orderProducts:id,order_id,product_id,vendor_id,quantity,unit_price,amount,shipping_charge,total_amount,status,accessory_item_charge',
			'orderProducts.accessoryCharges:id,relation_type,relation_id,accessory_item_id,amount',
			'orderProducts.accessoryCharges.accessoryItem:id,product_accessory_id,name,price',
			'orderProducts.accessoryCharges.accessoryItem.accessory:id,name',
			'orderProducts.product:id,name,images,sku,brand_id,currency_id,barcode',
			'orderProducts.product.brand:id,name',
			'orderProducts.product.currency:id,symbol',
			'orderProducts.product.seoProductUrl:id,relational_id,relational_type,url',
			'orderProducts.product.sellingUnitAttribute:id,product_id,attribute_value',
			'orderProducts.product.warrantyAttribute',
			'tracking',
			'payments:id,order_id,transaction_id,payment_mode,amount,status,notes,created_at'
		]);

		/* Mutate the data for each order product */
		foreach ($order->orderProducts as $orderProduct) {
			$product = $orderProduct->product;
			if ($product) {
				$product->images = is_array($product->images)
				? $product->images
				: (is_array($decoded = json_decode($product->images, true)) ? $decoded : null);

				$product->brand_name = $product->brand->name ?? null;
				$product->currency_symbol = $product->currency->symbol ?? null;
				$product->warranty = $product->warrantyAttribute->attribute_value ?? null;
				$product->category_url = method_exists($product, 'category_url')
				? $product->category_url()
				: null;

				$product->parent_category_url = method_exists($product, 'parent_category_url')
				? $product->parent_category_url()
				: null;

				$product->url = $product->seoProductUrl->url ?? null;

				unset($product->brand, $product->currency);
			}

			$orderProduct->product_supplier = optional($orderProduct->vendor_product_supplier)
			->only(['price', 'sale_price', 'shipping_charge', 'delivery_days', 'return_policy']);

			$orderProduct->expectedShippingDate = $orderProduct->product_supplier
			? getDateRange($order->created_at, $orderProduct->product_supplier['delivery_days'])
			: null;

			if ($orderProduct->accessoryCharges) {
				$orderProduct->accessory_charges = $orderProduct->accessoryCharges->map(function ($charge) {
					return [
						'id' => $charge->id,
						'accessory_item_id' => $charge->accessory_item_id,
						'accessory_item_name' => $charge->accessoryItem->name ?? null,
						'accessory_item_price' => $charge->accessoryItem->price ?? null,
						'product_accessory_id' => $charge->accessoryItem->accessory->id ?? null,
						'product_accessory_name' => $charge->accessoryItem->accessory->name ?? null,
						'amount' => $charge->amount,
					];
				});

				unset($orderProduct->accessoryCharges);
			}
		}

		if (
			$order->status === 'Delivered' &&
			$orderProduct->product_supplier &&
			isset($orderProduct->product_supplier['return_policy'])
		) {
			$returnDays = (int) $orderProduct->product_supplier['return_policy'];
			$deliveryDate = \Carbon\Carbon::parse($order->updated_at);
			$returnUntil = $deliveryDate->copy()->addDays($returnDays);

			$orderProduct->is_returnable = now()->lte($returnUntil) ? 'yes' : 'no';
		} else {
			$orderProduct->is_returnable = 'yes';
		}

		foreach (['amount', 'tax_amount', 'discount', 'additional_discount', 'cheque_discount', 'total_amount', 'paid_amount', 'pending_amount'] as $key) {
			if (isset($order->$key)) {
				$order->$key = number_format($order->$key, 2, '.', '');
			}
		}

		return response()->json([
			'success' => true,
			'data' => $order
		]);
	}

	// /**
	//  * @OA\Put(
	//  *     path="/api/frontend/orders/{id}",
	//  *     summary="Update an existing order (if not yet confirmed)",
	//  *     tags={"FrontEnd-Orders"},
	//  *     @OA\Parameter(name="id", in="path", required=true, description="Order ID", @OA\Schema(type="integer")),
	//  *     @OA\RequestBody(
	//  *         required=true,
	//  *         @OA\JsonContent(
	//  *             required={"customer_address_id", "products"},
	//  *             @OA\Property(property="customer_address_id", type="integer", example="4"),
	//  *             @OA\Property(property="ship_all_at_once", type="boolean", example=false),
	//  *             @OA\Property(property="separate_deliveries", type="boolean", example=true),
	//  *             @OA\Property(
	//  *                 property="products",
	//  *                 type="array",
	//  *                 @OA\Items(
	//  *                     required={"product_id", "vendor_id", "quantity"},
	//  *                     @OA\Property(property="product_id", type="integer", example=101),
	//  *                     @OA\Property(property="vendor_id", type="integer", example=22),
	//  *                     @OA\Property(property="quantity", type="integer", example=3),
	//  *                 )
	//  *             )
	//  *         )
	//  *     ),
	//  *     @OA\Response(response=200, description="Updated successfully", @OA\MediaType(mediaType="application/json")),
	//  *     security={{"bearerAuth":{}}}
	//  * )
	//  */
	// public function update(Request $request, $orderId)
	// {
	// 	$allowedStatuses = [
	// 		'Pending'
	// 	];

	// 	$order = Order::with('orderProducts')->find($orderId);

	// 	if (!$order) {
	// 		return response()->json([
	// 			'success' => false,
	// 			'message' => 'Order not found'
	// 		], 404);
	// 	}

	// 	if (!in_array($order->status, $allowedStatuses)) {
	// 		return response()->json([
	// 			'success' => false,
	// 			'message' => 'This order has already been confirmed or processed and cannot be updated.'
	// 		], 400);
	// 	}

	// 	$request->validate([
	// 		'customer_address_id' => 'required|integer|exists:customer_addresses,id',
	// 		'shipping_charge' => 'required|numeric|min:0',
	// 		'ship_all_at_once' => 'nullable|boolean',
	// 		'separate_deliveries' => 'nullable|boolean',
	// 		'products' => 'required|array|min:1',
	// 		'products.*.product_id' => 'required|integer|exists:ec_products,id',
	// 		'products.*.vendor_id' => 'required|integer|exists:vendors,id',
	// 		'products.*.quantity' => 'required|integer|min:1',
	// 	]);

	// 	$customerId = auth()->id();

	// 	$address = CustomerAddress::where('id', $request->customer_address_id)->where('customer_id', $customerId)->first();

	// 	if (!$address) {
	// 		return response()->json([
	// 			'success' => false,
	// 			'message' => 'The selected address does not belong to the customer.'
	// 		], 422);
	// 	}

	// 	DB::beginTransaction();

	// 	try {
	// 		/* Collect all product supplier details in one go */
	// 		$productDetails = [];
	// 		foreach ($request->products as $product) {
	// 			$fetchedDetail = productSupplierDetail($product['product_id'], $product['vendor_id']);
	// 			if (!$fetchedDetail) {
	// 				throw new \Exception("Product supplier not found for Product {$product['product_id']} & Vendor {$product['vendor_id']}");
	// 			}
	// 			$productDetails[] = [
	// 				'product_id' => $product['product_id'],
	// 				'vendor_id' => $product['vendor_id'],
	// 				'quantity' => $product['quantity'],
	// 				'unit_price' => $fetchedDetail->unit_price,
	// 				'shipping_charge' => $fetchedDetail->shipping_charge ?? 0,
	// 			];
	// 		}

	// 		$totalProducts = 0;
	// 		$totalAmount = 0;

	// 		foreach ($productDetails as $product) {
	// 			$totalProducts += $product['quantity'];
	// 			$totalAmount += $product['quantity'] * $product['unit_price'];
	// 		}

	// 		$order->update([
	// 			'customer_address_id' => $request->customer_address_id,
	// 			'shipping_charge' => $request->shipping_charge,
	// 			'total_amount' => $totalAmount,
	// 			'total_products' => $totalProducts,
	// 			'ship_all_at_once' => $request->get('ship_all_at_once', true),
	// 			'separate_deliveries' => $request->get('separate_deliveries', false),
	// 			'pending_amount' => $totalAmount,
	// 		]);

	// 		/* Delete existing products and re-insert */
	// 		OrderProduct::where('order_id', $order->id)->delete();

	// 		foreach ($productDetails as $product) {
	// 			$total = $product['quantity'] * $product['unit_price'];
	// 			OrderProduct::create([
	// 				'order_id' => $order->id,
	// 				'product_id' => $product['product_id'],
	// 				'vendor_id' => $product['vendor_id'],
	// 				'quantity' => $product['quantity'],
	// 				'shipped_quantity' => 0,
	// 				'remaining_quantity' => $product['quantity'],
	// 				'unit_price' => $product['unit_price'],
	// 				'total_amount' => $total,
	// 				'status' => 'Pending',
	// 			]);
	// 		}

	// 		OrderTracking::create([
	// 			'order_id' => $order->id,
	// 			'status' => 'Order Updated By Customer',
	// 			'description' => 'Order has been successfully updated',
	// 		]);

	// 		DB::commit();

	// 		/* Reload updated order data */
	// 		$order->load([
	// 			'orderProducts:id,order_id,product_id,vendor_id,quantity,status',
	// 			'orderProducts.product:id,name,images,sku,brand_id,price,sale_price,product_type,barcode,warranty_information,brand_id',
	// 			'orderProducts.product.brand:id,name',
	// 			'tracking'
	// 		]);

	// 		/* Mutate */
	// 		foreach ($order->orderProducts as $orderProduct) {
	// 			$product = $orderProduct->product;

	// 			if ($product) {
	// 				$product->images = json_decode($product->images);
	// 				if ($product->brand) {
	// 					$product->brand_name = $product->brand->name;
	// 				}
	// 				unset($product->brand);
	// 			}
	// 		}

	// 		return response()->json([
	// 			'success' => true,
	// 			'message' => 'Order updated successfully',
	// 			'data' => $order
	// 		], 200);

	// 	} catch (\Exception $e) {
	// 		DB::rollBack();

	// 		return response()->json([
	// 			'success' => false,
	// 			'message' => 'Failed to update order: ' . $e->getMessage()
	// 		], 500);
	// 	}
	// }

	/**
	 * @OA\Put(
	 *     path="/api/frontend/orders/{id}/status",
	 *     summary="Update order status",
	 *     tags={"FrontEnd-Orders"},
	 *     @OA\Parameter(name="id", in="path", description="Order ID", required=true, @OA\Schema(type="integer")),
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"status"},
	 *             @OA\Property(property="status", type="string"),
	 *             @OA\Property(property="notes", type="string")
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Order status updated successfully"),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function updateStatus(Request $request, $id)
	{
		$request->validate([
			'status' => 'required|string|in:Cancelled',
			'notes' => 'required|string'
		]);

		$order = Order::find($id);

		if (!$order) {
			return response()->json([
				'success' => false,
				'message' => "Order not found."
			]);
		}

		$allowedStatuses = [
			'Pending',
			'Confirmed',
			'Supplier Delivery',
			'International',
			'Export',
			'On hold',
			'Ready to ship'
		];

		if (!in_array($order->status, $allowedStatuses)) {
			return response()->json([
				'success' => false,
				'message' => 'This order has already been shipped, delivered, or cancelled. You can no longer cancel it.'
			], 400);
		}

		$order->update([
			'status' => $request->status,
		]);
		$order->orderProducts()->update(['status' => $request->status]);

		if ($request->status == 'Cancelled') {
			$batch = Bus::batch([])->name("Order Cancelled by Customer - #{$order->order_number}")->dispatch();
			$batch->options['queue'] = config('app.website') . '_ORD_CNCL';
			$batch->add(new OrderCancelledMailJob([
				'recordId' => $order->id
			]));
		}

		/* dd tracking entry */
		OrderTracking::create([
			'order_id' => $order->id,
			'status' => 'Cancelled by customer',
			'description' => $request->notes,
		]);

		return response()->json([
			'success' => true,
			'message' => 'Order cancelled successfully.',
			'data' => $order
		], 200);
	}

	/**
	 * @OA\Get(
	 *     path="/api/frontend/orders/buy-it-again",
	 *     summary="Get products from last 5 delivered orders to buy again",
	 *     tags={"FrontEnd-Orders"},
	 *     @OA\Response(
	 *         response=200,
	 *         description="Products retrieved from previous delivered orders successfully"
	 *     ),
	 *     @OA\Response(
	 *         response=404,
	 *         description="No delivered orders found with products"
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function buyItAgain(Request $request)
	{
		// Get authenticated customer
		$customerId = auth()->id();

		if (!$customerId) {
			return response()->json([
				'success' => false,
				'message' => 'User not authenticated.'
			], 401);
		}

		// Fetch last 5 delivered orders with products
		$deliveredOrders = Order::where('customer_id', $customerId)
		->whereIn('status', ['Delivered', 'Cancelled'])
		->orderByDesc('created_at')
		->take(5)
		->with([
			'orderProducts.product.productSuppliers',
			'orderProducts.product.currency',
			'orderProducts.product.brand'
		])
		->get();

		$addedProducts = collect();

		foreach ($deliveredOrders as $order) {
			foreach ($order->orderProducts as $orderProduct) {
				$product = $orderProduct->product;
				if (!$product) {
					continue;
				}

				// find a vendor_id if available
				$vendorId = $product->productSuppliers->first()->vendor_id ?? null;

				// build a request like addToCart expects
				$cartRequest = new Request([
					'product_id' => $product->id,
					'quantity'   => $orderProduct->quantity,
					'vendor_id'  => $vendorId,
				]);

				// call your CartController function
				app(\App\Http\Controllers\FrontEnd\CartController::class)->addToCart($cartRequest);

				$addedProducts->push([
					'product_id' => $product->id,
					'name'       => $product->name,
					'quantity'   => $orderProduct->quantity,
					'unit_price' => $orderProduct->unit_price,
					'brand_name' => $product->brand->name ?? null,
				]);
			}
		}

		if ($addedProducts->isEmpty()) {
			return response()->json([
				'success' => false,
				'message' => 'No delivered orders found or no valid products available to buy again.'
			], 404);
		}

		return response()->json([
			'success' => true,
			'message' => 'Products added to your cart successfully.',
			'data'    => $addedProducts,
		], 200);
	}


	/**
	 * @OA\Get(
	 *     path="/api/frontend/orders/tracking",
	 *     summary="Track order by order ID",
	 *     tags={"FrontEnd-Orders"},
	 *     @OA\Parameter(name="order_number", in="query", required=true, description="Order number to track", @OA\Schema(type="string", example=12345)),
	 *     @OA\Response(response=200, description="List retrieved successfully", @OA\MediaType(mediaType="application/json")),
	 * )
	 */
	public function orderTracking(Request $request)
	{
		$request->validate([
			'order_number' => 'required|string|exists:orders,order_number',
		]);

		$order = Order::with(['tracking'])->where('order_number', $request->order_number)->first();

		if (!$order) {
			return response()->json([
				'success' => false,
				'message' => 'Order not found or access denied.',
				'data' => null,
			], 404);
		}

		return response()->json([
			'success' => true,
			'message' => 'Order with tracking info retrieved',
			'data' => $order,
		]);
	}
}
