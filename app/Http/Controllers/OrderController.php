<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;

use App\Models\FrontEnd\Order;
use App\Models\FrontEnd\Customer;
use App\Models\FrontEnd\OrderProduct;
use App\Models\FrontEnd\OrderTracking;

use App\Models\FrontEnd\Payment;
use App\Models\PaymentManagement;
use App\Models\FrontEnd\Shipment;
use App\Models\FrontEnd\ShipmentProduct;
use App\Models\FrontEnd\CustomerAddress;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

use Illuminate\Support\Facades\Bus;
use Illuminate\Bus\Batch;

use App\Jobs\Order\OrderPlacedMailJob;
use App\Jobs\Order\OrderReservedMailJob;
use App\Jobs\Order\OrderConfirmationMailJob;
use App\Jobs\Order\OutDeliveryMailJob;
use App\Jobs\Order\OrderDeliveredMailJob;
use App\Jobs\Order\OrderCancelledMailJob;
use App\Jobs\Order\PartialOrderCancelledMailJob;
use App\Jobs\Order\OrderUpdateMailJob;

class OrderController extends Controller
{
	/**
	 * @OA\Get(
	 *     path="/api/orders",
	 *     summary="Get all orders with pagination and filters",
	 *     tags={"Orders"},
	 *     @OA\Parameter(name="page", in="query", description="Page number for pagination", example=1, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="length", in="query", description="Number of records per page.", example=20, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="is_reserved", in="query", description="Filter by reserved orders (true/false)", @OA\Schema(type="boolean", example=true)),
	 *     @OA\Parameter(name="status", in="query", description="Filter by order status.", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="payment_status", in="query", description="Filter by payment status.", @OA\Schema(type="string", enum={"Paid", "Unpaid", "Partially Paid"})),
	 *     @OA\Parameter(name="from_date", in="query", @OA\Schema(type="string", format="date")),
	 *     @OA\Parameter(name="to_date", in="query", @OA\Schema(type="string", format="date")),
	 *     @OA\Parameter(name="global", in="query", description="Global search for all fields", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="sort_by", in="query", description="Column name to sort by", @OA\Schema(type="string", enum={"id", "order_number", "customer_name", "shipping_charge", "total_amount", "total_products", "created_at", "updated_at"})),
	 *     @OA\Parameter(name="sort_dir", in="query", description="Sort direction (asc or desc)", example="asc", @OA\Schema(type="string", enum={"asc", "desc"})),
	 *     @OA\Response(response=200, description="Orders retrieved successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function index(Request $request)
	{
		if ($request->filled('from_date') && $request->filled('to_date')) {
			$from = $request->from_date . ' 00:00:00';
			$to = $request->to_date . ' 23:59:59';

			$recordsQuery = Order::query();

			if ($request->has('status')) {
				$recordsQuery->where('status', $request->status);
			}

			if ($request->has('is_reserved')) {
				$recordsQuery->where('is_reserved', $request->boolean('is_reserved'));
			} else {
				$recordsQuery->where('is_reserved', false);
			}

			$recordsQuery = $recordsQuery->whereBetween('created_at', [$from, $to])->pluck('id');

			return response()->json([
				'success' => true,
				'message' => __('msg_rec_list'),
				'data' => $recordsQuery,
			]);
		}

		$searchableColumns = ['id', 'order_number', 'customer_name'];
		$sortableColumns = array_merge($searchableColumns, ['shipping_charge', 'total_amount', 'total_products', 'created_at', 'updated_at']);

		$sortBy = in_array($request->input('sort_by'), $sortableColumns) ? $request->input('sort_by') : 'id';
		$sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

		$recordsQuery = Order::query();

		if ($request->has('status')) {
			$recordsQuery->where('status', $request->status);
		}

		if ($request->filled('page') && $request->filled('length')) {

			if ($sortBy === 'customer_name' || ($request->filled('global') && in_array('customer_name', $searchableColumns))) {
				$recordsQuery->leftJoin('customers', 'orders.customer_id', '=', 'customers.id');
				$recordsQuery->select('orders.*');
			}

			$recordsQuery->with([
				'customer:id,name',
				'orderProducts:id,order_id,product_id,vendor_id,quantity,unit_price,amount,shipping_charge,total_amount,status,accessory_item_charge',
				'orderProducts.accessoryCharges:id,relation_type,relation_id,accessory_item_id,amount',
				'orderProducts.accessoryCharges.accessoryItem:id,product_accessory_id,name,price',
				'orderProducts.accessoryCharges.accessoryItem.accessory:id,name',
				'orderProducts.product:id,name,images,sku,brand_id,currency_id,barcode',
				'orderProducts.product.brand:id,name',
				'orderProducts.product.currency:id,symbol',
				'payments:id,order_id,transaction_id,payment_mode,amount,status,notes,created_at',
				'shipments',
				'creator',
				'updator',
				'nofraudResponse',
				'utm'
			]);

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

			if ($request->has('is_reserved')) {
				$recordsQuery->where('orders.is_reserved', $request->boolean('is_reserved'));
			} else {
				$recordsQuery->where('orders.is_reserved', false);
			}

			if ($request->filled('global')) {
				$search = $request->input('global');
				$recordsQuery->where(function ($q) use ($searchableColumns, $search) {
					foreach ($searchableColumns as $col) {
						if ($col === 'customer_name') {
							$q->orWhereHas('customer', function ($sub) use ($search) {
								$sub->where('name', 'like', '%' . $search . '%');
							});
						} else {
							$q->orWhere("orders.$col", 'like', '%' . $search . '%');
						}
					}
				});
			}

			if ($sortBy === 'customer_name') {
				$recordsQuery->orderBy('customers.name', $sortDir);
			} else {
				$recordsQuery->orderBy("orders.$sortBy", $sortDir);
			}

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

			$records->transform(function ($record) {
				$record->customer_name = $record->customer->name ?? null;
				$record->created_by = $record->creator->name ?? null;
				$record->updated_by = $record->updator->name ?? null;

				$response = $record->nofraudResponse->response ?? null;

				if (is_string($response)) {
					$data = json_decode($response, true);
				} elseif (is_array($response)) {
					$data = $response;
				} else {
					$data = [];
				}

				$record->nofraud_decision = $data['decision'] ?? null;
				unset($record->nofraudResponse);

				// Handle UTM: keep object, default fields to "Organic"
				if ($record->utm) {
					$record->utm->utm_source = $record->utm->utm_source ?? 'Organic';
					$record->utm->utm_medium = $record->utm->utm_medium ?? 'Organic';
					$record->utm->utm_campaign = $record->utm->utm_campaign ?? 'Organic';
					$record->utm->utm_term = $record->utm->utm_term ?? 'Organic';
					$record->utm->utm_content = $record->utm->utm_content ?? 'Organic';
					$record->utm->gclid = $record->utm->gclid ?? 'Organic';
				} else {
					$record->utm = (object)[
						'id' => null,
						'utm_source' => 'Organic',
						'utm_medium' => 'Organic',
						'utm_campaign' => 'Organic',
						'utm_term' => 'Organic',
						'utm_content' => 'Organic',
						'gclid' => 'Organic',
						'session_id' => null,
						'created_at' => null,
						'updated_at' => null
					];
				}

				unset($record->creator, $record->updator);

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

					foreach (['unit_price', 'amount', 'shipping_charge', 'total_amount'] as $key) {
						if (isset($orderProduct->$key)) {
							$orderProduct->$key = number_format($orderProduct->$key, 2, '.', '');
						}
					}
				}

				foreach (['shipping_charge', 'amount', 'tax_amount', 'discount', 'additional_discount_amount', 'total_amount', 'paid_amount', 'pending_amount'] as $key) {
					if (isset($record->$key)) {
						$record->$key = number_format($record->$key, 2, '.', '');
					}
				}

				return $record;
			});
		} else {//
			$records = $recordsQuery->orderBy('order_number', 'asc')->get(['id', 'order_number']);
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
	 *     path="/api/orders",
	 *     summary="Create a new order",
	 *     tags={"Orders"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="multipart/form-data",
	 *             @OA\Schema(
	 *                 required={"customer_id", "customer_address_id", "tax_percentage", "products"},
	 *                 @OA\Property(property="customer_id", type="integer", example=1, description="Customer ID"),
	 *                 @OA\Property(property="customer_address_id", type="integer", example=1, description="Customer address ID"),
	 *                 @OA\Property(property="is_lift_gate", type="boolean", example=true, description="Lift gate required"),
	 *                 @OA\Property(property="is_residential_address", type="boolean", example=true, description="Residential address"),
	 *                 @OA\Property(property="is_inside_delivery", type="boolean", example=true, description="Inside delivery required"),
	 *                 @OA\Property(property="tax_percentage", type="number", format="float", example=5, description="Tax percentage"),
	 *                 @OA\Property(property="ship_all_at_once", type="boolean", example=true, description="Ship all items together"),
	 *                 @OA\Property(property="separate_deliveries", type="boolean", example=false, description="Separate deliveries"),
	 *
	 *                 @OA\Property(property="additional_amount_name", type="string", example="Accessory 1", description="Additional amount name"),
	 *                 @OA\Property(property="additional_amount_price", type="number", format="float", example=100, description="Additional amount price"),
	 *
	 *                 @OA\Property(property="coupon_id", type="integer", example=1, description="Coupon ID"),
	 *                 @OA\Property(property="discount", type="number", format="float", example=200, description="Discount amount"),
	 *
	 *                 @OA\Property(property="additional_discount_option", type="boolean", example=true, description="Additional Discount Option"),
	 *                 @OA\Property(property="additional_discount_reason", type="string", example="Bulk order discount", description="Reason for additional discount"),
	 *                 @OA\Property(property="additional_discount_type", type="string", enum={"fixed", "percentage"}, example="percentage"),
	 *                 @OA\Property(property="additional_discount_percentage", type="number", format="float", example=10.50, description="Additional discount percentage"),
	 *                 @OA\Property(property="additional_discount_amount", type="number", format="float", example=50.00, description="Additional discount amount"),
	 *
	 *                 @OA\Property(property="payment_mode", type="string", enum={"Stripe", "Check Payment", "Ascentium Financing", "Approve Financing", "Resolve Financing", "Net Terms"}, example="Check Payment", description="Payment mode"),
	 *                 @OA\Property(property="pay_with_cheque", type="boolean", example=false, description="Pay with cheque"),
	 *                 @OA\Property(property="cheque_img", type="string", format="binary", description="Cheque image (jpeg, png, webp only, max 5 MB)"),
	 *                 @OA\Property(property="cheque_img_back", type="string", format="binary", description="Cheque image (jpeg, png, webp only, max 5 MB)"),
	 *
	 *                 @OA\Property(property="is_cod", type="boolean", example=false, description="Cash on delivery"),
	 *                 @OA\Property(property="is_reserved", type="boolean", example=false, description="Reserved order"),
	 *                 @OA\Property(property="is_payment", type="boolean", example=false, description="Payment gateway"),
	 *                 @OA\Property(property="is_ccavenue", type="boolean", example=false, description="Payment gateway"),
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
		/* Parse boolean strings to actual booleans */
		$booleanFields = [
			'is_lift_gate',
			'is_residential_address',
			'is_inside_delivery',
			'ship_all_at_once',
			'separate_deliveries',
			'is_cod',
			'pay_with_cheque',
			'additional_discount_option',
			'is_reserved',
			'is_payment',
			'is_ccavenue',
			'is_squarePayment',
			'is_customer_pickup'
		];

		/* Parse products JSON string to array */
		foreach ($booleanFields as $field) {
			if ($request->has($field)) {
				$request->merge([
					$field => filter_var($request->input($field), FILTER_VALIDATE_BOOLEAN)
				]);
			}
		}
		if ($request->has('products') && is_string($request->products)) {
			$productsString = $request->products;
			if (strpos(trim($productsString), '{') === 0 && strpos(trim($productsString), '[') !== 0) {
				$productsString = '[' . $productsString . ']';
			}
			$products = json_decode($productsString, true);
			$request->merge(['products' => $products]);
		}

		$request->validate([
			'customer_id' => 'required|integer|exists:customers,id',
			'customer_address_id' => 'required|integer|exists:customer_addresses,id',
			'is_lift_gate' => 'nullable|boolean',
			'is_residential_address' => 'nullable|boolean',
			'is_inside_delivery' => 'nullable|boolean',
			'tax_percentage' => 'required|numeric|min:0',
			'ship_all_at_once' => 'nullable|boolean',
			'separate_deliveries' => 'nullable|boolean',

			'additional_amount_name' => 'nullable|required_with:additional_amount_price|string|max:255',
			'additional_amount_price' => 'nullable|required_with:additional_amount_name|numeric|min:0',

			'coupon_id' => 'nullable|integer',
			'discount' => 'nullable|numeric|min:0',

			'payment_mode' => 'nullable|in:Stripe,Check Payment,Ascentium Financing,Approve Financing,Resolve Financing,Net Terms',
			'pay_with_cheque' => 'nullable|boolean',
			'cheque_img' => 'nullable|required_if:pay_with_cheque,true|file|mimes:jpeg,jpg,png,webp|max:5120',
			'cheque_img_back' => 'nullable|required_if:pay_with_cheque,true|file|mimes:jpeg,jpg,png,webp|max:5120',

			'additional_discount_option' => 'nullable|boolean',
			'additional_discount_reason' => 'nullable|string|max:255',
			'additional_discount_type' => 'nullable|in:fixed,percentage',
			'additional_discount_percentage' => 'nullable|numeric|min:0|max:100|required_if:additional_discount_type,percentage',
			'additional_discount_amount' => 'nullable|numeric|min:0|required_if:additional_discount_type,fixed',

			'is_cod' => 'nullable|boolean',
			'is_reserved' => 'nullable|boolean',
			'is_payment' => 'nullable|boolean',
			'is_ccavenue' => 'nullable|boolean',
			'is_squarePayment' => 'nullable|boolean',
			'is_customer_pickup' => 'nullable|boolean',

			'products' => 'required|array|min:1',
			'products.*.product_id' => 'required|integer|exists:ec_products,id',
			'products.*.vendor_id' => 'required|integer|exists:vendors,id',
			'products.*.quantity' => 'required|integer|min:1',
			'products.*.accessory_item_ids' => 'nullable|array',
			'products.*.accessory_item_ids.*' => 'integer|exists:accessory_items,id',
		]);

		$address = CustomerAddress::where('id', $request->customer_address_id)
		->where('customer_id', $request->customer_id)
		->first();

		if (!$address) {
			return response()->json([
				'success' => false,
				'message' => 'The selected address does not belong to the customer.'
			], 422);
		}
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

		/* Handle Additional Amount Price */
		if (!empty($request->additional_amount_price)) {
			$orderAmount += (float) $request->additional_amount_price;
		}

		/* Handle Coupon Discount */
		$discountedAmount = $orderAmount - $discount;

		/* Handle Additional Discount */
		if ($request->additional_discount_option) {
			$additionalDiscountReason = $request->additional_discount_reason;
			$additionalDiscountType = $request->additional_discount_type;
			if ($additionalDiscountType == 'fixed') {
				$additionalDiscountPercentage = null;
				$additionalDiscountAmount = $request->additional_discount_amount ?? 0;
			} else if ($additionalDiscountType == 'percentage') {
				$additionalDiscountPercentage = $request->additional_discount_percentage;
				$additionalDiscountAmount = round($discountedAmount * $additionalDiscountPercentage / 100, 2);
			}
			$discountedAmount -= $additionalDiscountAmount;
		} else {
			$additionalDiscountReason = null;
			$additionalDiscountType = null;
			$additionalDiscountPercentage = null;
			$additionalDiscountAmount = 0;
		}

		/* Handle Cheque Payment Discount */
		if ($payWithCheque && $request->payment_mode == 'Check Payment') {
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
			$chequeDiscountPercentage = 0;
			$chequeDiscount = round($discountedAmount * $chequeDiscountPercentage / 100, 2);
			$discountedAmount -= $chequeDiscount;
		} else {
			$chequeImg = null;
			$chequeImgBack = null;
			$chequeDiscountPercentage = 0;
			$chequeDiscount = 0;
		}

		/* Add extra charges */
		$discountedAmount += $request->boolean('is_lift_gate') ? 75 : 0;
		$discountedAmount += $request->boolean('is_residential_address') ? 199 : 0;
		$discountedAmount += $request->boolean('is_inside_delivery') ? 249 : 0;

		/* Tax rules */
		$customer = Customer::find($request->customer_id);
		$taxPercentage = $customer->is_tax_free ? 0 : $request->tax_percentage;

		if (in_array(config('app.website'), ['UAE', 'UAE_T'])) {
			$taxAmount = round($discountedAmount * ($taxPercentage / 100), 2);
			$orderShipping = (($discountedAmount + $taxAmount) < 500) ? 30 : 0;
		} elseif (in_array(config('app.website'), ['US', 'US_T'])) {
			$taxableAmount = $discountedAmount + $orderShipping;
			$taxAmount = round($taxableAmount * ($taxPercentage / 100), 2);
		} else {
			$taxAmount = round($discountedAmount * ($taxPercentage / 100), 2);
		}
		$totalAmount = $discountedAmount + $taxAmount + $orderShipping;

		DB::beginTransaction();

		try {
			/* Get the latest order by ID (most recent) */
			$latestOrder = Order::orderBy('order_number', 'desc')->first();

			if ($latestOrder && is_numeric($latestOrder->order_number)) {
				$orderNumber = (int) $latestOrder->order_number + 1;
			} else {
				$orderNumber = in_array(config('app.website'), ['US', 'US_T']) ? 10001 : (in_array(config('app.website'), ['UAE', 'UAE_T']) ? 1001 : 101);
			}

			$order = Order::create([
				'order_number' => $orderNumber,
				'customer_id' => $request->customer_id,
				'customer_address_id' => $request->customer_address_id,
				'is_lift_gate' => $request->is_lift_gate,
				'is_residential_address' => $request->is_residential_address,
				'is_inside_delivery' => $request->is_inside_delivery,
				'amount' => $orderAmount,

				'additional_amount_name' => $request->additional_amount_name ?? null,
				'additional_amount_price' => $request->additional_amount_price ?? null,

				'coupon_id' => $request->coupon_id ?? null,
				'discount' => $discount,

				'additional_discount_reason' => $additionalDiscountReason,
				'additional_discount_type' => $additionalDiscountType,
				'additional_discount_percentage' => $additionalDiscountPercentage,
				'additional_discount_amount' => $additionalDiscountAmount,

				'payment_mode' => $request->payment_mode ?? null,
				'pay_with_cheque' => $payWithCheque,
				'cheque_discount_percentage' => $chequeDiscountPercentage,
				'cheque_discount' => $chequeDiscount,
				'cheque_img' => $chequeImg,
				'cheque_img_back' => $chequeImgBack,

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
				'is_ccavenue' => $request->boolean('is_ccavenue'),
				'is_squarePayment' => $request->boolean('is_squarePayment'),
				'is_customer_pickup' => $request->boolean('is_customer_pickup'),
				'is_cod' => $request->boolean('is_cod'),

				'created_by' => auth()->id(),
				'payment_link' => null
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
				'status' => 'Order Created By Backend Panel',
				'description' => 'Order has been successfully created',
				'created_by' => auth()->id()
			]);

			/* Remove all customer's carts along with their products */
			$order->customer->customerCarts->each(function ($cart) {
				$cart->customerCartProducts()->delete();
				$cart->delete();
			});

			if ($request->boolean('is_reserved') && !$payWithCheque) {
				if (in_array(config('app.website'), ['UAE', 'UAE_T'])) {
					$paymentLink = null;
					if ($request->boolean('is_payment')) {
						try {
							$paymentLink = app(\App\Http\Controllers\FrontEnd\StripeController::class)->generatePaymentLink($order);
							if ($paymentLink) {
								$order = Order::find($order->id);
								$order->payment_link = $paymentLink;
								$order->save();
							}
						} catch (\Exception $e) {
							\Log::error('Stripe Payment Link generation failed', [
								'order_id' => $order->id,
								'error' => $e->getMessage(),
								'trace' => $e->getTraceAsString()
							]);
						}

					} else if ($request->boolean('is_ccavenue')) {
						try {
							$paymentLink = app(\App\Http\Controllers\FrontEnd\CcavenueController::class)->createCCavenuePaymentLink($order);

							if ($paymentLink) {
								$order = Order::find($order->id);
								$order->payment_link = $paymentLink;
								$order->save();
							}
						} catch (\Exception $e) {
							\Log::error('CCAvenue Payment Link generation failed', [
								'order_id' => $order->id,
								'error' => $e->getMessage(),
								'trace' => $e->getTraceAsString()
							]);
						}
					}
				} else if (in_array(config('app.website'), ['US', 'US_T'])) {
					$paymentLink = null;
					if ($request->boolean('is_payment')) {
						try {
							$paymentLink = app(\App\Http\Controllers\FrontEnd\StripeController::class)->generatePaymentLink($order);
							if ($paymentLink) {
								$order = Order::find($order->id);
								$order->payment_link = $paymentLink;
								$order->save();
							}
						} catch (\Exception $e) {
							\Log::error('Stripe Payment Link generation failed', [
								'order_id' => $order->id,
								'error' => $e->getMessage(),
								'trace' => $e->getTraceAsString()
							]);
						}

					}
					else if ($request->boolean('is_squarePayment')) {
						try {
							$paymentLink = app(\App\Http\Controllers\FrontEnd\SquarePaymentController::class)
							->createPaymentLink($order);
							if ($paymentLink) {
								$order = Order::find($order->id);
								$order->payment_link = $paymentLink;
								$order->save();
							}
						} catch (\Exception $e) {
							\Log::error('Square Payment Link generation failed', [
								'order_id' => $order->id,
								'error' => $e->getMessage(),
								'trace' => $e->getTraceAsString()
							]);
						}
					}
				}
			}

			DB::commit();

			if ($request->boolean('is_reserved')) {
				$batch = Bus::batch([])->name("Order Reserved by Backend - #{$order->order_number}")->dispatch();
				$batch->options['queue'] = config('app.website') . '_ORD_RES';
				$batch->add(new OrderReservedMailJob([
					'recordId' => $order->id
				]));
			} else {
				$batch = Bus::batch([])->name("Order Placed by Backend - #{$order->order_number}")->dispatch();
				$batch->options['queue'] = config('app.website') . '_ORD_PLC';
				$batch->add(new OrderPlacedMailJob([
					'recordId' => $order->id
				]));
			}

			// $order->load([
			// 	'orderProducts:id,order_id,product_id,vendor_id,quantity,unit_price,amount,shipping_charge,total_amount,status,accessory_item_charge',
			// 	'orderProducts.accessoryCharges:id,relation_type,relation_id,accessory_item_id,amount',
			// 	'orderProducts.accessoryCharges.accessoryItem:id,product_accessory_id,name,price',
			// 	'orderProducts.accessoryCharges.accessoryItem.accessory:id,name',
			// 	'orderProducts.product:id,name,images,sku,brand_id,currency_id,barcode',
			// 	'orderProducts.product.brand:id,name',
			// 	'orderProducts.product.currency:id,symbol',
			// 	'tracking',
			// 	'payments:id,order_id,transaction_id,payment_mode,amount,status,notes,created_at'
			// ]);

			// // Mutate the data for each order product
			// foreach ($order->orderProducts as $orderProduct) {
			// 	$product = $orderProduct->product;
			// 	if ($product) {
			// 		$product->images = is_array($product->images)
			// 		? $product->images
			// 		: (is_array($decoded = json_decode($product->images, true)) ? $decoded : null);
			// 		$product->brand_name = $product->brand->name ?? null;
			// 		$product->currency_symbol = $product->currency->symbol ?? null;
			// 		unset($product->brand, $product->currency);
			// 	}

			// 	$orderProduct->product_supplier = optional($orderProduct->vendor_product_supplier)
			// 	->only(['price', 'sale_price', 'shipping_charge', 'delivery_days', 'return_policy']);
			// 	$orderProduct->expectedShippingDate = $orderProduct->product_supplier
			// 	? getDateRange($order->created_at, $orderProduct->product_supplier['delivery_days'])
			// 	: null;

			// 	if ($orderProduct->accessoryCharges) {
			// 		$orderProduct->accessory_charges = $orderProduct->accessoryCharges->map(function ($charge) {
			// 			return [
			// 				'id' => $charge->id,
			// 				'accessory_item_id' => $charge->accessory_item_id,
			// 				'accessory_item_name' => $charge->accessoryItem->name ?? null,
			// 				'accessory_item_price' => $charge->accessoryItem->price ?? null,
			// 				'product_accessory_id' => $charge->accessoryItem->accessory->id ?? null,
			// 				'product_accessory_name' => $charge->accessoryItem->accessory->name ?? null,
			// 				'amount' => $charge->amount,
			// 			];
			// 		});

			// 		unset($orderProduct->accessoryCharges);
			// 	}

			// 	// Format numeric values to 2 decimal places - FIXED variable name
			// 	foreach (['unit_price', 'amount', 'shipping_charge', 'total_amount'] as $key) {
			// 		if (isset($orderProduct->$key)) {
			// 			$orderProduct->$key = number_format($orderProduct->$key, 2, '.', '');
			// 		}
			// 	}
			// }

			// foreach (['shipping_charge', 'amount', 'tax_amount', 'discount', 'additional_discount_amount', 'total_amount', 'paid_amount', 'pending_amount'] as $key) {
			// 	if (isset($order->$key)) {
			// 		$order->$key = number_format($order->$key, 2, '.', '');
			// 	}
			// }

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

	public function handleSquareWebhook(Request $request)
	{
		$data = $request->all();

		// Example: Square sends the order_id or payment link ID
		$orderId = $data['order_id'] ?? null;
		$transactionId = $data['transaction_id'] ?? null;

		if (!$orderId) {
			return response()->json(['success' => false, 'message' => 'Order ID missing'], 400);
		}

		$order = Order::where('id', $orderId)->first();
		if (!$order) {
			return response()->json(['success' => false, 'message' => 'Order not found'], 404);
		}

		// Mark order as paid and remove payment link
		$order->update([
			'is_paid' => true,
			'paid_amount' => $order->total_amount,
			'pending_amount' => 0,
			'payment_link' => null
		]);

		Payment::create([
			'order_id' => $order->id,
			'transaction_id' => $transactionId,
			'payment_mode' => 'Square',
			'amount' => $order->total_amount,
			'status' => 'Paid',
			'notes' => 'Payment completed via Square'
		]);

		return response()->json(['success' => true, 'message' => 'Payment recorded']);
	}

	public function markOrderPaid($orderId, $transactionId = null)
	{
		$order = Order::find($orderId);

		if (!$order) {
			return response()->json(['success' => false, 'message' => 'Order not found'], 404);
		}

		$order->update([
			'is_paid' => true,
			'paid_amount' => $order->total_amount,
			'pending_amount' => 0,
			'payment_link' => null
		]);

		PaymentManagement::create([
			'order_id' => $order->id,
			'transaction_id' => $transactionId,
			'payment_mode' => 'Credit Card',
			'payment_method' => 'Square',
			'amount' => $order->total_amount,
			'status' => 'Completed',
			'notes' => 'Payment marked through link'
		]);

		return response()->json(['success' => true, 'message' => 'Order marked as paid.']);
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
	 *     @OA\Response(response=200, description="Order details retrieved successfully", @OA\MediaType(mediaType="application/json"))
	 * )
	 */
	public function show($id)
	{
		$order = Order::find($id);

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
			'orderProducts.product.sellingUnitAttribute:id,product_id,attribute_value',
			'payments:id,order_id,transaction_id,payment_mode,amount,status,notes,created_at,payment_method',
			'shipments',
			'creator',
			'updator',
			'tracking',
			'nofraudResponse',
			'utm'
		]);

		if ($order->payments->isEmpty()) {
			$order->setRelation('payments', collect([
				(object) [
					'transaction_id' => null,
					'amount' => null,
					'status' => null,
					'payment_mode' => 'Cash On Delivery',
					'notes' => null,
					'created_at' => null,
				]
			]));
		}

		/* Mutate the data for each order product */
		$order->created_by = $order->creator->name ?? null;
		$order->updated_by = $order->updator->name ?? null;
		unset($record->creator, $record->updator);

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

			$orderProduct->nofraudResponse->response ?? null;
			$orderProduct->nofraud_decision = $data['decision'] ?? null;
			unset($orderProduct->nofraudResponse);

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

			/* Format numeric values to 2 decimal places */
			foreach (['unit_price', 'amount', 'shipping_charge', 'total_amount'] as $key) {
				if (isset($quoteProduct->$key)) {
					$quoteProduct->$key = number_format($quoteProduct->$key, 2, '.', '');
				}
			}
		}

		foreach (['shipping_charge', 'amount', 'tax_amount', 'discount', 'additional_discount_amount', 'total_amount', 'paid_amount', 'pending_amount'] as $key) {
			if (isset($order->$key)) {
				$order->$key = number_format($order->$key, 2, '.', '');
			}
		}

		return response()->json([
			'success' => true,
			'data' => $order
		]);
	}

	/**
	 * @OA\Post(
	 *     path="/api/orders/{id}/resend-mail",
	 *     summary="Resend order place email (only if order is pending)",
	 *     tags={"Orders"},
	 *     security={{"bearerAuth":{}}},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         description="Order ID",
	 *         required=true,
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\Response(response=200, description="Order place mail sent successfully", @OA\MediaType(mediaType="application/json")),
	 * )
	 */
	public function resendOrderPlaceMail($id)
	{
		$order = Order::find($id);
		if (!$order) {
			return response()->json([
				'success' => false,
				'message' => 'Order not found.'
			], 404);
		}

		if (strtolower($order->status) !== 'pending') {
			return response()->json([
				'success' => false,
				'message' => "Order #{$order->id} cannot resend mail because status is '{$order->status}'. Only pending orders are allowed."
			], 400);
		}

		try {
			/* Check is_reserved from order */
			$isReserved = $order->is_reserved;

			/* Create batch for resending order mail */
			if ($isReserved) {
				$batch = Bus::batch([])->name("Resend Order Reserved Mail - #{$order->order_number}")->dispatch();
				$batch->options['queue'] = config('app.website') . '_ORD_RES';
				$batch->add(new OrderReservedMailJob([
					'recordId' => $order->id
				]));
			} else {
				$batch = Bus::batch([])->name("Resend Order Place Mail - #{$order->order_number}")->dispatch();
				$batch->options['queue'] = config('app.website') . '_ORD_PLC';
				$batch->add(new OrderPlacedMailJob([
					'recordId' => $order->id
				]));
			}

			return response()->json([
				'success' => true,
				'message' => "Order mail resent successfully for Order #{$order->order_number}."
			]);
		} catch (\Throwable $e) {
			return response()->json([
				'success' => false,
				'message' => 'Failed to resend order mail. Please try again later.',
				'error' => $e->getMessage(),
			], 500);
		}
	}

	/**
	 * @OA\Post(
	 *     path="/api/orders/calculate-discount",
	 *     summary="Calculate required discount to achieve desired total amount",
	 *     tags={"Orders"},
	 *     security={{"bearerAuth":{}}},
	 *     @OA\Parameter(name="order_id", in="query", description="Order ID", required=true, @OA\Schema(type="integer", example=123)),
	 *     @OA\Parameter(name="desired_amount", in="query", description="Target total amount", required=true, @OA\Schema(type="number", example=15000.00)),
	 *     @OA\Response(response=200, description="Discount calculated successfully", @OA\MediaType(mediaType="application/json"))
	 * )
	 */
	public function calculateDiscountForDesiredAmount(Request $request)
	{
		$request->validate([
			'order_id' => 'required|integer|exists:orders,id',
			'desired_amount' => 'required|numeric|min:0'
		]);

		$order = Order::find($request->order_id);

		if (!$order) {
			return response()->json([
				'success' => false,
				'message' => 'Order not found'
			], 404);
		}

		/* Get base order amount (products + additional amount, NO discounts) */
		$baseOrderAmount = $order->amount; /* This is before any discounts */

		/* Fees */
		$liftGateFee = $order->is_lift_gate ? 75 : 0;
		$residentialFee = $order->is_residential_address ? 199 : 0;
		$insideDeliveryFee = $order->is_inside_delivery ? 249 : 0;
		$shippingCharge = $order->shipping_charge ?? 0;

		/* Tax */
		$taxPercentage = $order->tax_percentage ?? 0;
		$taxRate = $taxPercentage / 100;

		$desiredAmount = $request->desired_amount;

		/* Calculate TOTAL discount needed (ignoring any existing discounts) */
		if (in_array(config('app.website'), ['US', 'US_T'])) {
			/* US: Shipping is TAXABLE */
			$amountBeforeTax = $desiredAmount / (1 + $taxRate);
			$discountedAmount = $amountBeforeTax - $shippingCharge;

			/* Calculate total discount from base amount */
			$totalDiscountNeeded = $baseOrderAmount
			+ $liftGateFee
			+ $residentialFee
			+ $insideDeliveryFee
			- $discountedAmount;

		} elseif (in_array(config('app.website'), ['UAE', 'UAE_T'])) {
			/* UAE: Shipping is NOT TAXABLE */
			$discountedAmount = ($desiredAmount - $shippingCharge) / (1 + $taxRate);

			$totalDiscountNeeded = $baseOrderAmount
			+ $liftGateFee
			+ $residentialFee
			+ $insideDeliveryFee
			- $discountedAmount;

		} else {
			/* Default: Same as UAE */
			$discountedAmount = ($desiredAmount - $shippingCharge) / (1 + $taxRate);

			$totalDiscountNeeded = $baseOrderAmount
			+ $liftGateFee
			+ $residentialFee
			+ $insideDeliveryFee
			- $discountedAmount;
		}

		/* Ensure discount is not negative */
		$totalDiscountNeeded = max(0, $totalDiscountNeeded);

		/* Get existing discounts for reference */
		$existingCouponDiscount = $order->discount ?? 0;
		$existingAdditionalDiscount = $order->additional_discount_amount ?? 0;
		$existingChequeDiscount = $order->cheque_discount ?? 0;
		$totalExistingDiscounts = $existingCouponDiscount + $existingAdditionalDiscount + $existingChequeDiscount;

		/* Calculate verification */
		$verificationTotal = $this->calculateTotalWithDiscount($order, $totalDiscountNeeded);

		return response()->json([
			'success' => true,
			'total_discount_needed' => round($totalDiscountNeeded, 2),
			'existing_discounts' => [
				'coupon_discount' => round($existingCouponDiscount, 2),
				'additional_discount' => round($existingAdditionalDiscount, 2),
				'cheque_discount' => round($existingChequeDiscount, 2),
				'total' => round($totalExistingDiscounts, 2)
			],
			'verification_total' => round($verificationTotal, 2),
			'difference' => round(abs($desiredAmount - $verificationTotal), 2),
			'message' => 'Total discount calculated successfully (replace existing discounts with this value)'
		]);
	}

	/**
	 * Helper function to verify calculation
	 */
	private function calculateTotalWithDiscount($order, $totalDiscount)
	{
		$baseOrderAmount = $order->amount;

		$liftGateFee = $order->is_lift_gate ? 75 : 0;
		$residentialFee = $order->is_residential_address ? 199 : 0;
		$insideDeliveryFee = $order->is_inside_delivery ? 249 : 0;

		$taxPercentage = $order->tax_percentage ?? 0;
		$shippingCharge = $order->shipping_charge ?? 0;

		/* Calculate discounted amount */
		$discountedAmount = $baseOrderAmount
		- $totalDiscount
		+ $liftGateFee
		+ $residentialFee
		+ $insideDeliveryFee;

		/* Calculate total based on website */
		if (in_array(config('app.website'), ['US', 'US_T'])) {
			/* US: Shipping is taxable */
			$taxableAmount = $discountedAmount + $shippingCharge;
			$taxAmount = round($taxableAmount * ($taxPercentage / 100), 2);
			$totalAmount = $discountedAmount + $taxAmount + $shippingCharge;
		} else {
			/* UAE: Shipping is not taxable */
			$taxAmount = round($discountedAmount * ($taxPercentage / 100), 2);
			$totalAmount = $discountedAmount + $taxAmount + $shippingCharge;
		}

		return $totalAmount;
	}

    /**
     * @OA\Post(
     *     path="/api/orders/calculate-discount-for-new-order",
     *     summary="Calculate required additional discount for a new order to achieve desired total amount",
     *     tags={"Orders"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"products_subtotal","desired_amount","tax_percentage","shipping_charge"},
     *                 @OA\Property(property="products_subtotal", type="number", format="float", example=149.34, description="Sum of all product prices"),
     *                 @OA\Property(property="additional_amount", type="number", format="float", example=3000.00, description="Additional special amount (optional)"),
     *                 @OA\Property(property="coupon_discount", type="number", format="float", example=0.00, description="Existing coupon discount (optional)"),
     *                 @OA\Property(property="cheque_discount", type="number", format="float", example=0.00, description="Existing cheque discount (optional)"),
     *                 @OA\Property(property="is_lift_gate", type="boolean", example=true, description="Lift gate fee required ($75)"),
     *                 @OA\Property(property="is_residential_address", type="boolean", example=true, description="Residential delivery fee required ($199)"),
     *                 @OA\Property(property="is_inside_delivery", type="boolean", example=true, description="Inside delivery fee required ($249)"),
     *                 @OA\Property(property="shipping_charge", type="number", format="float", example=11343.00, description="Shipping charge amount"),
     *                 @OA\Property(property="tax_percentage", type="number", format="float", example=10.25, description="Tax percentage (e.g., 10.25 for 10.25%)"),
     *                 @OA\Property(property="desired_amount", type="number", format="float", example=15000.00, description="Target total amount you want to achieve")
     *             )
     *         )
     *     ),
	 *     @OA\Response(response=200, description="Additional discount calculated successfully", @OA\MediaType(mediaType="application/json"))
     * )
     */
    public function calculateDiscountForNewOrder(Request $request)
    {
        $request->validate([
            'products_subtotal' => 'required|numeric|min:0',
            'additional_amount' => 'nullable|numeric|min:0',
            'coupon_discount' => 'nullable|numeric|min:0',
            'cheque_discount' => 'nullable|numeric|min:0',
            'is_lift_gate' => 'nullable|boolean',
            'is_residential_address' => 'nullable|boolean',
            'is_inside_delivery' => 'nullable|boolean',
            'shipping_charge' => 'required|numeric|min:0',
            'tax_percentage' => 'required|numeric|min:0|max:100',
            'desired_amount' => 'required|numeric|min:0'
        ]);

        /* Get request values */
        $productsSubtotal = $request->products_subtotal;
        $additionalAmount = $request->additional_amount ?? 0;
        $subtotal = $productsSubtotal + $additionalAmount;

        /* Existing discounts */
        $couponDiscount = $request->coupon_discount ?? 0;
        $chequeDiscount = $request->cheque_discount ?? 0;
        $existingDiscounts = $couponDiscount + $chequeDiscount;

        /* Fees */
        $liftGateFee = $request->boolean('is_lift_gate') ? 75 : 0;
        $residentialFee = $request->boolean('is_residential_address') ? 199 : 0;
        $insideDeliveryFee = $request->boolean('is_inside_delivery') ? 249 : 0;
        $shippingCharge = $request->shipping_charge;

        /* Tax */
        $taxPercentage = $request->tax_percentage;
        $taxRate = $taxPercentage / 100;

        $desiredAmount = $request->desired_amount;

        /* Calculate additional discount needed based on website */
        if (in_array(config('app.website'), ['US', 'US_T'])) {
            /* US: Shipping is TAXABLE */
            /* Formula: Total = (discountedAmount + shipping) × (1 + tax%) */
            /* Reverse: discountedAmount = (Total / (1 + tax%)) - shipping */

            $amountBeforeTax = $desiredAmount / (1 + $taxRate);
            $discountedAmount = $amountBeforeTax - $shippingCharge;

            /* discountedAmount = subtotal - couponDiscount - chequeDiscount - additionalDiscount + fees */
            /* additionalDiscount = subtotal - couponDiscount - chequeDiscount + fees - discountedAmount */

            $additionalDiscountNeeded = $subtotal
                - $couponDiscount
                - $chequeDiscount
                + $liftGateFee
                + $residentialFee
                + $insideDeliveryFee
                - $discountedAmount;

        } elseif (in_array(config('app.website'), ['UAE', 'UAE_T'])) {
            /* UAE: Shipping is NOT TAXABLE */
            /* Formula: Total = (discountedAmount × (1 + tax%)) + shipping */
            /* Reverse: discountedAmount = (Total - shipping) / (1 + tax%) */

            $discountedAmount = ($desiredAmount - $shippingCharge) / (1 + $taxRate);

            $additionalDiscountNeeded = $subtotal
                - $couponDiscount
                - $chequeDiscount
                + $liftGateFee
                + $residentialFee
                + $insideDeliveryFee
                - $discountedAmount;

        } else {
            /* Default: Same as UAE */
            $discountedAmount = ($desiredAmount - $shippingCharge) / (1 + $taxRate);

            $additionalDiscountNeeded = $subtotal
                - $couponDiscount
                - $chequeDiscount
                + $liftGateFee
                + $residentialFee
                + $insideDeliveryFee
                - $discountedAmount;
        }

        /* Ensure additional discount is not negative */
        $additionalDiscountNeeded = max(0, $additionalDiscountNeeded);

        /* Calculate breakdown for verification */
        $breakdown = $this->calculateNewOrderBreakdown(
            $productsSubtotal,
            $additionalAmount,
            $couponDiscount,
            $chequeDiscount,
            $additionalDiscountNeeded,
            $liftGateFee,
            $residentialFee,
            $insideDeliveryFee,
            $shippingCharge,
            $taxPercentage
        );

        return response()->json([
            'success' => true,
            'additional_discount_needed' => round($additionalDiscountNeeded, 2),
            'existing_discounts' => [
                'coupon_discount' => round($couponDiscount, 2),
                'cheque_discount' => round($chequeDiscount, 2),
                'total' => round($existingDiscounts, 2)
            ],
            'breakdown' => $breakdown,
            'verification_total' => round($breakdown['total_amount'], 2),
            'difference' => round(abs($desiredAmount - $breakdown['total_amount']), 2),
            'message' => 'Additional discount calculated successfully'
        ]);
    }

    /**
     * Helper function to calculate complete order breakdown
     */
    private function calculateNewOrderBreakdown(
        $productsSubtotal,
        $additionalAmount,
        $couponDiscount,
        $chequeDiscount,
        $additionalDiscount,
        $liftGateFee,
        $residentialFee,
        $insideDeliveryFee,
        $shippingCharge,
        $taxPercentage
    ) {
        $subtotal = $productsSubtotal + $additionalAmount;
        $totalDiscounts = $couponDiscount + $chequeDiscount + $additionalDiscount;

        /* Calculate discounted amount */
        $discountedAmount = $subtotal
            - $couponDiscount
            - $chequeDiscount
            - $additionalDiscount
            + $liftGateFee
            + $residentialFee
            + $insideDeliveryFee;

        /* Calculate total based on website */
        if (in_array(config('app.website'), ['US', 'US_T'])) {
            /* US: Shipping is taxable */
            $amountBeforeTax = $discountedAmount + $shippingCharge;
            $taxAmount = round($amountBeforeTax * ($taxPercentage / 100), 2);
            $totalAmount = $discountedAmount + $taxAmount + $shippingCharge;
        } else {
            /* UAE: Shipping is not taxable */
            $amountBeforeTax = $discountedAmount;
            $taxAmount = round($discountedAmount * ($taxPercentage / 100), 2);
            $totalAmount = $discountedAmount + $taxAmount + $shippingCharge;
        }

        return [
            'products_subtotal' => round($productsSubtotal, 2),
            'additional_amount' => round($additionalAmount, 2),
            'subtotal' => round($subtotal, 2),
            'coupon_discount' => round($couponDiscount, 2),
            'cheque_discount' => round($chequeDiscount, 2),
            'additional_discount' => round($additionalDiscount, 2),
            'total_discounts' => round($totalDiscounts, 2),
            'subtotal_after_discounts' => round($subtotal - $totalDiscounts, 2),
            'lift_gate_fee' => round($liftGateFee, 2),
            'residential_delivery_fee' => round($residentialFee, 2),
            'inside_delivery_fee' => round($insideDeliveryFee, 2),
            'shipping_charge' => round($shippingCharge, 2),
            'amount_before_tax' => round($amountBeforeTax, 2),
            'tax_percentage' => round($taxPercentage, 2),
            'tax_amount' => round($taxAmount, 2),
            'total_amount' => round($totalAmount, 2)
        ];
    }

	/**
	 * @OA\Post(
	 *     path="/api/orders/{id}",
	 *     summary="Update an existing order (if not yet confirmed)",
	 *     tags={"Orders"},
	 *     @OA\Parameter(name="id", in="path", required=true, description="Order ID", @OA\Schema(type="integer")),
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="multipart/form-data",
	 *             @OA\Schema(
	 *                 required={"_method", "customer_address_id", "tax_percentage", "update_reason", "products"},
	 *                 @OA\Property(property="_method", type="string", example="PUT"),
	 *                 @OA\Property(property="customer_address_id", type="integer", example=1, description="Customer address ID"),
	 *                 @OA\Property(property="is_lift_gate", type="boolean", example=true, description="Lift gate required"),
	 *                 @OA\Property(property="is_residential_address", type="boolean", example=true, description="Residential address"),
	 *                 @OA\Property(property="is_inside_delivery", type="boolean", example=true, description="Inside delivery required"),
	 *                 @OA\Property(property="tax_percentage", type="number", format="float", example=5, description="Tax percentage"),
	 *                 @OA\Property(property="ship_all_at_once", type="boolean", example=true, description="Ship all items together"),
	 *                 @OA\Property(property="separate_deliveries", type="boolean", example=false, description="Separate deliveries"),
	 *
	 *                 @OA\Property(property="additional_amount_name", type="string", example="Accessory 1", description="Additional amount name"),
	 *                 @OA\Property(property="additional_amount_price", type="number", format="float", example=100, description="Additional amount price"),
	 *
	 *                 @OA\Property(property="coupon_id", type="integer", example=1, description="Coupon ID"),
	 *                 @OA\Property(property="discount", type="number", format="float", example=200, description="Discount amount"),
	 *
	 *                 @OA\Property(property="additional_discount_option", type="boolean", example=true, description="Additional Discount Option"),
	 *                 @OA\Property(property="additional_discount_reason", type="string", example="Bulk order discount", description="Reason for additional discount"),
	 *                 @OA\Property(property="additional_discount_type", type="string", enum={"fixed", "percentage"}, example="percentage"),
	 *                 @OA\Property(property="additional_discount_percentage", type="number", format="float", example=10.50, description="Additional discount percentage"),
	 *                 @OA\Property(property="additional_discount_amount", type="number", format="float", example=50.00, description="Additional discount amount"),
	 *
	 *                 @OA\Property(property="payment_mode", type="string", enum={"Stripe", "Check Payment", "Ascentium Financing", "Approve Financing", "Resolve Financing", "Net Terms"}, example="Check Payment", description="Payment mode"),
	 *                 @OA\Property(property="pay_with_cheque", type="boolean", example=false, description="Pay with cheque"),
	 *                 @OA\Property(property="cheque_img", type="string", format="binary", description="Cheque image (jpeg, png, webp only, max 5 MB)"),
	 *                 @OA\Property(property="cheque_img_back", type="string", format="binary", description="Cheque image (jpeg, png, webp only, max 5 MB)"),
	 *                 @OA\Property(property="cheque_img_url", type="string", example="https://example.com/image.png"),
	 *                 @OA\Property(property="cheque_img_back_url", type="string", example="https://example.com/image.png"),
	 *
	 *                 @OA\Property(property="update_reason", type="string", example="Customer requested changes", description="Reason for order update"),
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
	 *     @OA\Response(response=200, description="Updated successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function update(Request $request, $orderId)
	{
		$order = Order::with('orderProducts')->find($orderId);

		if (!$order) {
			return response()->json([
				'success' => false,
				'message' => 'Order not found'
			], 404);
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
				'message' => 'This order has already been shipped or delivered. You can no longer update it.'
			], 400);
		}

		/* For temporary use - Later updated by user forcefully */
		if (!$request->has('update_reason')) {
			$request->merge(['update_reason' => "Order updated by admin due to changes in order details."]);
		}

		/* Parse boolean strings to actual booleans */
		$booleanFields = [
			'is_lift_gate',
			'is_residential_address',
			'is_inside_delivery',
			'ship_all_at_once',
			'separate_deliveries',
			'pay_with_cheque',
			'additional_discount_option',
		];

		/* Parse products JSON string to array */
		foreach ($booleanFields as $field) {
			if ($request->has($field)) {
				$request->merge([
					$field => filter_var($request->input($field), FILTER_VALIDATE_BOOLEAN)
				]);
			}
		}
		if ($request->has('products') && is_string($request->products)) {
			$productsString = $request->products;
			if (strpos(trim($productsString), '{') === 0 && strpos(trim($productsString), '[') !== 0) {
				$productsString = '[' . $productsString . ']';
			}
			$products = json_decode($productsString, true);
			$request->merge(['products' => $products]);
		}

		$request->validate([
			'customer_address_id' => 'required|integer|exists:customer_addresses,id',
			'is_lift_gate' => 'nullable|boolean',
			'is_residential_address' => 'nullable|boolean',
			'is_inside_delivery' => 'nullable|boolean',
			'tax_percentage' => 'required|numeric|min:0',
			'ship_all_at_once' => 'nullable|boolean',
			'separate_deliveries' => 'nullable|boolean',

			'additional_amount_name' => 'nullable|required_with:additional_amount_price|string|max:255',
			'additional_amount_price' => 'nullable|required_with:additional_amount_name|numeric|min:0',

			'coupon_id' => 'nullable|integer',
			'discount' => 'nullable|numeric|min:0',

			'payment_mode' => 'nullable|in:Stripe,Check Payment,Ascentium Financing,Approve Financing,Resolve Financing,Net Terms',
			'pay_with_cheque' => 'nullable|boolean',
			'cheque_img' => 'nullable|file|mimes:jpeg,jpg,png,webp|max:5120',
			'cheque_img_back' => 'nullable|file|mimes:jpeg,jpg,png,webp|max:5120',
			'cheque_img_url' => 'nullable|string',
			'cheque_img_back_url' => 'nullable|string',

			'additional_discount_option' => 'nullable|boolean',
			'additional_discount_reason' => 'nullable|string|max:255',
			'additional_discount_type' => 'nullable|in:fixed,percentage',
			'additional_discount_percentage' => 'nullable|numeric|min:0|max:100|required_if:additional_discount_type,percentage',
			'additional_discount_amount' => 'nullable|numeric|min:0|required_if:additional_discount_type,fixed',

			'update_reason' => 'required|string',

			'products' => 'required|array|min:1',
			'products.*.product_id' => 'required|integer|exists:ec_products,id',
			'products.*.vendor_id' => 'required|integer|exists:vendors,id',
			'products.*.quantity' => 'required|integer|min:1',
			'products.*.accessory_item_ids' => 'nullable|array',
			'products.*.accessory_item_ids.*' => 'integer|exists:accessory_items,id',
		]);

		$customerId = $order->customer_id;

		$address = CustomerAddress::where('id', $request->customer_address_id)->where('customer_id', $customerId)->first();

		if (!$address) {
			return response()->json([
				'success' => false,
				'message' => 'The selected address does not belong to the customer.'
			], 422);
		}
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

		$discount = $request->discount ?? 0;
		$totalProducts = 0;
		$orderAmount = 0;
		$orderShipping = 0;

		foreach ($productDetails as $product) {
			$totalProducts += $product['quantity'];
			$orderAmount += ($product['quantity'] * $product['unit_price']) + $product['accessory_item_charge'];
			$orderShipping += $product['shipping_charge'];
		}

		/* Handle Additional Amount Price */
		if (!empty($request->additional_amount_price)) {
			$orderAmount += (float) $request->additional_amount_price;
		}

		/* Handle Coupon Discount */
		$discountedAmount = $orderAmount - $discount;

		/* Handle Additional Discount */
		if ($request->additional_discount_option) {
			$additionalDiscountReason = $request->additional_discount_reason;
			$additionalDiscountType = $request->additional_discount_type;
			if ($additionalDiscountType == 'fixed') {
				$additionalDiscountPercentage = null;
				$additionalDiscountAmount = $request->additional_discount_amount ?? 0;
			} else if ($additionalDiscountType == 'percentage') {
				$additionalDiscountPercentage = $request->additional_discount_percentage;
				$additionalDiscountAmount = round($discountedAmount * $additionalDiscountPercentage / 100, 2);
			}
			$discountedAmount -= $additionalDiscountAmount;
		} else {
			$additionalDiscountReason = null;
			$additionalDiscountType = null;
			$additionalDiscountPercentage = null;
			$additionalDiscountAmount = 0;
		}

		/* Handle cheque payment discount */
		$payWithCheque = $order->pay_with_cheque;
		$paymentMode = $order->payment_mode;
		if ($payWithCheque && $paymentMode == 'Check Payment') {
			if ($request->hasFile('cheque_img')) {
				$chequeImg = uploadImageToWebpS3FromFile(
					$request,
					'cheque_img',
					env('STORAGE_ENV') . '/customer/orders'
				);
			} elseif (!empty($request->cheque_img_url)) {
				$chequeImg = $request->cheque_img_url;
			}
			if ($request->hasFile('cheque_img_back')) {
				$chequeImgBack = uploadImageToWebpS3FromFile(
					$request,
					'cheque_img_back',
					env('STORAGE_ENV') . '/customer/orders'
				);
			} elseif (!empty($request->cheque_img_back_url)) {
				$chequeImgBack = $request->cheque_img_back_url;
			}
			$createdByStaff = $order->created_by > 0;
			$chequeDiscountPercentage = $createdByStaff ? 0 : cheque_discount_percentage();
			$chequeDiscount = round($discountedAmount * $chequeDiscountPercentage / 100, 2);
			$discountedAmount -= $chequeDiscount;
		} else {
			$chequeImg = null;
			$chequeImgBack = null;
			$chequeDiscountPercentage = 0;
			$chequeDiscount = 0;
		}

		/* Add extra charges */
		$discountedAmount += $request->boolean('is_lift_gate') ? 75 : 0;
		$discountedAmount += $request->boolean('is_residential_address') ? 199 : 0;
		$discountedAmount += $request->boolean('is_inside_delivery') ? 249 : 0;

		/* Tax rules */
		$customer = $order->customer;
		$taxPercentage = $customer->is_tax_free ? 0 : $request->tax_percentage;

		if (in_array(config('app.website'), ['UAE', 'UAE_T'])) {
			$taxAmount = round($discountedAmount * ($taxPercentage / 100), 2);
			$orderShipping = ($discountedAmount + $taxAmount) < 500 ? 30 : 0;
		} elseif (in_array(config('app.website'), ['US', 'US_T'])) {
			$taxableAmount = $discountedAmount + $orderShipping;
			$taxAmount = round($taxableAmount * ($taxPercentage / 100), 2);
		} else {
			$taxAmount = round($discountedAmount * ($taxPercentage / 100), 2);
		}
		$totalAmount = $discountedAmount + $taxAmount + $orderShipping;

		$paidAmount = $order->paid_amount ?? 0;
		$pendingAmount = $totalAmount - $paidAmount;

		/* Get original total amount before update */
		$originalTotalAmount = $order->total_amount;
		$prevPendingAmount = $order->pending_amount;

		DB::beginTransaction();
		try {
			$order->update([
				'customer_address_id' => $request->customer_address_id,
				'is_lift_gate' => $request->is_lift_gate,
				'is_residential_address' => $request->is_residential_address,
				'is_inside_delivery' => $request->is_inside_delivery,
				'amount' => $orderAmount,

				'additional_amount_name' => $request->additional_amount_name ?? null,
				'additional_amount_price' => $request->additional_amount_price ?? null,

				'coupon_id' => $request->coupon_id ?? null,
				'discount' => $discount,

				'additional_discount_reason' => $additionalDiscountReason,
				'additional_discount_type' => $additionalDiscountType,
				'additional_discount_percentage' => $additionalDiscountPercentage,
				'additional_discount_amount' => $additionalDiscountAmount,

				'cheque_discount_percentage' => $chequeDiscountPercentage,
				'cheque_discount' => $chequeDiscount,
				'cheque_img' => $chequeImg,
				'cheque_img_back' => $chequeImgBack,

				'tax_percentage' => $taxPercentage,
				'tax_amount' => $taxAmount,
				'shipping_charge' => $orderShipping,

				'total_amount' => $totalAmount,
				'total_products' => $totalProducts,
				'ship_all_at_once' => $request->get('ship_all_at_once', true),
				'separate_deliveries' => $request->get('separate_deliveries', false),
				'paid_amount' => $paidAmount,
				'is_paid' => $pendingAmount <= 0,
				'pending_amount' => $pendingAmount,
				'updated_by' => auth()->id()
			]);

			/* Delete existing products and re-insert */
			OrderProduct::where('order_id', $order->id)->delete();

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
				'status' => 'Order Updated By Backend Panel',
				'description' => $originalTotalAmount != $totalAmount ? "Amount changed from {$originalTotalAmount} to {$totalAmount}. " . ($request->update_reason ?? '') : ($request->update_reason ?? ''),
				'created_by' => auth()->id()
			]);

			if ($pendingAmount > 0) {
				$paymentLink = null;
				if (in_array(config('app.website'), ['UAE', 'UAE_T'])) {
					try {
						$paymentLink = app(\App\Http\Controllers\FrontEnd\CcavenueController::class)->createCCavenuePaymentLink($order);
						if ($paymentLink) {
							$order = Order::find($order->id);
							$order->payment_link = $paymentLink;
							$order->save();
						}
					} catch (\Exception $e) {
						\Log::error('Paymob Payment Link generation failed', [
							'order_id' => $order->id,
							'error' => $e->getMessage(),
							'trace' => $e->getTraceAsString()
						]);
					}
				} else if (in_array(config('app.website'), ['US', 'US_T'])) {
					try {
						$paymentLink = app(\App\Http\Controllers\FrontEnd\StripeController::class)->generatePaymentLink($order);
						if ($paymentLink) {
							$order = Order::find($order->id);
							$order->payment_link = $paymentLink;
							$order->save();
						}
					} catch (\Exception $e) {
						\Log::error('Stax Payment Link generation failed', [
							'order_id' => $order->id,
							'error' => $e->getMessage(),
							'trace' => $e->getTraceAsString()
						]);
					}
				}
			}

			DB::commit();

			if ($originalTotalAmount != $totalAmount || $prevPendingAmount != $pendingAmount) {
				$batch = Bus::batch([])->name("Order Update by Backend - #{$order->order_number}")->dispatch();
				$batch->options['queue'] = config('app.website') . '_ORD_UPDT';
				$batch->add(new OrderUpdateMailJob([
					'recordId' => $order->id,
					'originalTotalAmount' => $originalTotalAmount,
					'updateReason' => $request->update_reason,
				]));
			}

			/* Load relationships */
			// $order->load([
			// 	'orderProducts:id,order_id,product_id,vendor_id,quantity,unit_price,amount,shipping_charge,total_amount,status,accessory_item_charge',
			// 	'orderProducts.accessoryCharges:id,relation_type,relation_id,accessory_item_id,amount',
			// 	'orderProducts.accessoryCharges.accessoryItem:id,product_accessory_id,name,price',
			// 	'orderProducts.accessoryCharges.accessoryItem.accessory:id,name',
			// 	'orderProducts.product:id,name,images,sku,brand_id,currency_id,barcode',
			// 	'orderProducts.product.brand:id,name',
			// 	'orderProducts.product.currency:id,symbol',
			// 	'tracking',
			// 	'payments:id,order_id,transaction_id,payment_mode,amount,status,notes,created_at'
			// ]);

			// /* Mutate the data for each order product */
			// foreach ($order->orderProducts as $orderProduct) {
			// 	$product = $orderProduct->product;
			// 	if ($product) {
			// 		$product->images = is_array($product->images) ? $product->images : (is_array($decoded = json_decode($product->images, true)) ? $decoded : null);
			// 		$product->brand_name = $product->brand->name ?? null;
			// 		$product->currency_symbol = $product->currency->symbol ?? null;
			// 		unset($product->brand, $product->currency);
			// 	}
			// 	$orderProduct->product_supplier = optional($orderProduct->vendor_product_supplier)->only(['price', 'sale_price', 'shipping_charge', 'delivery_days', 'return_policy']);
			// 	$orderProduct->expectedShippingDate = $orderProduct->product_supplier
			// 	? getDateRange($order->created_at, $orderProduct->product_supplier['delivery_days'])
			// 	: null;

			// 	$shipping = $orderProduct->shipping_charge ?? 0;
			// 	if (in_array(config('app.website'), ['US', 'US_T'])) {
			// 		$state = $order->customerAddress->state ?? null;

			// 		if (!$order->is_customer_pickup) {
			// 			if ($state === 'Texas') {
			// 				$shipping = ($shipping > 0) ? $shipping : 99;
			// 			} else {
			// 				$shipping = ($shipping > 0) ? $shipping : 199;
			// 			}
			// 		} else {
			// 			$shipping = 0;
			// 		}
			// 	}
			// 	$orderProduct->shipping_charge = $shipping;

			// 	if ($orderProduct->accessoryCharges) {
			// 		$orderProduct->accessory_charges = $orderProduct->accessoryCharges->map(function ($charge) {
			// 			return [
			// 				'id' => $charge->id,
			// 				'accessory_item_id' => $charge->accessory_item_id,
			// 				'accessory_item_name' => $charge->accessoryItem->name ?? null,
			// 				'accessory_item_price' => $charge->accessoryItem->price ?? null,
			// 				'product_accessory_id' => $charge->accessoryItem->accessory->id ?? null,
			// 				'product_accessory_name' => $charge->accessoryItem->accessory->name ?? null,
			// 				'amount' => $charge->amount,
			// 			];
			// 		});

			// 		unset($orderProduct->accessoryCharges);
			// 	}

			// 	/* Format numeric values to 2 decimal places */
			// 	foreach (['unit_price', 'amount', 'shipping_charge', 'total_amount'] as $key) {
			// 		if (isset($quoteProduct->$key)) {
			// 			$quoteProduct->$key = number_format($quoteProduct->$key, 2, '.', '');
			// 		}
			// 	}
			// }

			// foreach (['shipping_charge', 'amount', 'tax_amount', 'discount', 'additional_discount', 'total_amount', 'paid_amount', 'pending_amount'] as $key) {
			// 	if (isset($order->$key)) {
			// 		$order->$key = number_format($order->$key, 2, '.', '');
			// 	}
			// }

			return response()->json([
				'success' => true,
				'message' => 'Order updated successfully',
				'data' => $order
			], 200);

		} catch (\Exception $e) {
			DB::rollBack();

			return response()->json([
				'success' => false,
				'message' => 'Failed to update order: ' . $e->getMessage()
			], 500);
		}
	}

	/**
	 * @OA\Put(
	 *     path="/api/orders/{id}/status",
	 *     summary="Update order status",
	 *     tags={"Orders"},
	 *     @OA\Parameter(name="id", in="path", description="Order ID", required=true, @OA\Schema(type="integer")),
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"status"},
	 *             @OA\Property(property="status", type="string"),
	 *             @OA\Property(property="notes", type="string")
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Order status updated successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function updateStatus(Request $request, $id)
	{
		$order = Order::find($id);

		if (!$order) {
			return response()->json([
				'success' => false,
				'message' => "Order not found."
			]);
		}

		$request->validate([
			'status' => 'required|string|in:Confirmed,Supplier Delivery,International,Export,On hold,Ready to ship,Pickups,Out for delivery,Delivered,Cancelled',
			'notes' => 'nullable|string'
		]);

		$oldStatus = $order->status;
		$newStatus = $request->status;

		if ($oldStatus == 'Cancelled') {
			return response()->json([
				'success' => false,
				'message' => "Status cannot be changed because the order is already cancelled."
			]);
		}

		/* Order Cancellation Validation */
		if ($newStatus === 'Cancelled') {
			if ($oldStatus !== 'Pending') {
				return response()->json([
					'success' => false,
					'message' => "Order can only be cancelled if it is in 'Pending' status."
				]);
			}
			$order->update([
				'status' => $newStatus,
			]);

			$order->orderProducts()->update(['status' => $newStatus]);

			/* Add tracking */
			OrderTracking::create([
				'order_id' => $order->id,
				'status' => "Order status changed to {$newStatus} by backend panel",
				'description' => "Order status changed from {$oldStatus} to {$newStatus}." . ($request->notes ? " {$request->notes}" : ''),
				'created_by' => auth()->id(),
			]);

			$batch = Bus::batch([])->name("Order Cancelled by Backend - #{$order->order_number}")->dispatch();
			$batch->options['queue'] = config('app.website') . '_ORD_CNCL';
			$batch->add(new OrderCancelledMailJob([
				'recordId' => $order->id
			]));

			return response()->json([
				'success' => true,
				'message' => 'Order status updated successfully',
				'data' => $order->fresh(['tracking'])
			]);
		}

		if ($newStatus == 'Delivered' && $order->is_reserved) {
			return response()->json([
				'success' => false,
				'message' => "This order is reserved and cannot be marked as delivered."
			]);
		}

		/* Other status validation flow */
		$otherStatus = [
			'Pending',
			'Confirmed',
			['Supplier Delivery', 'International', 'Export', 'On hold'],
			'Ready to ship',
			'Pickups',
			'Out for delivery',
			'Delivered'
		];

		$findStatusIndex = function ($status) use ($otherStatus) {
			foreach ($otherStatus as $index => $step) {
				if (is_array($step) && in_array($status, $step)) {
					return $index;
				}
				if ($step === $status) {
					return $index;
				}
			}
			return null;
		};

		$oldStatusIndex = $findStatusIndex($oldStatus);
		$newStatusIndex = $findStatusIndex($newStatus);

		if ($oldStatusIndex < $newStatusIndex - 1) {
			// if (condition) {
			// 	// code...
			// }
			return response()->json([
				'success' => false,
				'message' => "Invalid status update: You cannot skip directly from '{$oldStatus}' to '{$newStatus}'. Please follow the correct order flow."
			]);
		} elseif ($oldStatusIndex == $newStatusIndex) {
			if ($oldStatus == $newStatus) {
				return response()->json([
					'success' => false,
					'message' => "Order is already in '{$oldStatus}' status. Please choose a different status."
				]);
			}
		} elseif ($oldStatusIndex > $newStatusIndex) {
			return response()->json([
				'success' => false,
				'message' => "Invalid status update: You cannot move backwards from '{$oldStatus}' to '{$newStatus}'."
			]);
		}

		/* Validate shipment and quantity for delivery-related statuses */
		$deliveryStatuses = ['Pickups', 'Out for delivery', 'Delivered'];

		if (in_array($newStatus, $deliveryStatuses)) {

			/* Check if order has any shipments */
			if (!$order->shipments()->exists()) {
				return response()->json([
					'success' => false,
					'message' => "Order cannot be marked as '{$newStatus}' because no shipments are available."
				]);
			}

			/* Calculate total quantity from shipmentProducts */
			$totalShippedQuantity = 0;

			foreach ($order->shipments as $shipment) {
				foreach ($shipment->shipmentProducts as $shipmentProduct) {
					$totalShippedQuantity += $shipmentProduct->quantity;
				}
			}

			if ($totalShippedQuantity !== $order->total_products) {
				return response()->json([
					'success' => false,
					'message' => "Order cannot be marked as '{$newStatus}' because total shipped quantity ({$totalShippedQuantity}) does not match the total ordered products ({$order->total_products})."
				]);
			}
		}

		/* Update order and products */
		$order->update([
			'status' => $newStatus,
		]);

		$order->orderProducts()->where('status', '!=', 'Cancelled')->update(['status' => $newStatus]);

		/* Add tracking */
		OrderTracking::create([
			'order_id' => $order->id,
			'status' => "Order status changed to {$newStatus} by backend panel",
			'description' => "Order status changed from {$oldStatus} to {$newStatus}." . ($request->notes ? " {$request->notes}" : ''),
			'created_by' => auth()->id(),
		]);

		if ($newStatus == 'Confirmed') {
			$batch = Bus::batch([])->name("Order Confirmation - #{$order->order_number}")->dispatch();
			$batch->options['queue'] = config('app.website') . '_ORD_CNF';
			$batch->add(new OrderConfirmationMailJob([
				'recordId' => $order->id
			]));
		}

		if ($newStatus == 'Out for delivery') {
			$batch = Bus::batch([])->name("Order Out for Delivery - #{$order->order_number}")->dispatch();
			$batch->options['queue'] = config('app.website') . '_ORD_OUT';
			$batch->add(new OutDeliveryMailJob([
				'recordId' => $order->id
			]));
		}

		if ($newStatus == 'Delivered') {
			$batch = Bus::batch([])->name("Order Delivered - #{$order->order_number}")->dispatch();
			$batch->options['queue'] = config('app.website') . '_ORD_DLVR';
			$batch->add(new OrderDeliveredMailJob([
				'recordId' => $order->id
			]));
		}

		return response()->json([
			'success' => true,
			'message' => 'Order status updated successfully',
			'data' => $order->fresh(['tracking'])
		]);
	}

	/**
	 * @OA\Put(
	 *     path="/api/orders/{orderId}/products/{orderProductId}/status",
	 *     summary="Update specific item status",
	 *     tags={"Orders"},
	 *     @OA\Parameter(name="orderId", in="path", description="Order ID", required=true, @OA\Schema(type="integer")),
	 *     @OA\Parameter(name="orderProductId", in="path", description="Order product ID", required=true, @OA\Schema(type="integer")),
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"status"},
	 *             @OA\Property(property="status", type="string"),
	 *             @OA\Property(property="notes", type="string")
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Product status updated successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function updateProductStatus(Request $request, $orderId, $orderProductId)
	{
		$order = Order::find($orderId);

		if (!$order) {
			return response()->json([
				'success' => false,
				'message' => "Order not found."
			]);
		}

		$orderProduct = $order->orderProducts()->find($orderProductId);

		if (!$orderProduct) {
			return response()->json([
				'success' => false,
				'message' => "Order Product not found in the order."
			]);
		}

		$request->validate([
			'status' => 'required|string|in:Supplier Delivery,International,Export,On hold,Ready to ship,Pickups,Partially Pickups,Out for delivery,Partially Out for delivery,Delivered,Partially Delivered,Cancelled',
			'notes' => 'nullable|string'
		]);

		$oldStatus = $orderProduct->status;
		$newStatus = $request->status;

		if ($oldStatus == 'Cancelled') {
			return response()->json([
				'success' => false,
				'message' => "Status cannot be changed because the order product is already cancelled."
			]);
		}

		/* Order Cancellation Validation */
		if ($newStatus === 'Cancelled') {
			if ($oldStatus !== 'Pending') {
				return response()->json([
					'success' => false,
					'message' => "Order product can only be cancelled if it is in 'Pending' status."
				]);
			}
			$orderProduct->update([
				'status' => $newStatus,
			]);

			/* Add tracking entry */
			OrderTracking::create([
				'order_id' => $order->id,
				'status' => "Order status changed to {$newStatus} by backend panel",
				'description' => "Order product status changed from {$oldStatus} to {$newStatus}." . ($request->notes ? " {$request->notes}" : ''),
				'metadata' => json_encode([
					'order_product_id' => $orderProduct->id,
					'product_name' => $orderProduct->product->name ?? '',
				]),
				'created_by' => auth()->id(),
			]);

			$batch = Bus::batch([])->name("Order Partially Cancelled by Backend - #{$order->order_number}")->dispatch();
			$batch->options['queue'] = config('app.website') . '_ORD_PART_CNCL';
			$batch->add(new PartialOrderCancelledMailJob([
				'recordId' => $orderProduct->id,
				'reason' => $request->notes,
			]));

			return response()->json([
				'success' => true,
				'message' => 'Order product status updated successfully',
				'data' => $orderProduct->fresh()
			]);
		}

		/* Prevent status update before order is confirmed */
		if ($order->status === 'Pending') {
			return response()->json([
				'success' => false,
				'message' => "Order Product status cannot be changed until the order is confirmed."
			]);
		}

		if ($newStatus == 'Delivered' && $order->is_reserved) {
			return response()->json([
				'success' => false,
				'message' => "This product belongs to a reserved order and cannot be marked as delivered."
			]);
		}

		/* Other status validation flow */
		$otherStatus = [
			'Confirmed',
			['Supplier Delivery', 'International', 'Export', 'On hold'],
			'Ready to ship',
			['Pickups', 'Partially Pickups'],
			['Out for delivery', 'Partially Out for delivery'],
			['Delivered', 'Partially Delivered'],
		];

		$findStatusIndex = function ($status) use ($otherStatus) {
			foreach ($otherStatus as $index => $step) {
				if (is_array($step) && in_array($status, $step)) {
					return $index;
				}
				if ($step === $status) {
					return $index;
				}
			}
			return null;
		};

		$oldStatusIndex = $findStatusIndex($oldStatus);
		$newStatusIndex = $findStatusIndex($newStatus);

		if ($oldStatusIndex < $newStatusIndex - 1) {
			return response()->json([
				'success' => false,
				'message' => "Invalid status update: You cannot skip directly from '{$oldStatus}' to '{$newStatus}'. Please follow the correct order flow."
			]);
		} elseif ($oldStatusIndex == $newStatusIndex) {
			if ($oldStatus == $newStatus) {
				return response()->json([
					'success' => false,
					'message' => "Order product is already in '{$oldStatus}' status. Please choose a different status."
				]);
			}
		} elseif ($oldStatusIndex > $newStatusIndex) {
			return response()->json([
				'success' => false,
				'message' => "Invalid status update: You cannot move backwards from '{$oldStatus}' to '{$newStatus}'."
			]);
		}

		$fullStatuses = ['Pickups', 'Out for delivery', 'Delivered'];
		$partialStatuses = ['Partially Pickups', 'Partially Out for delivery', 'Partially Delivered'];

		if (in_array($newStatus, array_merge($fullStatuses, $partialStatuses))) {

			$shipmentProducts = $orderProduct->shipmentProducts;

			if ($shipmentProducts->isEmpty()) {
				return response()->json([
					'success' => false,
					'message' => "Cannot mark product as '{$newStatus}' because it has no shipment records."
				]);
			}

			$totalShipped = 0;
			foreach ($shipmentProducts as $shipmentProduct) {
				$totalShipped += $shipmentProduct->quantity;
			}

			if (in_array($newStatus, $fullStatuses)) {
				if ($totalShipped !== $orderProduct->quantity) {
					return response()->json([
						'success' => false,
						'message' => "Cannot mark product as '{$newStatus}' because shipped quantity ({$totalShipped}) does not match ordered quantity ({$orderProduct->quantity})."
					]);
				}
			} elseif (in_array($newStatus, $partialStatuses)) {
				if ($totalShipped <= 0 || $totalShipped >= $orderProduct->quantity) {
					return response()->json([
						'success' => false,
						'message' => "Cannot mark product as '{$newStatus}' because shipped quantity ({$totalShipped}) must be greater than 0 and less than ordered quantity ({$orderProduct->quantity})."
					]);
				}
			}
		}

		$orderProduct->status = $newStatus;
		$orderProduct->save();

		/* Get all product statuses from this order */
		$productStatuses = $order->orderProducts()->pluck('status')->toArray();

		/* Check if all product statuses are the same */
		$allSame = count(array_unique($productStatuses)) === 1;

		if ($allSame) {
			$order->status = $productStatuses[0];
		} elseif (in_array('Delivered', $productStatuses) || in_array('Partially Delivered', $productStatuses)) {
			$order->status = 'Partially Delivered';
		}

		$order->save();

		/* Add tracking entry */
		OrderTracking::create([
			'order_id' => $order->id,
			'status' => 'Order product status changed to ' . $newStatus . ' by backend panel',
			'description' => "Order product status changed from {$oldStatus} to {$newStatus}." . ($request->notes ? " {$request->notes}" : ''),
			'metadata' => json_encode([
				'order_product_id' => $orderProduct->id,
				'product_name' => $orderProduct->product->name ?? '',
			]),
			'created_by' => auth()->id(),
		]);

		return response()->json([
			'success' => true,
			'message' => 'Product status updated successfully',
			'data' => $orderProduct->fresh()
		]);
	}

	/**
	 * @OA\Post(
	 *     path="/api/orders/{id}/shipments",
	 *     summary="Create a shipment for an order (supports partial delivery)",
	 *     tags={"Orders"},
	 *     @OA\Parameter(name="id", in="path", description="Order ID", required=true, @OA\Schema(type="integer")),
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"products"},
	 *             @OA\Property(
	 *                 property="products",
	 *                 type="array",
	 *                 @OA\Items(
	 *                     required={"order_product_id", "quantity"},
	 *                     @OA\Property(property="order_product_id", type="integer"),
	 *                     @OA\Property(property="quantity", type="integer")
	 *                 )
	 *             ),
	 *             @OA\Property(property="tracking_number", type="string"),
	 *             @OA\Property(property="carrier", type="string"),
	 *             @OA\Property(property="notes", type="string"),
	 *             @OA\Property(property="estimated_delivery_date", type="string", format="date", example="2025-07-09")
	 *         )
	 *     ),
	 *     @OA\Response(response=201, description="Shipment created successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}},
	 * )
	 */
	public function createShipment(Request $request, $id)
	{
		/* Fetch order with related products */
		$order = Order::with('orderProducts')->find($id);

		if (!$order) {
			return response()->json([
				'success' => false,
				'message' => 'Order not found.'
			], 404);
		}

		/* Allow shipment creation only when the order is "Ready to ship" */
		if ($order->status !== 'Ready to ship') {
			return response()->json([
				'success' => false,
				'message' => 'Shipment can only be created when the order status is "Ready to ship".'
			]);
		}

		/* Validate input */
		$request->validate([
			'products' => 'required|array|min:1',
			'products.*.order_product_id' => 'required|integer|exists:order_products,id',
			'products.*.quantity' => 'required|integer|min:1',
			'tracking_number' => 'nullable|string|max:255',
			'carrier' => 'nullable|string|max:255',
			'notes' => 'nullable|string|max:500',
		]);

		DB::beginTransaction();

		try {
			/* Create shipment record */
			$shipment = Shipment::create([
				'order_id' => $order->id,
				'shipment_number' => 'SHP-' . strtoupper(Str::random(8)),
				'tracking_number' => $request->tracking_number,
				'carrier' => $request->carrier,
				'notes' => $request->notes,
				'estimated_delivery_date' => $request->estimated_delivery_date
			]);

			/* Process each product */
			foreach ($request->products as $productData) {
				$orderProduct = OrderProduct::where('id', $productData['order_product_id'])
				->where('order_id', $order->id)
				->firstOrFail();

				if ($productData['quantity'] > $orderProduct->remaining_quantity) {
					throw new \Exception("Cannot ship more than remaining quantity for product ID {$orderProduct->id}");
				}

				/* Create shipment product */
				ShipmentProduct::create([
					'shipment_id' => $shipment->id,
					'order_product_id' => $orderProduct->id,
					'quantity' => $productData['quantity'],
				]);

				/* Update remaining quantity */
				$orderProduct->shipped_quantity += $productData['quantity'];
				$orderProduct->remaining_quantity -= $productData['quantity'];
				$orderProduct->save();
			}

			/* Update order delivery status */
			$order->save();

			/* Add tracking entry */
			OrderTracking::create([
				'order_id' => $order->id,
				'shipment_id' => $shipment->id,
				'status' => 'Order shipment created by backend panel',
				'description' => 'Order Shipment created with tracking number: ' . $request->tracking_number,
				'created_by' => auth()->id(),
			]);

			DB::commit();

			return response()->json([
				'success' => true,
				'message' => 'Shipment created successfully',
				'data' => $shipment->load('shipmentProducts.orderProduct')
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