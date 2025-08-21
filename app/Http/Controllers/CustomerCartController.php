<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

use Illuminate\Support\Facades\Bus;
use Illuminate\Bus\Batch;

use App\Models\FrontEnd\CustomerCart;
use App\Models\FrontEnd\CustomerCartProduct;
use App\Models\FrontEnd\CustomerAddress;
use App\Jobs\Order\CartCreationMailJob;

class CustomerCartController extends Controller
{
	/**
	 * Display a listing of the resource.
	 */
	public function index()
	{
		//
	}

	/**
	 * @OA\Post(
	 *     path="/api/carts",
	 *     summary="Create a new cart",
	 *     tags={"Carts"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"customer_id", "customer_address_id", "products"},
	 *             @OA\Property(property="customer_id", type="integer", example=1),
	 *             @OA\Property(property="customer_address_id", type="integer", example="1"),
	 *             @OA\Property(property="is_lift_gate", type="boolean", example=true),
	 *             @OA\Property(property="is_residential_address", type="boolean", example=true),
	 *             @OA\Property(property="tax_percentage", type="number", example=5),
	 *             @OA\Property(
	 *                 property="products",
	 *                 type="array",
	 *                 @OA\Items(
	 *                     required={"product_id", "vendor_id", "quantity", "unit_price", "shipping_charge"},
	 *                     @OA\Property(property="product_id", type="integer", example=101),
	 *                     @OA\Property(property="vendor_id", type="integer", example=22),
	 *                     @OA\Property(property="quantity", type="integer", example=5),
	 *                     @OA\Property(property="unit_price", type="number", format="float", example=199.99),
	 *                     @OA\Property(property="shipping_charge", type="number", format="float", example=50.00)
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
			'customer_id' => 'required|integer|exists:customers,id',
			'customer_address_id' => 'required|integer|exists:customer_addresses,id',
			'is_lift_gate' => 'nullable|boolean',
			'is_residential_address' => 'nullable|boolean',
			'tax_percentage' => 'required|numeric|min:0',
			'products' => 'required|array|min:1',
			'products.*.product_id' => 'required|integer|exists:ec_products,id',
			'products.*.vendor_id' => 'required|integer|exists:vendors,id',
			'products.*.quantity' => 'required|integer|min:1',
			'products.*.unit_price' => 'required|numeric|min:0',
			'products.*.shipping_charge' => 'required|numeric|min:0',
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

		DB::beginTransaction();

		try {
			$totalProducts = 0;
			$cartAmount = 0;
			$cartShipping = 0;

			foreach ($request->products as $product) {
				$totalProducts += $product['quantity'];
				$cartAmount += $product['quantity'] * $product['unit_price'];
				$cartShipping += $product['shipping_charge'];
			}

			$cartAmount += $request->boolean('is_lift_gate') ? 75 : 0;
			$cartAmount += $request->boolean('is_residential_address') ? 199 : 0;

			$taxAmount = round($cartAmount * ($request->tax_percentage / 100), 2);
			$totalAmount = $cartAmount + $taxAmount + $cartShipping;

			/* Get the latest cart by ID (most recent) */
			$latestCart = CustomerCart::orderBy('id', 'desc')->first();

			// Generate the next cart reference number
			if ($latestCart && is_numeric($latestCart->reference_number)) {
				$referenceNumber = (int) $latestCart->reference_number + 1;
			} else {
				$website = config('app.website');
				$referenceNumber = $website === 'US' ? 10001 : ($website === 'UAE' ? 1001 : 101);
			}

			$customerCart = CustomerCart::create([
				'reference_number' => $referenceNumber,
				'customer_id' => $request->customer_id,
				'customer_address_id' => $request->customer_address_id,
				'shipping_charge' => $cartShipping,
				'is_lift_gate' => $request->is_lift_gate,
				'is_residential_address' => $request->is_residential_address,
				'amount' => $cartAmount,
				'tax_percentage' => $request->tax_percentage,
				'tax_amount' => $taxAmount,
				'total_amount' => $totalAmount,
				'total_products' => $totalProducts,
				'created_by' => auth()->id(),
			]);

			foreach ($request->products as $product) {
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

			$randomPassword = Str::random(8);
			$hashedPassword = Hash::make($randomPassword);
			$customerCart->customer->update(['password' => $hashedPassword]);

			DB::commit();

			$batch = Bus::batch([])->before(function (Batch $batch) {
			})->catch(function (Batch $batch, Throwable $e) {
			})->finally(function (Batch $batch) {
			})->name('Cart Creation')->dispatch();

			$batch->options['queue'] = config('app.website') . '_CART_ADD';
			$batch->add(new CartCreationMailJob([
				'recordId' => $customerCart->id,
				'randomPassword' => $randomPassword,
			]));

			/* Load relationships */
			$customerCart->load([
				'customerCartProducts:id,customer_cart_id,product_id,vendor_id,quantity,unit_price,amount,shipping_charge,total_amount',
				'customerCartProducts.product:id,name,images,sku,brand_id,currency_id,barcode',
				'customerCartProducts.product.brand:id,name',
				'customerCartProducts.product.currency:id,symbol',
			]);

			/* Mutate the data for each customer cart product */
			foreach ($customerCart->customerCartProducts as $customerCartProduct) {
				$product = $customerCartProduct->product;
				if ($product) {
					$product->images = is_array($product->images)
						? $product->images
						: (is_array($decoded = json_decode($product->images, true)) ? $decoded : null);
					$product->brand_name = $product->brand->name ?? null;
					$product->currency_symbol = $product->currency->symbol ?? null;
					unset($product->brand, $product->currency);
				}

				$customerCartProduct->product_supplier = optional($customerCartProduct->vendor_product_supplier)
					->only(['price', 'sale_price', 'delivery_days', 'return_policy']);
				$customerCartProduct->expectedShippingDate = $customerCartProduct->product_supplier
					? getDateRange($customerCart->created_at, $customerCartProduct->product_supplier['delivery_days'])
					: null;

				// Format numeric values to 2 decimal places - FIXED variable name
				foreach (['unit_price', 'amount', 'shipping_charge', 'total_amount'] as $key) {
					if (isset($customerCartProduct->$key)) {
						$customerCartProduct->$key = number_format($customerCartProduct->$key, 2, '.', '');
					}
				}
			}

			foreach (['shipping_charge', 'amount', 'tax_amount', 'total_amount', 'paid_amount', 'pending_amount'] as $key) {
				if (isset($customerCart->$key)) {
					$customerCart->$key = number_format($customerCart->$key, 2, '.', '');
				}
			}

			return response()->json([
				'success' => true,
				'message' => 'Customer cart created successfully',
				'data' => $customerCart,
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
	 * Display the specified resource.
	 */
	public function show(CustomerCart $customerCart)
	{
		//
	}

	/**
	 * Update the specified resource in storage.
	 */
	public function update(Request $request, CustomerCart $customerCart)
	{
		//
	}

	/**
	 * Remove the specified resource from storage.
	 */
	public function destroy(CustomerCart $customerCart)
	{
		//
	}
}
