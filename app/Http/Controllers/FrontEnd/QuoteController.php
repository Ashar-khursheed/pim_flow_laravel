<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\FrontEnd\Quote;
use App\Models\FrontEnd\CustomerAddress;

class QuoteController extends BaseController
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
	 *     path="/api/frontend/quotes",
	 *     summary="Create a new quote",
	 *     tags={"FrontEnd-Quotes"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"quote_number", "quote_name", "customer_address_id", "tax_percentage", "products"},
	 *             @OA\Property(property="quote_number", type="string", example="1111"),
	 *             @OA\Property(property="quote_name", type="string", example="Kitchen equipment quote"),
	 *             @OA\Property(property="customer_address_id", type="integer", example=1),
	 *             @OA\Property(property="tax_percentage", type="number", format="float", example=5),
	 *             @OA\Property(property="payment_terms", type="string", example="Credit Card"),
	 *             @OA\Property(property="customer_notes", type="string", example="The need for my inner purpose."),
	 *             @OA\Property(property="internal_notes", type="string", example="Please deliver between 9am-5pm."),
	 *             @OA\Property(property="send_customer_email", type="boolean", example=true),
	 *             @OA\Property(
	 *                 property="products",
	 *                 type="array",
	 *                 @OA\Items(
	 *                     required={"product_id", "vendor_id", "quantity", "unit_price", "shipping_charge"},
	 *                     @OA\Property(property="product_id", type="integer", example=2001),
	 *                     @OA\Property(property="vendor_id", type="integer", example=22),
	 *                     @OA\Property(property="quantity", type="integer", example=2),
	 *                     @OA\Property(property="unit_price", type="number", format="float", example=4000),
	 *                     @OA\Property(property="shipping_charge", type="number", format="float", example=50.00)
	 *                 )
	 *             ),
	 *             @OA\Property(
	 *                 property="emails",
	 *                 type="array",
	 *                 @OA\Items(type="string", format="email", example="john@example.com")
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
			'quote_number' => 'required|string|unique:quotes,quote_number',
			'quote_name' => 'required|string',
			'customer_address_id' => 'required|integer|exists:customer_addresses,id',
			'tax_percentage' => 'required|numeric|min:0',
			'products' => 'required|array|min:1',
			'products.*.product_id' => 'required|integer|exists:ec_products,id',
			'products.*.vendor_id' => 'required|integer|exists:vendors,id',
			'products.*.quantity' => 'required|integer|min:1',
			'products.*.unit_price' => 'required|numeric|min:0',
			'products.*.shipping_charge' => 'required|numeric|min:0',
			'emails' => 'array',
			'emails.*' => 'email',
		]);

		$customerId = auth()->id();

		$address = CustomerAddress::where('id', $request->customer_address_id)
		->where('customer_id', $customerId)
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
			$quoteAmount = 0;
			$quoteShipping = 0;

			foreach ($request->products as $product) {
				$totalProducts += $product['quantity'];
				$quoteAmount += $product['quantity'] * $product['unit_price'];
				$quoteShipping += $product['shipping_charge'];
			}

			$taxAmount = round($quoteAmount * ($request->tax_percentage / 100), 2);
			$totalAmount = $quoteAmount + $taxAmount + $quoteShipping;

			$quote = Quote::create([
				'quote_number' => $request->quote_number,
				'quote_name' => $request->quote_name,
				'customer_id' => $customerId,
				'customer_address_id' => $request->customer_address_id,
				'shipping_charge' => $quoteShipping,
				'amount' => $quoteAmount,
				'tax_percentage' => $request->tax_percentage,
				'tax_amount' => $taxAmount,
				'total_amount' => $totalAmount,
				'total_products' => $totalProducts,
				'payment_terms' => $request->payment_terms,
				'customer_notes' => $request->customer_notes,
				'internal_notes' => $request->internal_notes,
				'status' => 'Pending',
				'expiration_days' => 7,
				'created_by' => 0,
			]);

			foreach ($request->products as $product) {
				$total = $product['quantity'] * $product['unit_price'];
				$quote->quoteProducts()->create([
					'product_id' => $product['product_id'],
					'vendor_id' => $product['vendor_id'],
					'quantity' => $product['quantity'],
					'unit_price' => $product['unit_price'],
					'amount' => $total,
					'shipping_charge' => $product['shipping_charge'],
					'total_amount' => $total + $product['shipping_charge'],
				]);
			}

			foreach ($request->emails as $email) {
				$quote->quoteEmails()->create([
					'email' => $email,
				]);
			}

			foreach ($quote->quoteEmails as $quoteEmail) {
				// $quoteEmail->notify(new QuotePlacedMail($quote));
			}

			DB::commit();

			$quote->load([
				'quoteProducts:id,quote_id,product_id,vendor_id,quantity,unit_price,amount,shipping_charge,total_amount',
				'quoteProducts.product:id,name,images,sku,brand_id,currency_id,barcode',
				'quoteProducts.product.brand:id,name',
				'quoteProducts.product.currency:id,symbol',
				'quoteEmails'
			]);

			foreach ($quote->quoteProducts as $quoteProduct) {
				$product = $quoteProduct->product;
				if ($product) {
					$product->images = is_array($product->images)
					? $product->images
					: (is_array($decoded = json_decode($product->images, true)) ? $decoded : null);

					$product->brand_name = $product->brand->name ?? null;
					$product->currency_symbol = $product->currency->symbol ?? null;

					unset($product->brand, $product->currency);
				}

				$quoteProduct->product_supplier = optional($quoteProduct->vendor_product_supplier)->only(['price', 'sale_price']);

				/* Format numeric values to 2 decimal places */
				foreach (['unit_price', 'amount', 'shipping_charge', 'total_amount'] as $key) {
					if (isset($quoteProduct->$key)) {
						$quoteProduct->$key = number_format($quoteProduct->$key, 2, '.', '');
					}
				}
			}

			foreach (['shipping_charge', 'amount', 'tax_amount', 'total_amount'] as $key) {
				if (isset($quote->$key)) {
					$quote->$key = number_format($quote->$key, 2, '.', '');
				}
			}

			return response()->json([
				'success' => true,
				'message' => 'Quote created successfully',
				'data' => $quote
			], 201);

		} catch (\Exception $e) {
			DB::rollBack();

			return response()->json([
				'success' => false,
				'message' => 'Failed to create quote: ' . $e->getMessage()
			], 500);
		}
	}

	/**
	 * @OA\Get(
	 *     path="/api/frontend/quotes/{id}",
	 *     summary="Get quote details",
	 *     tags={"FrontEnd-Quotes"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         description="Quote ID",
	 *         required=true,
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\Response(response=200, description="Details retrieved successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function show($id)
	{
		$quote = Quote::where('customer_id', auth()->id())->where('id', $id)->first();
		if (!$quote) {
			return response()->json([
				'success' => false,
				'message' => "Quote not found."
			]);
		}

		/* Load relationships */
		$quote->load([
			'customer:id,name,email,type,country_code,mobile_number',
			'customerAddress',
			'quoteProducts:id,quote_id,product_id,vendor_id,quantity,unit_price,amount,shipping_charge,total_amount',
			'quoteProducts.product:id,name,images,sku,brand_id,currency_id,barcode',
			'quoteProducts.product.brand:id,name',
			'quoteProducts.product.currency:id,symbol',
			'quoteProducts.product.sellingUnitAttribute:id,product_id,attribute_value',
			'quoteEmails',
		]);

		/* Mutate the data for each quote product */
		foreach ($quote->quoteProducts as $quoteProduct) {
			$product = $quoteProduct->product;
			if ($product) {
				$product->images = is_array($product->images) ? $product->images : (is_array($decoded = json_decode($product->images, true)) ? $decoded : null);
				$product->brand_name = $product->brand->name ?? null;
				$product->currency_symbol = $product->currency->symbol ?? null;
				unset($product->brand, $product->currency);
			}
			$quoteProduct->product_supplier = optional($quoteProduct->vendor_product_supplier)->only(['price', 'sale_price', 'delivery_days']);
			$quoteProduct->expectedShippingDate = $quoteProduct->product_supplier
			? getDateRange($quote->created_at, $quoteProduct->product_supplier['delivery_days'])
			: null;

			/* Format numeric values to 2 decimal places */
			foreach (['unit_price', 'amount', 'shipping_charge', 'total_amount'] as $key) {
				if (isset($quoteProduct->$key)) {
					$quoteProduct->$key = number_format($quoteProduct->$key, 2, '.', '');
				}
			}
		}

		foreach (['shipping_charge', 'amount', 'tax_amount', 'total_amount'] as $key) {
			if (isset($quote->$key)) {
				$quote->$key = number_format($quote->$key, 2, '.', '');
			}
		}

		return response()->json([
			'success' => true,
			'data' => $quote
		]);
	}

	/**
	 * Update the specified resource in storage.
	 */
	public function update(Request $request, Quote $quote)
	{
		//
	}

	/**
	 * Remove the specified resource from storage.
	 */
	public function destroy(Quote $quote)
	{
		//
	}
}
