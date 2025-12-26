<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Traits\GeneratesQuotePdf;

use Illuminate\Support\Facades\Bus;
use Illuminate\Bus\Batch;

use App\Models\FrontEnd\Quote;
use App\Models\FrontEnd\CustomerAddress;
use App\Models\ProductSupplier;

use App\Jobs\Quote\QuotePlacedMailJob;

class QuoteController extends BaseController
{
	use GeneratesQuotePdf;

	/**
	 * @OA\Get(
	 *     path="/api/frontend/quotes",
	 *     summary="Get all quotes with pagination and filters",
	 *     tags={"FrontEnd-Quotes"},
	 *     @OA\Parameter(name="page", in="query", description="Page number for pagination", example=1, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="length", in="query", description="Number of records per page.", example=20, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="status", in="query", description="Filter by quote status.", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="global", in="query", description="Global search for all fields", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="from_date", in="query", @OA\Schema(type="string", format="date")),
	 *     @OA\Parameter(name="to_date", in="query", @OA\Schema(type="string", format="date")),
	 *     @OA\Parameter(name="sort_by", in="query", description="Column name to sort by", @OA\Schema(type="string", enum={"id", "quote_number", "quote_name", "shipping_charge", "total_amount", "total_products", "created_at", "updated_at"})),
	 *     @OA\Parameter(name="sort_dir", in="query", description="Sort direction (asc or desc)", example="asc", @OA\Schema(type="string", enum={"asc", "desc"})),
	 *     @OA\Response(response=200, description="List retrieved successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function index(Request $request)
	{
		$searchableColumns = ['id', 'quote_number', 'quote_name'];
		$sortableColumns = array_merge($searchableColumns, ['shipping_charge', 'total_amount', 'total_products', 'created_at', 'updated_at']);

		$sortBy = in_array($request->input('sort_by'), $sortableColumns) ? $request->input('sort_by') : 'id';
		$sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

		$recordsQuery = Quote::where('customer_id', auth()->id());

		/* Check if pagination requested */
		if ($request->filled('page') && $request->filled('length')) {
			/* Eager load relationships */
			$recordsQuery->with([
				'customer:id,name,email,type,country_code,mobile_number',
				'customerAddress',
				'quoteProducts:id,quote_id,product_id,vendor_id,quantity,unit_price,amount,shipping_charge,total_amount',
				'quoteProducts.product:id,name,images,sku,brand_id,currency_id,barcode',
				'quoteProducts.product.brand:id,name',
				'quoteProducts.product.currency:id,symbol',
				'quoteProducts.product.seoProductUrl:id,relational_id,relational_type,url',
				'quoteProducts.product.sellingUnitAttribute:id,product_id,attribute_value',
				'quoteProducts.product.warrantyAttribute:id,product_id,attribute_value',
				'quoteEmails',
			]);

			/* Filter by status */
			if ($request->has('status')) {
				$recordsQuery->where('quotes.status', $request->status);
			}

			if ($request->has('from_date') && $request->has('to_date')) {
				$from = $request->from_date . ' 00:00:00';
				$to = $request->to_date . ' 23:59:59';
				$recordsQuery->whereBetween('quotes.created_at', [$from, $to]);
			} elseif ($request->has('from_date')) {
				$from = $request->from_date . ' 00:00:00';
				$recordsQuery->where('quotes.created_at', '>=', $from);
			} elseif ($request->has('to_date')) {
				$to = $request->to_date . ' 23:59:59';
				$recordsQuery->where('quotes.created_at', '<=', $to);
			}

			/* Global search */
			if ($request->filled('global')) {
				$search = $request->input('global');
				$recordsQuery->where(function ($q) use ($searchableColumns, $search) {
					foreach ($searchableColumns as $col) {
						$q->orWhere("quotes.$col", 'like', '%' . $search . '%');
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
				/* Process each product in record products */
				foreach ($record->quoteProducts as $quoteProduct) {
					$product = $quoteProduct->product;
					if ($product) {
						$product->images = is_array($product->images) ? $product->images : (is_array($decoded = json_decode($product->images, true)) ? $decoded : null);
						$product->brand_name = $product->brand->name ?? null;
						$product->currency_symbol = $product->currency->symbol ?? null;
						$product->url = $product->seoProductUrl->url ?? null;
						$product->category_url = method_exists($product, 'category_url')
							? $product->category_url()
							: null;

						$product->parent_category_url = method_exists($product, 'parent_category_url')
							? $product->parent_category_url()
							: null;
						unset($product->brand, $product->currency, $product->seoProductUrl);
					}
					$quoteProduct->product_supplier = optional($quoteProduct->vendor_product_supplier)->only(['price', 'sale_price', 'shipping_charge', 'delivery_days', 'return_policy']);
					$quoteProduct->expectedShippingDate = $quoteProduct->product_supplier
					? getDateRange($record->created_at, $quoteProduct->product_supplier['delivery_days'])
					: null;

					/* Format numeric values to 2 decimal places */
					/* Format numeric values to 2 decimal places */
					foreach (['unit_price', 'amount', 'shipping_charge', 'total_amount'] as $key) {
						if (isset($quoteProduct->$key)) {
							$quoteProduct->$key = number_format($quoteProduct->$key, 2, '.', '');
						}
					}
				}

				foreach (['shipping_charge', 'amount', 'tax_amount', 'discount', 'total_amount'] as $key) {
					if (isset($record->$key)) {
						$record->$key = number_format($record->$key, 2, '.', '');
					}
				}

				return $record;
			});
		} else {
			/* No pagination: just fetch id and quote_number */
			$records = Quote::orderBy('quote_number', 'asc')->get(['id', 'quote_number']);
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
	 *     path="/api/frontend/quotes",
	 *     summary="Create a new quote",
	 *     tags={"FrontEnd-Quotes"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"quote_name", "customer_address_id", "tax_percentage", "products"},
	 *             @OA\Property(property="quote_name", type="string", example="Kitchen equipment quote"),
	 *             @OA\Property(property="customer_address_id", type="integer", example=1),
	 *             @OA\Property(property="tax_percentage", type="number", format="float", example=5),
	 *             @OA\Property(property="coupon_id", type="integer", example=1),
	 *             @OA\Property(property="discount", type="number", format="float", example=200),
	 *             @OA\Property(property="payment_terms", type="string", example="Credit Card"),
	 *             @OA\Property(property="customer_notes", type="string", example="The need for my inner purpose."),
	 *             @OA\Property(property="internal_notes", type="string", example="Please deliver between 9am-5pm."),
	 *             @OA\Property(
	 *                 property="products",
	 *                 type="array",
	 *                 @OA\Items(
	 *                     required={"product_id", "vendor_id", "quantity"},
	 *                     @OA\Property(property="product_id", type="integer", example=2001),
	 *                     @OA\Property(property="vendor_id", type="integer", example=22),
	 *                     @OA\Property(property="quantity", type="integer", example=2),
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
			'quote_name' => 'required|string',
			'customer_address_id' => 'required|integer|exists:customer_addresses,id',
			'tax_percentage' => 'required|numeric|min:0',
			'coupon_id' => 'nullable|integer',
			'discount' => 'nullable|numeric|min:0',
			'products' => 'required|array|min:1',
			'products.*.product_id' => 'required|integer|exists:ec_products,id',
			'products.*.vendor_id' => 'required|integer|exists:vendors,id',
			'products.*.quantity' => 'required|integer|min:1',
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
			$specificShipping = in_array(config('app.website'), ['US', 'US_T']) ? ($address->state === 'Texas' ? 99 : 199) : 0;

			/* Collect all product supplier details in one go */
			$productDetails = [];
			foreach ($request->products as $product) {
				$fetchedDetail = productSupplierDetail($product['product_id'], $product['vendor_id']);
				if (!$fetchedDetail) {
					throw new \Exception("Product supplier not found for Product {$product['product_id']} & Vendor {$product['vendor_id']}");
				}

				$charge = empty($fetchedDetail->shipping_charge) ? $specificShipping : $fetchedDetail->shipping_charge;
				$shipping = $request->boolean('pay_with_cheque', false) ? 0 : ($charge * $product['quantity']);

				$productDetails[] = [
					'product_id' => $product['product_id'],
					'vendor_id' => $product['vendor_id'],
					'quantity' => $product['quantity'],
					'unit_price' => $fetchedDetail->unit_price,
					'shipping_charge' => $shipping,
				];
			}

			$discount = $request->discount ?? 0;
			$totalProducts = 0;
			$quoteAmount = 0;
			$quoteShipping = 0;

			foreach ($productDetails as $product) {
				$totalProducts += $product['quantity'];
				$quoteAmount += $product['quantity'] * $product['unit_price'];
				$quoteShipping += $product['shipping_charge'];
			}

			$discountedAmount = $quoteAmount - $discount;

			$customer = auth()->user();
			$taxPercentage = $customer->is_tax_free ? 0 : $request->tax_percentage;

			if (in_array(config('app.website'), ['UAE', 'UAE_T'])) {
				$taxAmount = round($discountedAmount * ($taxPercentage / 100), 2);
				$quoteShipping = (($discountedAmount + $taxAmount) < 500) ? 30 : 0;
			} elseif (in_array(config('app.website'), ['US', 'US_T'])) {
				$taxableAmount = $discountedAmount + $quoteShipping;
				$taxAmount = round($taxableAmount * ($taxPercentage / 100), 2);
			} else {
				$taxAmount = round($discountedAmount * ($taxPercentage / 100), 2);
			}
			$totalAmount = $discountedAmount + $taxAmount + $quoteShipping;

			/* Generate new quote number */
			$latestQuote = Quote::whereRaw("quote_number REGEXP '^QT[0-9]+$'")
				->orderBy('id', 'desc')
				->first();

			if ($latestQuote && preg_match('/^QT(\d+)$/', $latestQuote->quote_number, $matches)) {
				$nextNumber = (int) $matches[1] + 1;
				$quoteNumber = 'QT' . $nextNumber;
			} else {
				$quoteNumber = 'QT1001';
			}

			$quote = Quote::create([
				'quote_number' => $quoteNumber,
				'quote_name' => $request->quote_name,
				'customer_id' => $customerId,
				'customer_address_id' => $request->customer_address_id,
				'shipping_charge' => $quoteShipping,
				'amount' => $quoteAmount,
				'tax_percentage' => $taxPercentage,
				'tax_amount' => $taxAmount,
				'coupon_id' => $request->coupon_id ?? null,
				'tax_amount' => $taxAmount,
				'total_amount' => $totalAmount,
				'total_products' => $totalProducts,
				'payment_terms' => $request->payment_terms,
				'customer_notes' => $request->customer_notes,
				'internal_notes' => $request->internal_notes,
				'status' => 'Pending',
				'expired_at' => now()->addDays(7),
				'created_by' => 0,
			]);

			foreach ($productDetails as $product) {
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

			DB::commit();

			$batch = Bus::batch([])->name('Quote Mails')->dispatch();

			$batch->options['queue'] = config('app.website') . '_QOT_PLC';
			$batch->add(new QuotePlacedMailJob([
				'recordId' => $quote->id
			]));

			$quote->load([
				'customer:id,name,email,type,country_code,mobile_number',
				'customerAddress',
				'quoteProducts:id,quote_id,product_id,vendor_id,quantity,unit_price,amount,shipping_charge,total_amount',
				'quoteProducts.product:id,name,images,sku,brand_id,currency_id,barcode',
				'quoteProducts.product.brand:id,name',
				'quoteProducts.product.currency:id,symbol',
				'quoteProducts.product.seoProductUrl:id,relational_id,relational_type,url',
				'quoteProducts.product.sellingUnitAttribute:id,product_id,attribute_value',
				'quoteProducts.product.warrantyAttribute:id,product_id,attribute_value',
				'quoteEmails',
			]);

			foreach ($quote->quoteProducts as $quoteProduct) {
				$product = $quoteProduct->product;
				if ($product) {
					$product->images = is_array($product->images) ? $product->images : (is_array($decoded = json_decode($product->images, true)) ? $decoded : null);
					$product->brand_name = $product->brand->name ?? null;
					$product->currency_symbol = $product->currency->symbol ?? null;
					$product->url = $product->seoProductUrl->url ?? null;
					$product->category_url = method_exists($product, 'category_url') ? $product->category_url() : null;
					$product->parent_category_url = method_exists($product, 'parent_category_url') ? $product->parent_category_url() : null;
					unset($product->brand, $product->currency, $product->seoProductUrl);
				}

				$quoteProduct->product_supplier = optional($quoteProduct->vendor_product_supplier)->only([
					'price', 'sale_price', 'shipping_charge', 'delivery_days', 'return_policy'
				]);

				/* Format numeric values to 2 decimal places */
				foreach (['unit_price', 'amount', 'shipping_charge', 'total_amount'] as $key) {
					if (isset($quoteProduct->$key)) {
						$quoteProduct->$key = number_format($quoteProduct->$key, 2, '.', '');
					}
				}
			}

			foreach (['shipping_charge', 'amount', 'tax_amount', 'discount', 'total_amount'] as $key) {
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
			'quoteProducts.product.seoProductUrl:id,relational_id,relational_type,url',
			'quoteProducts.product.sellingUnitAttribute:id,product_id,attribute_value',
			'quoteProducts.product.warrantyAttribute:id,product_id,attribute_value',
			'quoteEmails',
		]);

		/* Mutate the data for each quote product */
		foreach ($quote->quoteProducts as $quoteProduct) {
			$product = $quoteProduct->product;
			if ($product) {
				$product->images = is_array($product->images) ? $product->images : (is_array($decoded = json_decode($product->images, true)) ? $decoded : null);
				$product->brand_name = $product->brand->name ?? null;
				$product->currency_symbol = $product->currency->symbol ?? null;
				$product->url = $product->seoProductUrl->url ?? null;
				$product->category_url = method_exists($product, 'category_url') ? $product->category_url() : null;
				$product->parent_category_url = method_exists($product, 'parent_category_url') ? $product->parent_category_url() : null;
				unset($product->brand, $product->currency, $product->seoProductUrl);
			}
			$quoteProduct->product_supplier = optional($quoteProduct->vendor_product_supplier)->only(['price', 'sale_price', 'shipping_charge', 'delivery_days', 'return_policy']);
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

		foreach (['shipping_charge', 'amount', 'tax_amount', 'discount', 'total_amount'] as $key) {
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
	 * @OA\Put(
	 *     path="/api/frontend/quotes/{id}",
	 *     summary="Update an existing quote",
	 *     tags={"FrontEnd-Quotes"},
	 *     @OA\Parameter(name="id", in="path", required=true, description="Quote ID", @OA\Schema(type="integer")),
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"quote_name", "customer_address_id", "tax_percentage", "products"},
	 *             @OA\Property(property="quote_name", type="string", example="Kitchen equipment quote"),
	 *             @OA\Property(property="customer_address_id", type="integer", example=1),
	 *             @OA\Property(property="tax_percentage", type="number", format="float", example=5),
	 *             @OA\Property(property="coupon_id", type="integer", example=1),
	 *             @OA\Property(property="discount", type="number", format="float", example=200),
	 *             @OA\Property(property="payment_terms", type="string", example="Credit Card"),
	 *             @OA\Property(property="customer_notes", type="string", example="The need for my inner purpose."),
	 *             @OA\Property(property="internal_notes", type="string", example="Please deliver between 9am-5pm."),
	 *             @OA\Property(
	 *                 property="products",
	 *                 type="array",
	 *                 @OA\Items(
	 *                     required={"product_id", "vendor_id", "quantity"},
	 *                     @OA\Property(property="product_id", type="integer", example=2001),
	 *                     @OA\Property(property="vendor_id", type="integer", example=22),
	 *                     @OA\Property(property="quantity", type="integer", example=2),
	 *                 )
	 *             ),
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Updated successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function update(Request $request, $id)
	{
		$quote = Quote::where('customer_id', auth()->id())->where('id', $id)->first();
		if (!$quote) {
			return response()->json([
				'success' => false,
				'message' => "Quote not found."
			]);
		}

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

		$request->validate([
			'quote_name' => 'required|string',
			'customer_address_id' => 'required|integer|exists:customer_addresses,id',
			'tax_percentage' => 'required|numeric|min:0',
			'coupon_id' => 'nullable|integer',
			'discount' => 'nullable|numeric|min:0',
			'products' => 'required|array|min:1',
			'products.*.product_id' => 'required|integer|exists:ec_products,id',
			'products.*.vendor_id' => 'required|integer|exists:vendors,id',
			'products.*.quantity' => 'required|integer|min:1',
		]);

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

				$charge = empty($fetchedDetail->shipping_charge) ? $specificShipping : $fetchedDetail->shipping_charge;
				$shipping = $request->boolean('pay_with_cheque', false) ? 0 : ($charge * $product['quantity']);

				$productDetails[] = [
					'product_id' => $product['product_id'],
					'vendor_id' => $product['vendor_id'],
					'quantity' => $product['quantity'],
					'unit_price' => $fetchedDetail->unit_price,
					'shipping_charge' => $shipping,
				];
			}

			$discount = $request->discount ?? 0;
			$totalProducts = 0;
			$quoteAmount = 0;
			$quoteShipping = 0;

			foreach ($productDetails as $product) {
				$totalProducts += $product['quantity'];
				$quoteAmount += $product['quantity'] * $product['unit_price'];
				$quoteShipping += $product['shipping_charge'];
			}
			$discountedAmount = $quoteAmount - $discount;

			$customer = auth()->user();
			$taxPercentage = $customer->is_tax_free ? 0 : $request->tax_percentage;

			if (in_array(config('app.website'), ['UAE', 'UAE_T'])) {
				$taxAmount = round($discountedAmount * ($taxPercentage / 100), 2);
				$quoteShipping = (($discountedAmount + $taxAmount) < 500) ? 30 : 0;
			} elseif (in_array(config('app.website'), ['US', 'US_T'])) {
				$taxableAmount = $discountedAmount + $quoteShipping;
				$taxAmount = round($taxableAmount * ($taxPercentage / 100), 2);
			} else {
				$taxAmount = round($discountedAmount * ($taxPercentage / 100), 2);
			}
			$totalAmount = $discountedAmount + $taxAmount + $quoteShipping;

			$quote->update([
				'quote_name' => $request->quote_name,
				'customer_address_id' => $request->customer_address_id,
				'shipping_charge' => $quoteShipping,
				'amount' => $quoteAmount,
				'tax_percentage' => $taxPercentage,
				'tax_amount' => $taxAmount,
				'coupon_id' => $request->coupon_id ?? null,
				'discount' => $discount,
				'total_amount' => $totalAmount,
				'total_products' => $totalProducts,
				'payment_terms' => $request->payment_terms,
				'customer_notes' => $request->customer_notes,
				'internal_notes' => $request->internal_notes,
			]);

			/* Remove old quote products */
			$quote->quoteProducts()->delete();

			/* Insert updated products */
			foreach ($productDetails as $product) {
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

			DB::commit();

			$batch = Bus::batch([])->name('Quote Mails')->dispatch();

			$batch->options['queue'] = config('app.website') . '_QOT_PLC';
			$batch->add(new QuotePlacedMailJob([
				'recordId' => $quote->id
			]));

			$quote->refresh()->load([
				'customer:id,name,email,type,country_code,mobile_number',
				'customerAddress',
				'quoteProducts:id,quote_id,product_id,vendor_id,quantity,unit_price,amount,shipping_charge,total_amount',
				'quoteProducts.product:id,name,images,sku,brand_id,currency_id,barcode',
				'quoteProducts.product.brand:id,name',
				'quoteProducts.product.currency:id,symbol',
				'quoteProducts.product.seoProductUrl:id,relational_id,relational_type,url',
				'quoteProducts.product.sellingUnitAttribute:id,product_id,attribute_value',
				'quoteProducts.product.warrantyAttribute:id,product_id,attribute_value',
				'quoteEmails',
			]);

			foreach ($quote->quoteProducts as $quoteProduct) {
				$product = $quoteProduct->product;

				if ($product) {
					$product->images = is_array($product->images) ? $product->images : (is_array($decoded = json_decode($product->images, true)) ? $decoded : null);
					$product->brand_name = $product->brand->name ?? null;
					$product->currency_symbol = $product->currency->symbol ?? null;
					$product->url = $product->seoProductUrl->url ?? null;
					$product->category_url = method_exists($product, 'category_url') ? $product->category_url() : null;
					$product->parent_category_url = method_exists($product, 'parent_category_url') ? $product->parent_category_url() : null;
					unset($product->brand, $product->currency, $product->seoProductUrl);
				}

				$quoteProduct->product_supplier = optional($quoteProduct->vendor_product_supplier)->only([
					'price', 'sale_price', 'shipping_charge', 'delivery_days', 'return_policy'
				]);

				$quoteProduct->expectedShippingDate = $quoteProduct->product_supplier
				? getDateRange($quote->created_at, $quoteProduct->product_supplier['delivery_days'])
				: null;

				/* Format monetary values */
				foreach (['unit_price', 'amount', 'shipping_charge', 'total_amount'] as $key) {
					if (isset($quoteProduct->$key)) {
						$quoteProduct->$key = number_format($quoteProduct->$key, 2, '.', '');
					}
				}
			}

			foreach (['shipping_charge', 'amount', 'tax_amount', 'discount', 'total_amount'] as $key) {
				if (isset($quote->$key)) {
					$quote->$key = number_format($quote->$key, 2, '.', '');
				}
			}

			return response()->json([
				'success' => true,
				'message' => 'Quote updated successfully',
				'data' => $quote
			]);

		} catch (\Exception $e) {
			DB::rollBack();

			return response()->json([
				'success' => false,
				'message' => 'Failed to update quote: ' . $e->getMessage()
			]);
		}
	}

	/**
	 * @OA\Get(
	 *     path="/api/frontend/quotes/{id}/download-pdf",
	 *     summary="Download quote's pdf",
	 *     tags={"FrontEnd-Quotes"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         description="Quote ID",
	 *         required=true,
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\Response(response=200, description="PDF downloaded successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function downloadPdf($id)
	{
		$quote = Quote::where('customer_id', auth()->id())->where('id', $id)->first();
		if (!$quote) {
			return response()->json([
				'success' => false,
				'message' => "Quote not found."
			]);
		}

		$pdfParams = $this->generateQuotePdfParams($id);
		$pdf = Pdf::loadView('pdf.quote', $pdfParams);
		return $pdf->download("quote_{$quote->id}.pdf");
	}

	/**
	 * @OA\Get(
	 *     path="/api/frontend/quotes/{id}/email-pdf",
	 *     summary="emal quote's pdf",
	 *     tags={"FrontEnd-Quotes"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         description="Quote ID",
	 *         required=true,
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\Response(response=200, description="Quote email sent successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function emailPdf($id)
	{
		$quote = Quote::where('customer_id', auth()->id())->where('id', $id)->first();
		if (!$quote) {
			return response()->json([
				'success' => false,
				'message' => "Quote not found."
			]);
		}

		$batch = Bus::batch([])->name('Quote Mails')->dispatch();

		$batch->options['queue'] = config('app.website') . '_QOT_PLC';
		$batch->add(new QuotePlacedMailJob([
			'recordId' => $quote->id,
			'sendToCc' => true
		]));

		return response()->json([
			'success' => true,
			'message' => 'Quote email sent successfully with the attached PDF'
		]);
	}

	/**
	 * Remove the specified resource from storage.
	 */
	public function destroy(Quote $quote)
	{
		//
	}
}
