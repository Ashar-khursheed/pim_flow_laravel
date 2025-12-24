<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

use Illuminate\Support\Facades\Bus;
use Illuminate\Bus\Batch;

use App\Models\FrontEnd\CustomerCart;
use App\Models\FrontEnd\CustomerCartProduct;
use App\Models\FrontEnd\CustomerAddress;
use App\Models\FrontEnd\Customer;
use App\Jobs\Order\CartCreationMailJob;

class CustomerCartController extends Controller
{
	/**
	 * @OA\Get(
	 *     path="/api/frontend/carts",
	 *     summary="Get all carts with pagination and filters",
	 *     tags={"Frontend-Carts"},
	 *     @OA\Parameter(name="page", in="query", description="Page number for pagination", @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="length", in="query", description="Number of records per page.", @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="global", in="query", description="Global search for all fields", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="sort_by", in="query", description="Column name to sort by", @OA\Schema(type="string", enum={"id", "reference_number", "shipping_charge", "total_amount", "total_products", "created_at", "updated_at"})),
	 *     @OA\Parameter(name="sort_dir", in="query", description="Sort direction (asc or desc)", @OA\Schema(type="string", enum={"asc", "desc"})),
	 *     @OA\Response(response=200, description="List retrieved successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function index(Request $request)
	{
		$searchableColumns = ['id', 'reference_number'];
		$sortableColumns = array_merge($searchableColumns, ['shipping_charge', 'total_amount', 'total_products', 'created_at', 'updated_at']);

		$sortBy = in_array($request->input('sort_by'), $sortableColumns) ? $request->input('sort_by') : 'id';
		$sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

		$recordsQuery = CustomerCart::where('customer_id', auth()->id());

		/* Eager load relationships */
		$recordsQuery->with([
			'customerCartProducts:id,customer_cart_id,product_id,vendor_id,quantity',
			'customerCartProducts.product:id,name,images,sku,brand_id,currency_id,barcode',
			'customerCartProducts.product.brand:id,name',
			'customerCartProducts.product.currency:id,symbol',
		]);

		/* Global search */
		if ($request->filled('global')) {
			$search = $request->input('global');
			$recordsQuery->where(function ($q) use ($searchableColumns, $search) {
				foreach ($searchableColumns as $col) {
					$q->orWhere("customer_carts.$col", 'like', '%' . $search . '%');
				}
			});
		}

		/* Sorting */
		$recordsQuery->orderBy($sortBy, $sortDir);

		/* Check if pagination requested */
		$page = 1;
		$totalPages = 1;
		$length = $totalRecords = (clone $recordsQuery)->count();
		if ($request->filled('page') && $request->filled('length')) {

			/* Pagination */
			$length = (int) $request->input('length');
			$page = (int) $request->input('page');

			$totalPages = (int) ceil($totalRecords / $length);

			if ($page > $totalPages && $totalPages > 0) {
				$page = 1;
			}
		}

		$records = $recordsQuery
		->offset(($page - 1) * $length)
		->limit($length)
		->get(['id', 'reference_number', 'customer_id', 'created_at']);

		/* Transform results */
		$records->transform(function ($record) {
			/* Process each product in customer cart products */

			$totalProducts = 0;
			$cartAmount = 0;
			$cartShipping = 0;
			$cartProducts = [];

			foreach ($record->customerCartProducts as $customerCartProduct) {
				$product = $customerCartProduct->product;
				if (!$product) continue;

				/* Decode images if stored as JSON string */
				$images = is_array($product->images) ? $product->images : (is_array($decoded = json_decode($product->images, true)) ? $decoded : null);
				$image = $images[0] ?? null;

				$supplier = optional($customerCartProduct->vendor_product_supplier)->only(['price', 'sale_price', 'shipping_charge', 'delivery_days']);

				$unitPrice = 0;
				$shippingCharge = 0;
				if ($supplier) {
					$unitPrice = ($supplier['sale_price'] > 0 && $supplier['sale_price'] < $supplier['price']) ? $supplier['sale_price'] : $supplier['price'];
					$shippingCharge = $supplier['shipping_charge'] ?? 0;

					$expectedShippingDate = getDateRange($record->created_at, $supplier['delivery_days']);
				}

				$quantity = $customerCartProduct->quantity ?? 0;
				$subTotal = $quantity * $unitPrice;

				$totalProducts += $quantity;
				$cartAmount += $subTotal;
				$cartShipping += $shippingCharge;

				/* Push product data */
				$cartProducts[] = [
					'product_id'      		=> $customerCartProduct->product_id,
					'vendor_id'       		=> $customerCartProduct->vendor_id,
					'image'           		=> $image,
					'name'            		=> $product->name,
					'url'            		=> config('app.url') . '/products/' . $product->seoProductUrl->url ?? $product->id,
					'currency_symbol' 		=> $product->currency->symbol ?? null,
					'unit_price'      		=> number_format($unitPrice, 2, '.', ''),
					'quantity'        		=> $quantity,
					'expectedShippingDate'  => $expectedShippingDate,
					'sub_total'       		=> number_format($subTotal, 2, '.', ''),
					'shipping_charge' 		=> number_format($shippingCharge, 2, '.', ''),
				];
			}


			/* Tax calculations */
			$taxPercentage = $record->tax_percentage ?? 0;
			$taxAmount = round(($cartAmount * $taxPercentage) / 100, 2);

			/* Website-specific shipping rules */
			if (in_array(config('app.website'), ['UAE', 'UAE_T'])) {
				$cartShipping = ($cartAmount + $taxAmount) < 500 ? 30 : 0;
			}

			$totalAmount = $cartAmount + $taxAmount + $cartShipping;

			/* Prepare cart summary */
			$record = [
				'amount'                 => number_format($cartAmount, 2, '.', ''),
				'tax_amount'             => number_format($taxAmount, 2, '.', ''),
				'shipping_charge'        => number_format($cartShipping, 2, '.', ''),
				'total_amount'           => number_format($totalAmount, 2, '.', ''),
				'total_products'         => $totalProducts,
				'products'               => $cartProducts,
			];

			return $record;
		});

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
	 *     path="/api/frontend/carts",
	 *     summary="Create a new cart",
	 *     tags={"Frontend-Carts"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"products"},
	 *             @OA\Property(property="customer_address_id", type="integer", example="1"),
	 *             @OA\Property(property="tax_percentage", type="number", example=5),
	 *             @OA\Property(
	 *                 property="products",
	 *                 type="array",
	 *                 @OA\Items(
	 *                     required={"product_id", "vendor_id", "quantity"},
	 *                     @OA\Property(property="product_id", type="integer", example=101),
	 *                     @OA\Property(property="vendor_id", type="integer", example=22),
	 *                     @OA\Property(property="quantity", type="integer", example=5)
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
			'customer_address_id' => 'nullable|integer|exists:customer_addresses,id',
			'tax_percentage' => 'required|numeric|min:0',
			'products' => 'required|array|min:1',
			'products.*.product_id' => 'required|integer|exists:ec_products,id',
			'products.*.vendor_id' => 'required|integer|exists:vendors,id',
			'products.*.quantity' => 'required|integer|min:1',
		]);

		if ($request->customer_address_id) {
			$address = CustomerAddress::where('id', $request->customer_address_id)
			->where('customer_id', auth()->id())
			->first();

			if (!$address) {
				return response()->json([
					'success' => false,
					'message' => 'The selected address does not belong to the customer.'
				], 422);
			}
		}

		DB::beginTransaction();

		try {
			/* Collect all product supplier details in one go */
			$productDetails = [];
			foreach ($request->products as $product) {
				$fetchedDetail = productSupplierDetail($product['product_id'], $product['vendor_id']);
				if (!$fetchedDetail) {
					throw new \Exception("Product supplier not found for Product {$product['product_id']} & Vendor {$product['vendor_id']}");
				}
				$productDetails[] = [
					'product_id' => $product['product_id'],
					'vendor_id' => $product['vendor_id'],
					'quantity' => $product['quantity'],
					'unit_price' => $fetchedDetail->unit_price,
					'shipping_charge' => $request->boolean('is_customer_pickup') ? 0 : ($fetchedDetail->shipping_charge ?? 0),
				];
			}

			$totalProducts = 0;
			$cartAmount = 0;
			$cartShipping = 0;
			foreach ($productDetails as $product) {
				$totalProducts += $product['quantity'];
				$cartAmount += $product['quantity'] * $product['unit_price'];
				$cartShipping += $product['shipping_charge'];
			}

			$customer = auth()->user();
			$taxPercentage = $customer->is_tax_free ? 0 : $request->tax_percentage;

			$taxAmount = round($cartAmount * ($taxPercentage / 100), 2);

			if (in_array(config('app.website'), ['UAE', 'UAE_T'])) {
				$cartShipping = ($cartAmount + $taxAmount) < 500 ? 30 : 0;
			}

			$totalAmount = $cartAmount + $taxAmount + $cartShipping;

			/* Get the latest cart by ID (most recent) */
			$latestCart = CustomerCart::orderBy('id', 'desc')->first();

			/* Get the latest cart by ID (most recent) */
			$customerCart = CustomerCart::firstOrNew([
				'customer_id' => auth()->id()
			]);

			if (!$customerCart->exists) {
				/* New record → generate reference number */
				if ($latestCart && is_numeric($latestCart->reference_number)) {
					$referenceNumber = (int) $latestCart->reference_number + 1;
				} else {
					$website = config('app.website');
					$referenceNumber = in_array(config('app.website'), ['US', 'US_T']) ? 10001 : (in_array(config('app.website'), ['UAE', 'UAE_T']) ? 1001 : 101);
				}

				$customerCart->reference_number = $referenceNumber;
				$customerCart->created_by = 0;
			}

			/* Always update these fields */
			$customerCart->customer_address_id    = $request->customer_address_id ?? 0;
			$customerCart->shipping_charge        = $cartShipping;
			$customerCart->is_lift_gate           = $request->is_lift_gate ?? null;
			$customerCart->is_residential_address = $request->is_residential_address ?? null;
			$customerCart->is_inside_delivery     = $request->is_inside_delivery ?? null;
			$customerCart->amount                 = $cartAmount;
			$customerCart->tax_percentage         = $taxPercentage;
			$customerCart->tax_amount             = $taxAmount;
			$customerCart->total_amount           = $totalAmount;
			$customerCart->total_products         = $totalProducts;
			$customerCart->updated_by             = 0;

			$customerCart->save();

			/* Delete existing products and re-insert */
			CustomerCartProduct::where('customer_cart_id', $customerCart->id)->delete();

			foreach ($productDetails as $product) {
				$total = $product['quantity'] * $product['unit_price'];
				CustomerCartProduct::create([
					'customer_cart_id' => $customerCart->id,
					'product_id' => $product['product_id'],
					'vendor_id' => $product['vendor_id'],
					'quantity' => $product['quantity'],
					'unit_price' => $product['unit_price'],
					'amount' => $total,
					'shipping_charge' => $product['shipping_charge'],
					'total_amount' => $total + $product['shipping_charge'],
				]);
			}

			DB::commit();

			$cartProducts = [];
			foreach ($customerCart->customerCartProducts as $customerCartProduct) {
				$product = $customerCartProduct->product;
				if (!$product) continue;

				/* Decode images if stored as JSON string */
				$images = is_array($product->images) ? $product->images : (is_array($decoded = json_decode($product->images, true)) ? $decoded : null);
				$image = $images[0] ?? null;

				$supplier = optional($customerCartProduct->vendor_product_supplier)->only(['price', 'sale_price', 'shipping_charge']);

				$unitPrice = 0;
				$shippingCharge = 0;
				if ($supplier) {
					$unitPrice = ($supplier['sale_price'] > 0 && $supplier['sale_price'] < $supplier['price']) ? $supplier['sale_price'] : $supplier['price'];
					$shippingCharge = $supplier['shipping_charge'] ?? 0;
				}

				$quantity = $customerCartProduct->quantity ?? 0;
				$subTotal = $quantity * $unitPrice;

				/* Push product data */
				$cartProducts[] = [
					'name'            => $product->name,
					'image'           => $image,
					'sku'             => $product->sku,
					'currency_symbol' => $product->currency->symbol ?? null,
					'quantity'        => $quantity,
					'unit_price'      => number_format($unitPrice, 2, '.', ''),
					'sub_total'       => number_format($subTotal, 2, '.', ''),
					'shipping_charge' => number_format($shippingCharge, 2, '.', ''),
				];
			}

			/* Prepare cart summary */
			$carts = [
				'reference_number'       => $customerCart->reference_number,
				'address'                => $customerCart->customerAddress,
				'shipping_charge'        => number_format($cartShipping, 2, '.', ''),
				'amount'                 => number_format($cartAmount, 2, '.', ''),
				'tax_percentage'         => $taxPercentage,
				'tax_amount'             => number_format($taxAmount, 2, '.', ''),
				'total_amount'           => number_format($totalAmount, 2, '.', ''),
				'total_products'         => $totalProducts,
				'products'               => $cartProducts,
			];

			return response()->json([
				'success' => true,
				'message' => 'Customer cart created successfully',
				'data' => $carts,
			], 201);
		} catch (\Exception $e) {
			DB::rollBack();

			return response()->json([
				'success' => false,
				'message' => 'Failed to create customer cart: ' . $e->getMessage()
			], 500);
		}
	}

	/**
	 * @OA\Delete(
	 *     path="/api/frontend/carts",
	 *     summary="Delete a cart",
	 *     tags={"Frontend-Carts"},
	 *     @OA\Response(response=200, description="Deleted successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function destroyAll()
	{
		auth()->user()->customerCarts->each(function ($cart) {
			$cart->customerCartProducts()->delete();
			$cart->delete();
		});

		return response()->json([
			'success' => true,
			'message' => 'All carts deleted successfully for customer',
		]);
	}
}
