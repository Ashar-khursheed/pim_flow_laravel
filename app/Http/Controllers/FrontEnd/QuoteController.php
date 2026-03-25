<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;

use Illuminate\Support\Facades\Bus;
use Illuminate\Bus\Batch;

use App\Models\FrontEnd\Quote;
use App\Models\FrontEnd\QuoteProduct;
use App\Models\FrontEnd\CustomerAddress;
use App\Models\FrontEnd\AccessoryCharge;
use App\Models\ProductSupplier;

use App\Jobs\Quote\QuotePlacedMailJob;

use App\Traits\CalculationTrait;
use App\Traits\GeneratesQuotePdf;
use App\Helpers\CurrencyConverter;

class QuoteController extends BaseController
{
	use CalculationTrait;
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

		/* No pagination — return only id and quote_number */
		if (!$request->filled('page') || !$request->filled('length')) {
			$records = $recordsQuery->orderBy('quote_number', 'asc')->get(['id', 'quote_number']);

			return response()->json([
				'success' => true,
				'message' => __('msg_rec_list'),
				'data' => $records,
				'total_pages' => 1,
				'total_records' => $records->count(),
			]);
		}

		/* Eager load relationships */
		$recordsQuery->with([
			'customer:id,name,email,type,country_code,mobile_number',
			'customerAddress:id,address,city,country',
			'customerAddress.relatedCountry:id,name,currency_id',
			'customerAddress.relatedCountry.currency:id,title,symbol',
			'quoteProducts:id,quote_id,product_id,vendor_id,quantity,unit_price,amount,shipping_charge,total_amount',
			'quoteProducts.product:id,name,images,sku,brand_id,barcode',
			'quoteProducts.product.brand:id,name',
			'quoteProducts.product.seoProductUrl:id,relational_id,relational_type,url',
			'quoteProducts.product.sellingUnitAttribute:id,product_id,attribute_value',
			'quoteProducts.product.warrantyAttribute:id,product_id,attribute_value',
			'quoteEmails',
		]);

		/* Status filter */
		if ($request->filled('status')) {
			$recordsQuery->where('quotes.status', $request->status);
		}

		/* Date range filter */
		if ($request->filled('from_date') && $request->filled('to_date')) {
			$recordsQuery->whereBetween('quotes.created_at', [
				$request->from_date . ' 00:00:00',
				$request->to_date . ' 23:59:59',
			]);
		} elseif ($request->filled('from_date')) {
			$recordsQuery->where('quotes.created_at', '>=', $request->from_date . ' 00:00:00');
		} elseif ($request->filled('to_date')) {
			$recordsQuery->where('quotes.created_at', '<=', $request->to_date . ' 23:59:59');
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

		/* Count before pagination */
		$totalRecords = (clone $recordsQuery)->count();

		/* Pagination calculation */
		$length = (int) $request->input('length');
		$page = (int) $request->input('page');
		$totalPages = (int) ceil($totalRecords / $length);
		$page = ($page > $totalPages && $totalPages > 0) ? 1 : $page;

		/* Fetch paginated records */
		$records = $recordsQuery
		->offset(($page - 1) * $length)
		->limit($length)
		->get();

		/* Batch-fetch vendor product suppliers — not a relation, so with() cannot be used */
		$allQuoteProducts = $records->flatMap(fn($quote) => $quote->quoteProducts);

		if ($allQuoteProducts->isNotEmpty()) {
			$vendorSuppliers = ProductSupplier::where(function ($query) use ($allQuoteProducts) {
				foreach ($allQuoteProducts as $quoteProduct) {
					$query->orWhere(function ($q) use ($quoteProduct) {
						$q->where('product_id', $quoteProduct->product_id)
						->where('vendor_id', $quoteProduct->vendor_id);
					});
				}
			})
			->select('id', 'product_id', 'vendor_id', 'price', 'sale_price', 'shipping_charge', 'delivery_days', 'return_policy')
			->get()
			->keyBy(fn($item) => $item->product_id . '_' . $item->vendor_id);

			/* Attach supplier as dynamic attribute on each quote product */
			foreach ($allQuoteProducts as $quoteProduct) {
				$key = $quoteProduct->product_id . '_' . $quoteProduct->vendor_id;
				$quoteProduct->vendor_product_supplier = $vendorSuppliers->get($key);
			}
		}

		/* Transform records */
		$records->transform(function ($record) {
			foreach ($record->quoteProducts as $quoteProduct) {
				/* Decode product images and flatten relations */
				$product = $quoteProduct->product;
				if ($product) {
					$product->images = is_array($product->images) ? $product->images : (is_array($decoded = json_decode($product->images, true)) ? $decoded : null);
					$product->brand_name = $product->brand->name ?? null;
					$product->url = $product->seoProductUrl->url ?? null;
					$product->category_url = method_exists($product, 'category_url') ? $product->category_url() : null;
					$product->parent_category_url = method_exists($product, 'parent_category_url') ? $product->parent_category_url() : null;
					unset($product->brand, $product->seoProductUrl, $product->categories);
				}

				/* Attach supplier data from pre-fetched dynamic attribute */
				$supplier = $quoteProduct->vendor_product_supplier;
				$quoteProduct->product_supplier = $supplier ? $supplier->only(['price', 'sale_price', 'shipping_charge', 'delivery_days', 'return_policy']) : null;
				unset($quoteProduct->vendor_product_supplier);

				/* Expected shipping date based on supplier delivery days */
				$quoteProduct->expectedShippingDate = $quoteProduct->product_supplier ? getDateRange($record->created_at, $quoteProduct->product_supplier['delivery_days']) : null;

				/* Convert quote product amount fields */
				foreach (['unit_price', 'amount', 'shipping_charge', 'total_amount'] as $key) {
					if (isset($quoteProduct->$key)) {
						$quoteProduct->$key = number_format($quoteProduct->$key, 2, '.', '');
					}
				}
			}

			/* Convert quote-level amount fields */
			foreach (['shipping_charge', 'amount', 'tax_amount', 'discount', 'total_amount'] as $key) {
				if (isset($record->$key)) {
					$record->$key = number_format($record->$key, 2, '.', '');
				}
			}

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
	 *     path="/api/frontend/quotes",
	 *     summary="Create a new quote",
	 *     tags={"FrontEnd-Quotes"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"quote_name", "customer_address_id", "tax_percentage", "products"},
	 *             @OA\Property(property="quote_name", type="string", example="Kitchen equipment quote"),
	 *             @OA\Property(property="customer_address_id", type="integer", example=1),
	 *             @OA\Property(property="is_lift_gate", type="boolean", example=true, description="Lift gate required"),
	 *             @OA\Property(property="is_residential_address", type="boolean", example=true, description="Residential address"),
	 *             @OA\Property(property="is_inside_delivery", type="boolean", example=true, description="Inside delivery required"),
	 *             @OA\Property(property="tax_percentage", type="number", format="float", example=5),
	 *
	 *             @OA\Property(property="additional_amount_name", type="string", example="Accessory 1", description="Additional amount name"),
	 *             @OA\Property(property="additional_amount_price", type="number", format="float", example=100, description="Additional amount price"),
	 *
	 *             @OA\Property(property="coupon_id", type="integer", example=1),
	 *             @OA\Property(property="discount", type="number", format="float", example=200),
	 *
	 *             @OA\Property(property="additional_discount_option", type="boolean", example=true, description="Additional Discount Option"),
	 *             @OA\Property(property="additional_discount_reason", type="string", example="Bulk order discount", description="Reason for additional discount"),
	 *             @OA\Property(property="additional_discount_type", type="string", enum={"fixed", "percentage"}, example="percentage"),
	 *             @OA\Property(property="additional_discount_percentage", type="number", format="float", example=10.50, description="Additional discount percentage"),
	 *             @OA\Property(property="additional_discount_amount", type="number", format="float", example=50.00, description="Additional discount amount"),
	 *
	 *             @OA\Property(property="payment_terms", type="string", example="Credit Card"),
	 *             @OA\Property(property="customer_notes", type="string", example="The need for my inner purpose."),
	 *             @OA\Property(property="internal_notes", type="string", example="Please deliver between 9am-5pm."),
	 *             @OA\Property(
	 *                 property="products",
	 *                 type="array",
	 *                 @OA\Items(
	 *                     required={"product_id", "vendor_id", "quantity", "shipping_charge"},
	 *                     @OA\Property(property="product_id", type="integer", example=2001),
	 *                     @OA\Property(property="vendor_id", type="integer", example=22),
	 *                     @OA\Property(property="quantity", type="integer", example=2),
	 *                     @OA\Property(property="shipping_charge", type="number", example=50.00, description="Product Shipping Charge"),
	 *                     @OA\Property(
	 *                         property="accessory_item_ids",
	 *                         type="array",
	 *                         description="Array of accessory item IDs",
	 *                         @OA\Items(type="integer", example=50)
	 *                     )
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
		/* Parse boolean strings to actual booleans */
		$booleanFields = [
			'is_lift_gate',
			'is_residential_address',
			'is_inside_delivery',
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

		$request->validate([
			'quote_name' => 'required|string',
			'customer_address_id' => 'required|integer|exists:customer_addresses,id',
			'is_lift_gate' => 'nullable|boolean',
			'is_residential_address' => 'nullable|boolean',
			'is_inside_delivery' => 'nullable|boolean',
			'tax_percentage' => 'required|numeric|min:0',

			'additional_amount_name' => 'nullable|required_with:additional_amount_price|string|max:255',
			'additional_amount_price' => 'nullable|required_with:additional_amount_name|numeric|min:0',
			'additional_amount_details' => 'nullable|string',  /* ✅ Added */

			'coupon_id' => 'nullable|integer',
			'discount' => 'nullable|numeric|min:0',

			'additional_discount_option' => 'nullable|boolean',
			'additional_discount_reason' => 'nullable|string|max:255',
			'additional_discount_type' => 'nullable|in:fixed,percentage',
			'additional_discount_percentage' => 'nullable|numeric|min:0|max:100|required_if:additional_discount_type,percentage',
			'additional_discount_amount' => 'nullable|numeric|min:0|required_if:additional_discount_type,fixed',

			'products' => 'required|array|min:1',
			'products.*.product_id' => 'required|integer|exists:ec_products,id',
			'products.*.vendor_id' => 'required|integer|exists:vendors,id',
			'products.*.quantity' => 'required|integer|min:1',
			'products.*.shipping_charge' => 'required|numeric|min:0',
			'products.*.accessory_item_ids' => 'nullable|array',
			'products.*.accessory_item_ids.*' => 'integer|exists:accessory_items,id',
			'emails' => 'array',
			'emails.*' => 'email',
		]);

		$customerId = auth()->id();

		$address = CustomerAddress::with('relatedCountry:id,name,margin')
		->select(['id', 'customer_id', 'country'])
		->where('customer_id', $customerId)
		->find($request->customer_address_id);

		if (!$address) {
			return response()->json([
				'success' => false,
				'message' => 'The selected address does not belong to the customer.'
			], 422);
		}

		$margin = $address->relatedCountry->margin ?? 0;

		$customer = auth()->user();
		$amountCalculations = $this->calculateAmount($request, $customer->is_tax_free, margin: $margin);

		DB::beginTransaction();

		try {
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

				'is_lift_gate' => $request->boolean('is_lift_gate'),
				'is_residential_address' => $request->boolean('is_residential_address'),
				'is_inside_delivery' => $request->boolean('is_inside_delivery'),
				'amount' => $amountCalculations['subtotal'],

				'additional_amount_name' => $request->additional_amount_name ?? null,
				'additional_amount_price' => $request->additional_amount_price ?? null,
				'additional_amount_details' => $request->additional_amount_details ?? null,  /* ✅ Added */

				'coupon_id' => $request->coupon_id ?? null,
				'discount' => $amountCalculations['discount'],

				'additional_discount_reason' => $amountCalculations['additional_discount_reason'],
				'additional_discount_type' => $amountCalculations['additional_discount_type'],
				'additional_discount_percentage' => $amountCalculations['additional_discount_percentage'],
				'additional_discount_amount' => $amountCalculations['additional_discount_amount'],

				'tax_percentage' => $amountCalculations['tax_percentage'],
				'tax_amount' => $amountCalculations['tax_amount'],
				'shipping_charge' => $amountCalculations['shipping_charge'],

				'total_amount' => $amountCalculations['grand_total'],
				'total_products' => $amountCalculations['total_products'],
				'payment_terms' => $request->payment_terms,
				'customer_notes' => $request->customer_notes,
				'internal_notes' => $request->internal_notes,
				'status' => 'Pending',
				'expired_at' => now()->addDays(7),
				'created_by' => 0,
			]);

			foreach ($amountCalculations['product_details'] as $product) {
				$total = $product['quantity'] * $product['unit_price'];
				$quoteProduct = QuoteProduct::create([
					'quote_id' => $quote->id,
					'product_id' => $product['product_id'],
					'vendor_id' => $product['vendor_id'],
					'quantity' => $product['quantity'],
					'unit_price' => $product['unit_price'],
					'amount' => $total,
					'accessory_item_charge' => $product['accessory_item_charge'],
					'shipping_charge' => $product['shipping_charge'],
					'total_amount' => $total + $product['shipping_charge'] + $product['accessory_item_charge'],
				]);

				foreach ($product['accessoryItems'] as $accessoryItem) {
					$quoteProduct->accessoryCharges()->create([
						'accessory_item_id' => $accessoryItem['id'],
						'amount' => $accessoryItem['price'] * $product['quantity'],
						'created_at' => now(),
					]);
				}
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

			return response()->json([
				'success' => true,
				'message' => 'Quote created successfully',
				'data' => $quote
			]);

		} catch (\Exception $e) {
			DB::rollBack();

			return response()->json([
				'success' => false,
				'message' => 'Failed to create quote: ' . $e->getMessage()
			]);
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
				'message' => 'Quote not found.',
			]);
		}

		/* Load relationships */
		$quote->load([
			'customer:id,name,email,type,country_code,mobile_number',
			'customerAddress:id,address,city,country',
			'customerAddress.relatedCountry:id,name,currency_id',
			'customerAddress.relatedCountry.currency:id,title,symbol',
			'quoteProducts:id,quote_id,product_id,vendor_id,quantity,unit_price,amount,shipping_charge,total_amount',
			'quoteProducts.product:id,name,images,sku,brand_id,currency_id,barcode',
			'quoteProducts.product.brand:id,name',
			'quoteProducts.product.currency:id,symbol',
			'quoteProducts.product.seoProductUrl:id,relational_id,relational_type,url',
			'quoteProducts.product.sellingUnitAttribute:id,product_id,attribute_value',
			'quoteProducts.product.warrantyAttribute:id,product_id,attribute_value',
			'quoteEmails',
		]);

		/* Batch-fetch vendor product suppliers — not a relation, so with() cannot be used */
		$allQuoteProducts = $quote->quoteProducts;

		if ($allQuoteProducts->isNotEmpty()) {
			$vendorSuppliers = ProductSupplier::where(function ($query) use ($allQuoteProducts) {
				foreach ($allQuoteProducts as $quoteProduct) {
					$query->orWhere(function ($q) use ($quoteProduct) {
						$q->where('product_id', $quoteProduct->product_id)
						->where('vendor_id', $quoteProduct->vendor_id);
					});
				}
			})
			->select('id', 'product_id', 'vendor_id', 'price', 'sale_price', 'shipping_charge', 'delivery_days', 'return_policy')
			->get()
			->keyBy(fn($item) => $item->product_id . '_' . $item->vendor_id);

			/* Attach supplier as dynamic attribute on each quote product */
			foreach ($allQuoteProducts as $quoteProduct) {
				$key = $quoteProduct->product_id . '_' . $quoteProduct->vendor_id;
				$quoteProduct->vendor_product_supplier = $vendorSuppliers->get($key);
			}
		}

		/* Batch-fetch accessory charges grouped by quote product id */
		$quoteProductIds = $allQuoteProducts->pluck('id')->toArray();
		$accessoryCharges = AccessoryCharge::where('relation_type', QuoteProduct::class)
		->whereIn('relation_id', $quoteProductIds)
		->with([
			'accessoryItem.accessory',
		])
		->get()
		->groupBy('relation_id');

		/* Resolve source currency based on deployment */
		$isUAE = in_array(config('app.website'), ['UAE', 'UAE_T']);
		$sourceCurrencyTitle = $isUAE ? 'AED' : 'USD';
		$sourceCurrencySymbol = $isUAE ? 'AED' : '$';

		/* Resolve target currency and conversion rate from customer address */
		$targetCurrency = $quote->customerAddress->relatedCountry->currency ?? null;
		$targetCurrencyTitle = $targetCurrency->title ?? $sourceCurrencyTitle;
		$targetCurrencySymbol = $targetCurrency->symbol ?? $sourceCurrencySymbol;
		$conversionRate = CurrencyConverter::getRate($sourceCurrencyTitle, $targetCurrencyTitle) ?? 1;

		/* Process each quote product */
		foreach ($quote->quoteProducts as $quoteProduct) {
			/* Decode product images and flatten relations */
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

			/* Attach supplier data from pre-fetched dynamic attribute */
			$supplier = $quoteProduct->vendor_product_supplier;
			$quoteProduct->product_supplier = $supplier ? $supplier->only(['price', 'sale_price', 'shipping_charge', 'delivery_days', 'return_policy']) : null;
			unset($quoteProduct->vendor_product_supplier);

			/* Expected shipping date based on supplier delivery days */
			$quoteProduct->expectedShippingDate = $quoteProduct->product_supplier ? getDateRange($quote->created_at, $quoteProduct->product_supplier['delivery_days']) : null;

			/* Map accessory charges with currency conversion */
			$charges = $accessoryCharges->get($quoteProduct->id, collect());
			$quoteProduct->accessory_charges = $charges->map(function ($charge) use ($conversionRate) {
				return [
					'id' => $charge->id,
					'accessory_item_id' => $charge->accessory_item_id,
					'accessory_item_name' => $charge->accessoryItem->name ?? null,
					'accessory_item_price' => number_format(($charge->accessoryItem->price ?? 0) * $conversionRate, 2, '.', ''),
					'product_accessory_id' => $charge->accessoryItem->accessory->id ?? null,
					'product_accessory_name' => $charge->accessoryItem->accessory->name ?? null,
					'amount' => number_format($charge->amount * $conversionRate, 2, '.', ''),
				];
			})->values();

			/* Convert supplier price fields */
			if ($quoteProduct->product_supplier) {
				$productSupplier = $quoteProduct->product_supplier;
				foreach (['price', 'sale_price', 'shipping_charge'] as $key) {
					if (isset($productSupplier[$key])) {
						$productSupplier[$key] = number_format($productSupplier[$key] * $conversionRate, 2, '.', '');
					}
				}
				$quoteProduct->product_supplier = $productSupplier;
			}

			/* Convert quote product amount fields */
			foreach (['unit_price', 'amount', 'accessory_item_charge', 'shipping_charge', 'total_amount'] as $key) {
				if (isset($quoteProduct->$key)) {
					$quoteProduct->$key = number_format($quoteProduct->$key * $conversionRate, 2, '.', '');
				}
			}
		}

		/* Convert quote-level amount fields */
		foreach (['shipping_charge', 'amount', 'tax_amount', 'discount', 'additional_amount_price', 'additional_discount_amount', 'total_amount'] as $key) {
			if (isset($quote->$key)) {
				$quote->$key = number_format($quote->$key * $conversionRate, 2, '.', '');
			}
		}

		return response()->json([
			'success' => true,
			'data' => $quote,
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
	 *             @OA\Property(property="is_lift_gate", type="boolean", example=true, description="Lift gate required"),
	 *             @OA\Property(property="is_residential_address", type="boolean", example=true, description="Residential address"),
	 *             @OA\Property(property="is_inside_delivery", type="boolean", example=true, description="Inside delivery required"),
	 *             @OA\Property(property="tax_percentage", type="number", format="float", example=5),
	 *
	 *             @OA\Property(property="additional_amount_name", type="string", example="Accessory 1", description="Additional amount name"),
	 *             @OA\Property(property="additional_amount_price", type="number", format="float", example=100, description="Additional amount price"),
	 *
	 *             @OA\Property(property="coupon_id", type="integer", example=1, description="Coupon ID"),
	 *             @OA\Property(property="discount", type="number", format="float", example=200, description="Discount amount"),
	 *
	 *             @OA\Property(property="additional_discount_option", type="boolean", example=true, description="Additional Discount Option"),
	 *             @OA\Property(property="additional_discount_reason", type="string", example="Bulk order discount", description="Reason for additional discount"),
	 *             @OA\Property(property="additional_discount_type", type="string", enum={"fixed", "percentage"}, example="percentage"),
	 *             @OA\Property(property="additional_discount_percentage", type="number", format="float", example=10.50, description="Additional discount percentage"),
	 *             @OA\Property(property="additional_discount_amount", type="number", format="float", example=50.00, description="Additional discount amount"),
	 *
	 *             @OA\Property(property="payment_terms", type="string", example="Credit Card"),
	 *             @OA\Property(property="customer_notes", type="string", example="The need for my inner purpose."),
	 *             @OA\Property(property="internal_notes", type="string", example="Please deliver between 9am-5pm."),
	 *             @OA\Property(
	 *                 property="products",
	 *                 type="array",
	 *                 @OA\Items(
	 *                     required={"product_id", "vendor_id", "quantity", "shipping_charge"},
	 *                     @OA\Property(property="product_id", type="integer", example=101, description="Product ID"),
	 *                     @OA\Property(property="vendor_id", type="integer", example=22, description="Vendor ID"),
	 *                     @OA\Property(property="quantity", type="integer", example=5, description="Product quantity"),
	 *                     @OA\Property(property="shipping_charge", type="number", example=50.00, description="Product Shipping Charge"),
	 *                     @OA\Property(
	 *                         property="accessory_item_ids",
	 *                         type="array",
	 *                         description="Array of accessory item IDs",
	 *                         @OA\Items(type="integer", example=50)
	 *                     )
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
		/* Parse boolean strings to actual booleans */
		$booleanFields = [
			'is_lift_gate',
			'is_residential_address',
			'is_inside_delivery',
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

		$quote = Quote::where('customer_id', auth()->id())
		->where('id', $id)
		->first();

		if (!$quote) {
			return response()->json([
				'success' => false,
				'message' => "Quote not found."
			]);
		}

		$request->validate([
			'quote_name' => 'required|string',
			'customer_address_id' => 'required|integer|exists:customer_addresses,id',
			'is_lift_gate' => 'nullable|boolean',
			'is_residential_address' => 'nullable|boolean',
			'is_inside_delivery' => 'nullable|boolean',
			'tax_percentage' => 'required|numeric|min:0',

			'additional_amount_name' => 'nullable|required_with:additional_amount_price|string|max:255',
			'additional_amount_price' => 'nullable|required_with:additional_amount_name|numeric|min:0',
			'additional_amount_details' => 'nullable|string',  /* ✅ Added */

			'coupon_id' => 'nullable|integer',
			'discount' => 'nullable|numeric|min:0',

			'additional_discount_option' => 'nullable|boolean',
			'additional_discount_reason' => 'nullable|string|max:255',
			'additional_discount_type' => 'nullable|in:fixed,percentage',
			'additional_discount_percentage' => 'nullable|numeric|min:0|max:100|required_if:additional_discount_type,percentage',
			'additional_discount_amount' => 'nullable|numeric|min:0|required_if:additional_discount_type,fixed',

			'products' => 'required|array|min:1',
			'products.*.product_id' => 'required|integer|exists:ec_products,id',
			'products.*.vendor_id' => 'required|integer|exists:vendors,id',
			'products.*.quantity' => 'required|integer|min:1',
			'products.*.shipping_charge' => 'required|numeric|min:0',
			'products.*.accessory_item_ids' => 'nullable|array',
			'products.*.accessory_item_ids.*' => 'integer|exists:accessory_items,id',
		]);

		$customerId = auth()->id();

		$address = CustomerAddress::with('relatedCountry:id,name,margin')
		->select(['id', 'customer_id', 'country'])
		->where('customer_id', $customerId)
		->find($request->customer_address_id);

		if (!$address) {
			return response()->json([
				'success' => false,
				'message' => 'The selected address does not belong to the customer.'
			], 422);
		}

		$margin = $address->relatedCountry->margin ?? 0;

		$customer = auth()->user();
		$amountCalculations = $this->calculateAmount($request, $customer->is_tax_free, margin: $margin);

		DB::beginTransaction();

		try {
			$quote->update([
				'quote_name' => $request->quote_name,
				'customer_address_id' => $request->customer_address_id,

				'is_lift_gate' => $request->boolean('is_lift_gate'),
				'is_residential_address' => $request->boolean('is_residential_address'),
				'is_inside_delivery' => $request->boolean('is_inside_delivery'),
				'amount' => $amountCalculations['subtotal'],

				'additional_amount_name' => $request->additional_amount_name ?? null,
				'additional_amount_price' => $request->additional_amount_price ?? null,
				'additional_amount_details' => $request->additional_amount_details ?? null,  /* ✅ Added */

				'coupon_id' => $request->coupon_id ?? null,
				'discount' => $amountCalculations['discount'],

				'additional_discount_reason' => $amountCalculations['additional_discount_reason'],
				'additional_discount_type' => $amountCalculations['additional_discount_type'],
				'additional_discount_percentage' => $amountCalculations['additional_discount_percentage'],
				'additional_discount_amount' => $amountCalculations['additional_discount_amount'],

				'tax_percentage' => $amountCalculations['tax_percentage'],
				'tax_amount' => $amountCalculations['tax_amount'],
				'shipping_charge' => $amountCalculations['shipping_charge'],

				'total_amount' => $amountCalculations['grand_total'],
				'total_products' => $amountCalculations['total_products'],
				'payment_terms' => $request->payment_terms,
				'customer_notes' => $request->customer_notes,
				'internal_notes' => $request->internal_notes,
			]);

			/* Delete existing products and re-insert */
			QuoteProduct::where('quote_id', $quote->id)->delete();

			foreach ($amountCalculations['product_details'] as $product) {
				$total = $product['quantity'] * $product['unit_price'];
				$quoteProduct = QuoteProduct::create([
					'quote_id' => $quote->id,
					'product_id' => $product['product_id'],
					'vendor_id' => $product['vendor_id'],
					'quantity' => $product['quantity'],
					'unit_price' => $product['unit_price'],
					'amount' => $total,
					'accessory_item_charge' => $product['accessory_item_charge'],
					'shipping_charge' => $product['shipping_charge'],
					'total_amount' => $total + $product['shipping_charge'] + $product['accessory_item_charge'],
				]);

				foreach ($product['accessoryItems'] as $accessoryItem) {
					$quoteProduct->accessoryCharges()->create([
						'accessory_item_id' => $accessoryItem['id'],
						'amount' => $accessoryItem['price'] * $product['quantity'],
						'created_at' => now(),
					]);
				}
			}

			DB::commit();

			$batch = Bus::batch([])->name('Quote Mails')->dispatch();
			$batch->options['queue'] = config('app.website') . '_QOT_PLC';
			$batch->add(new QuotePlacedMailJob([
				'recordId' => $quote->id
			]));

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
