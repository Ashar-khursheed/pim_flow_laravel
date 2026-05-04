<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\FrontEnd\CustomerCart;
use App\Models\FrontEnd\CustomerCartProduct;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\FrontEnd\Customer;

class AbandonedCartController extends Controller
{
	/**
	 * @OA\Get(
	 *     path="/api/abandoned-carts",
	 *     tags={"Abandoned Carts"},
	 *      security={{"bearerAuth":{}}},
	 *     summary="Get list of abandoned carts",
	 *     description="Returns a paginated list of abandoned carts with customer info, addresses, product, and brand",
	 *     @OA\Parameter(
	 *         name="page",
	 *         in="query",
	 *         description="Page number",
	 *         required=false,
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\Parameter(
	 *         name="per_page",
	 *         in="query",
	 *         description="Items per page",
	 *         required=false,
	 *         @OA\Schema(type="integer", example=10)
	 *     ),
	 *     @OA\Parameter(
	 *         name="search",
	 *         in="query",
	 *         description="Search by customer name or product name",
	 *         required=false,
	 *         @OA\Schema(type="string", example="John")
	 *     ),
	 *     @OA\Parameter(
	 *         name="sort_by",
	 *         in="query",
	 *         description="Sort by column",
	 *         required=false,
	 *         @OA\Schema(type="string", example="created_at")
	 *     ),
	 *     @OA\Parameter(
	 *         name="sort_order",
	 *         in="query",
	 *         description="Sort order (asc or desc)",
	 *         required=false,
	 *         @OA\Schema(type="string", example="desc")
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="List of abandoned carts",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="status", type="boolean", example=true),
	 *             @OA\Property(property="data", type="array", @OA\Items(
	 *                 @OA\Property(property="id", type="integer", example=1),
	 *                 @OA\Property(property="quantity", type="integer", example=2),
	 *                 @OA\Property(property="created_at", type="string", example="2025-08-10T12:00:00Z"),
	 *                 @OA\Property(property="customer", type="object",
	 *                     @OA\Property(property="id", type="integer", example=5),
	 *                     @OA\Property(property="name", type="string", example="John Doe"),
	 *                     @OA\Property(property="addresses", type="array", @OA\Items(
	 *                         @OA\Property(property="address", type="string", example="123 Main Street"),
	 *                         @OA\Property(property="city", type="string", example="New York")
	 *                     ))
	 *                 ),
	 *                 @OA\Property(property="product", type="object",
	 *                     @OA\Property(property="name", type="string", example="Nike Shoes"),
	 *                     @OA\Property(property="sku", type="string", example="NK123"),
	 *                     @OA\Property(property="brand", type="object",
	 *                         @OA\Property(property="name", type="string", example="Nike")
	 *                     )
	 *                 )
	 *             ))
	 *         )
	 *     )
	 * )
	 */
	public function index(Request $request)
	{
		$threshold = Carbon::now()->subHours(1);
		$perPage = $request->get('per_page', 10);
		$search = $request->get('search');
		$sortBy = $request->get('sort_by', 'created_at');
		$sortOrder = $request->get('sort_order', 'desc');

		// Get distinct customer IDs who have abandoned carts before the threshold
		$customerIdsQuery = CustomerCart::where('created_at', '<=', $threshold)
		->select('customer_id')
		->distinct();

		// Apply search if provided
		if ($search) {
			$customerIdsQuery->whereHas('customer', function($query) use ($search) {
				$query->where('name', 'like', "%{$search}%");
			})->orWhereHas('customerCartProducts.product', function($query) use ($search) {
				$query->where('name', 'like', "%{$search}%");
			});
		}

		$customerIds = $customerIdsQuery->pluck('customer_id');

		// Paginate the Customer model based on these IDs
		$customersQuery = Customer::whereIn('id', $customerIds);

		// Apply sorting
		if ($sortBy === 'created_at') {
			$customersQuery->orderBy('created_at', $sortOrder);
		} else {
			$customersQuery->orderBy($sortBy, $sortOrder);
		}

		$customers = $customersQuery->paginate($perPage);

		// Get abandoned carts for these customers
		$customerCartData = [];
		foreach ($customers as $customer) {
			$abandonedCarts = CustomerCart::where('customer_id', $customer->id)
			->where('created_at', '<=', $threshold)
			->with([
				'customerCartProducts' => function($query) {
					$query->with([
						'product' => function($q) {
							$q->select('id', 'sku', 'name', 'images', 'brand_id')
							->with('brand:id,name');
						}
					]);
				}
			])
			->get();

			if ($abandonedCarts->isNotEmpty()) {
				$customerCartData[] = [
					'customer' => $customer,
					'carts' => $this->transformCartData($abandonedCarts)
				];
			}
		}

		return response()->json([
			'status' => true,
			'data' => $customerCartData,
			'pagination' => [
				'total' => $customers->total(),
				'per_page' => $customers->perPage(),
				'current_page' => $customers->currentPage(),
				'last_page' => $customers->lastPage(),
			],
		]);
	}

	/**
	 * @OA\Get(
	 *     path="/api/abandoned-carts/{id}",
	 *     tags={"Abandoned Carts"},
	 *     security={{"bearerAuth":{}}},
	 *     summary="Get details of a specific abandoned cart",
	 *     description="Returns abandoned cart details by ID with customer info, addresses, product, and brand",
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         description="Customer ID",
	 *         required=true,
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Abandoned cart details",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="status", type="boolean", example=true),
	 *             @OA\Property(property="data", type="object",
	 *                 @OA\Property(property="id", type="integer", example=1),
	 *                 @OA\Property(property="quantity", type="integer", example=2),
	 *                 @OA\Property(property="created_at", type="string", example="2025-08-10T12:00:00Z"),
	 *                 @OA\Property(property="customer", type="object",
	 *                     @OA\Property(property="id", type="integer", example=5),
	 *                     @OA\Property(property="name", type="string", example="John Doe"),
	 *                     @OA\Property(property="addresses", type="array", @OA\Items(
	 *                         @OA\Property(property="address", type="string", example="123 Main Street"),
	 *                         @OA\Property(property="city", type="string", example="New York")
	 *                     ))
	 *                 ),
	 *                 @OA\Property(property="product", type="object",
	 *                     @OA\Property(property="name", type="string", example="Nike Shoes"),
	 *                     @OA\Property(property="sku", type="string", example="NK123"),
	 *                     @OA\Property(property="brand", type="object",
	 *                         @OA\Property(property="name", type="string", example="Nike")
	 *                     )
	 *                 )
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=404,
	 *         description="Cart not found"
	 *     )
	 * )
	 */
	public function show($customerId)
	{
		$threshold = Carbon::now()->subHours(1);

		// Get all abandoned carts for a specific customer
		$abandonedCarts = CustomerCart::with([
			'customer:id,name,email',
			'customer.customerAddress',
			'customerCartProducts' => function($query) {
				$query->with([
					'product' => function($q) {
						$q->select('id', 'sku', 'name', 'images', 'brand_id')
						->with([
							'brand:id,name',
							'productSuppliers' => function($ps) {
								$ps->select('id','product_id','price','sale_price','vendor_id')
								->with(['vendor:id,name']);
							},
						]);
					},
				]);
			}
		])
		->where('customer_id', $customerId)
		// remove or adjust threshold
		// ->where('created_at', '<=', $threshold)
		->get();



		if ($abandonedCarts->isEmpty()) {
			return response()->json([
				'status' => false,
				'message' => 'No abandoned carts found for this customer'
			], 404);
		}

		$customer = $abandonedCarts->first()->customer;
		$transformedCarts = $this->transformCartDataWithPricing($abandonedCarts);

		$result = [
			'customer' => $customer,
			'carts' => $transformedCarts
		];

		return response()->json([
			'status' => true,
			'data' => $result
		]);
	}

	/**
	 * @OA\Get(
	 *     path="/api/customers-by-date-range",
	 *     summary="Get customer IDs with carts within a date range",
	 *     tags={"Abandoned Carts"},
	 *     security={{"bearerAuth":{}}},
	 *     @OA\Parameter(
	 *         name="start_date",
	 *         in="query",
	 *         description="Start date in YYYY-MM-DD format",
	 *         @OA\Schema(type="string", format="date")
	 *     ),
	 *     @OA\Parameter(
	 *         name="end_date",
	 *         in="query",
	 *         description="End date in YYYY-MM-DD format (must be after or equal to start_date)",
	 *         @OA\Schema(type="string", format="date")
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="List of customer IDs found",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="status", type="boolean", example=true),
	 *             @OA\Property(
	 *                 property="data",
	 *                 type="array",
	 *                 @OA\Items(type="integer", example=123)
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=404,
	 *         description="No customers found in the date range",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="status", type="boolean", example=false),
	 *             @OA\Property(property="message", type="string", example="No customers found with carts in this date range")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=422,
	 *         description="Validation error",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="message", type="string", example="The start_date field is required."),
	 *             @OA\Property(property="errors", type="object")
	 *         )
	 *     )
	 * )
	 */
	public function getCustomersByDateRange(Request $request)
	{
		$request->validate([
			'start_date' => 'required|date',
			'end_date'   => 'required|date|after_or_equal:start_date',
		]);

		$startDate = Carbon::parse($request->start_date)->startOfDay();
		$endDate   = Carbon::parse($request->end_date)->endOfDay();

		$customers = Customer::whereBetween('updated_at', [$startDate, $endDate])
		->orWhereHas('customerCarts', function ($q) use ($startDate, $endDate) {
			$q->whereBetween('created_at', [$startDate, $endDate])
			->orWhereBetween('updated_at', [$startDate, $endDate])
			->orWhereHas('customerCartProducts', function ($p) use ($startDate, $endDate) {
				$p->whereBetween('created_at', [$startDate, $endDate])
				->orWhereBetween('updated_at', [$startDate, $endDate]);
			});
		})
		->pluck('id');


		return response()->json([
			'status' => true,
			'data'   => $customers,
		]);
	}


	/**
	 * Transform cart data to maintain the same response structure
	 */
	private function transformCartData($abandonedCarts)
	{
		$result = [];

		foreach ($abandonedCarts as $cart) {
			foreach ($cart->customerCartProducts as $cartProduct) {
				$images = collect(json_decode($cartProduct->product->images, true) ?: [])
				->map(fn($url) => ['url' => $url])
				->toArray();

				$result[] = [
					'id' => $cartProduct->id,
					'quantity' => $cartProduct->quantity,
					'created_at' => $cart->created_at,
					'product' => [
						'id' => $cartProduct->product->id,
						'sku' => $cartProduct->product->sku,
						'name' => $cartProduct->product->name,
						'images' => $images,
						'brand' => $cartProduct->product->brand,
					],
				];
			}
		}

		return $result;
	}

	/**
	 * Transform cart data with pricing information
	 */
	private function transformCartDataWithPricing($abandonedCarts)
	{
		$result = [];

		foreach ($abandonedCarts as $cart) {
			foreach ($cart->customerCartProducts as $cartProduct) {
				$images = collect(json_decode($cartProduct->product->images, true) ?: [])
				->map(fn($url) => ['url' => $url])
				->toArray();

				// Get supplier pricing - try to match vendor_id, fallback to first supplier
				$supplier = $cartProduct->product->productSuppliers
				->where('vendor_id', $cartProduct->vendor_id)
				->first();

				if (!$supplier) {
					$supplier = $cartProduct->product->productSuppliers->first();
				}

				$result[] = [
					'id' => $cartProduct->id,
					'quantity' => $cartProduct->quantity,
					'created_at' => $cart->created_at,
					'product' => [
						'id' => $cartProduct->product->id,
						'sku' => $cartProduct->product->sku,
						'name' => $cartProduct->product->name,
						'images' => $images,
						'brand' => $cartProduct->product->brand,
						'price' => optional($supplier)->price,
						'sale_price' => optional($supplier)->sale_price,
					],
				];
			}
		}

		return $result;
	}
}