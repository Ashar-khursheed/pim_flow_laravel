<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Bus;
use Illuminate\Bus\Batch;

use App\Models\FrontEnd\Order;
use App\Models\FrontEnd\PostPurchaseClaim;

use App\Jobs\Welcome\PostClaimMailJob;

class PostPurchaseClaimController extends BaseController
{
	/**
	 * @OA\Get(
	 *     path="/api/frontend/post-purchase-claims",
	 *     summary="Get all post purchase claims of the authenticated customer",
	 *     tags={"FrontEnd-PostPurchaseClaims"},
	 *     @OA\Parameter(name="page", in="query", description="Page number for pagination", example=1, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="length", in="query", description="Number of records per page.", example=20, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="global", in="query", description="Global search for all fields", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="sort_by", in="query", description="Column name to sort by", @OA\Schema(type="string", enum={"id", "order_number", "product_name", "customer_city", "customer_zipcode", "created_at", "updated_at"})),
	 *     @OA\Parameter(name="sort_dir", in="query", description="Sort direction (asc or desc)", example="asc", @OA\Schema(type="string", enum={"asc", "desc"})),
	 *     @OA\Response(response=200, description="List retrieved successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function index(Request $request)
	{
		$searchableColumns = ['id', 'order_number', 'product_name', 'customer_city', 'customer_zipcode'];
		$sortableColumns = array_merge($searchableColumns, ['created_at', 'updated_at']);

		$sortBy = in_array($request->input('sort_by'), $sortableColumns) ? $request->input('sort_by') : 'id';
		$sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

		$recordsQuery = PostPurchaseClaim::where('post_purchase_claims.customer_id', auth()->id());

		/* Check if pagination requested */
		if ($request->filled('page') && $request->filled('length')) {
			/* join for product_name */
			if ($sortBy === 'order_number' || ($request->filled('global') && in_array('order_number', $searchableColumns))) {
				$recordsQuery->leftJoin('orders as o_num', 'post_purchase_claims.order_id', '=', 'o_num.id');
				$recordsQuery->addSelect('post_purchase_claims.*');
			}

			/* join for product_name */
			if ($sortBy === 'product_name' || ($request->filled('global') && in_array('product_name', $searchableColumns))) {
				$recordsQuery
				->leftJoin('order_products', 'post_purchase_claims.order_product_id', '=', 'order_products.id')
				->leftJoin('ec_products', 'order_products.product_id', '=', 'ec_products.id');
				$recordsQuery->addSelect('post_purchase_claims.*');
			}

			/* join for customer city or zipcode */
			if (
				$sortBy === 'customer_city' ||
				$sortBy === 'customer_zip_code' ||
				($request->filled('global') && (array_intersect(['customer_city', 'customer_zip_code'], $searchableColumns)))
			) {
				$recordsQuery
				->leftJoin('orders as o_customer', 'post_purchase_claims.order_id', '=', 'o_customer.id')
				->leftJoin('customer_addresses', 'o_customer.customer_address_id', '=', 'customer_addresses.id');
				$recordsQuery->addSelect('post_purchase_claims.*');
			}


			/* Eager load relationships */
			$recordsQuery->with([
				'order:id,order_number,customer_address_id',
				'order.customerAddress:id,city,zip_code',
				'orderProduct:id,product_id',
				'orderProduct.product:id,name,images,sku',
			]);

			/* Global search */
			if ($request->filled('global')) {
				$search = $request->input('global');
				$recordsQuery->where(function ($q) use ($searchableColumns, $search) {
					foreach ($searchableColumns as $col) {
						if ($col === 'order_number') {
							$q->orWhereHas('order', function ($sub) use ($search) {
								$sub->where('order_number', 'like', '%' . $search . '%');
							});
						} elseif ($col === 'product_name') {
							$q->orWhereHas('orderProduct', function ($sub) use ($search) {
								$sub->whereHas('product', function ($sub1) use ($search) {
									$sub1->where('name', 'like', '%' . $search . '%');
								});
							});
						} elseif ($col === 'customer_city') {
							$q->orWhereHas('order', function ($sub) use ($search) {
								$sub->whereHas('customerAddress', function ($sub1) use ($search) {
									$sub1->where('city', 'like', '%' . $search . '%');
								});
							});
						} elseif ($col === 'customer_zipcode') {
							$q->orWhereHas('order', function ($sub) use ($search) {
								$sub->whereHas('customerAddress', function ($sub1) use ($search) {
									$sub1->where('zip_code', 'like', '%' . $search . '%');
								});
							});
						} else {
							$q->orWhere("post_purchase_claims.$col", 'like', '%' . $search . '%');
						}
					}
				});
			}

			/* Sorting */
			if ($sortBy === 'order_number') {
				$recordsQuery->orderBy('orders.order_number', $sortDir);
			} elseif ($sortBy === 'product_name') {
				$recordsQuery->orderBy('ec_products.name', $sortDir);
			} elseif ($sortBy === 'customer_city') {
				$recordsQuery->orderBy('customer_addresses.city', $sortDir);
			} elseif ($sortBy === 'customer_zipcode') {
				$recordsQuery->orderBy('customer_addresses.zip_code', $sortDir);
			} else {
				$recordsQuery->orderBy("post_purchase_claims.$sortBy", $sortDir);
			}

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
				if ($record->order) {
					$record->order_number = $record->order->order_number ?? null;
					if ($record->order->customerAddress) {
						$record->customer_city = $record->order->customerAddress->city ?? null;
						$record->customer_zip_code = $record->order->customerAddress->zip_code ?? null;
						unset($record->order->customerAddress);
					}
					unset($record->order->customerAddress, $record->order);
				}

				if ($record->orderProduct->product) {
					$record->orderProduct->product_name = $record->orderProduct->product->name ?? null;
					$record->orderProduct->product_images = is_array($record->orderProduct->product->images) ? $record->orderProduct->product->images : (is_array($decoded = json_decode($record->orderProduct->product->images, true)) ? $decoded : null);
					$record->orderProduct->product_sku = $record->orderProduct->product->sku ?? null;
					unset($record->orderProduct->product, $record->orderProduct);
				}
				return $record;
			});
		} else {
			/* No pagination: just fetch id */
			$records = PostPurchaseClaim::orderBy('id', 'asc')->get(['id']);
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
	 *     path="/api/frontend/post-purchase-claims",
	 *     summary="Create a new post-purchase claim",
	 *     tags={"FrontEnd-PostPurchaseClaims"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="multipart/form-data",
	 *             @OA\Schema(
	 *                 required={"order_id", "order_product_id", "competitor_product_url", "competitor_product_price", "is_confirm", "is_agree"},
	 *                 @OA\Property(property="order_id", type="integer", example=1),
	 *                 @OA\Property(property="order_product_id", type="integer", example=1001),
	 *                 @OA\Property(property="competitor_product_url", type="string", example="https://competitor.com/product/xyz"),
	 *                 @OA\Property(property="competitor_product_price", type="number", format="float", example=180.00),
	 *                 @OA\Property(property="competitor_product_shipping_charge", type="number", format="float", example=50.00),
	 *                 @OA\Property(property="competitor_screenshot", type="string", format="binary", description="Competitor screenshot image (jpeg, png, jpg, webp, max:2MB)"),
	 *                 @OA\Property(property="is_confirm", type="boolean", example=true),
	 *                 @OA\Property(property="is_agree", type="boolean", example=true),
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=201, description="Created successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function store(Request $request)
	{
		/* Validate request */
		$request->validate([
			'order_id' => 'required|integer|exists:orders,id',
			'order_product_id' => 'required|integer|exists:order_products,id',

			'competitor_product_url' => 'required|url',
			'competitor_product_price' => 'required|numeric|min:0',
			'competitor_product_shipping_charge' => 'nullable|numeric|min:0',
			'competitor_screenshot' => 'nullable|file|mimes:jpeg,png,jpg,webp|max:2048',

			'is_confirm' => 'required|in:true,1',
			'is_agree' => 'required|in:true,1',
		]);

		/* check order */
		$order = Order::where('customer_id', auth()->id())->where('id', $request->order_id)->first();
		if (!$order) {
			return response()->json([
				'success' => false,
				'message' => __('The selected order was not found or does not belong to you.')
			], 404);
		}

		/* Check if order is within 120 days */
		$orderDate = $order->created_at;
		$daysSinceOrder = now()->diffInDays($orderDate);

		if ($daysSinceOrder > 120) {
			return response()->json([
				'success' => false,
				'message' => __('Sorry, our price match guarantee applies within 120 days of purchase. This order is just outside that window, but we\'ll consider your future orders as promised.')
			], 422);
		}

		/* check order product */
		$orderProduct = $order->orderProducts()->find($request->order_product_id);
		if (!$orderProduct) {
			return response()->json([
				'success' => false,
				'message' => __('The selected product was not found in this order.'),
			], 404);
		}

		try {
			/* Start transaction */
			DB::beginTransaction();

			/* Upload competitor screenshot if provided */
			$competitorProductImgURL = null;
			if ($request->hasFile('competitor_screenshot')) {
				$competitorProductImgURL = uploadImageToWebpS3FromFile(
					$request,
					'competitor_screenshot',
					env('STORAGE_ENV') . '/post_purchase_claims/screenshot'
				);
			}

			/* Create claim */
			$claim = PostPurchaseClaim::create([
				'customer_id' => auth()->id(),
				'order_id' => $order->id,
				'order_product_id' => $orderProduct->id,
				'competitor_product_url' => $request->competitor_product_url,
				'competitor_product_price' => $request->competitor_product_price,
				'competitor_product_shipping_charge' => $request->competitor_product_shipping_charge ?? 0,
				'competitor_screenshot_url' => $competitorProductImgURL,
			]);

			/* Dispatch job in batch */
			$batch = Bus::batch([])->name('Post Claim Mails')->dispatch();

			$batch->options['queue'] = config('app.website') . '_POST_CLM';
			$batch->add(new PostClaimMailJob([
				'recordId' => $claim->id,
			]));

			/* Commit if everything is good */
			DB::commit();

			return response()->json([
				'success' => true,
				'message' => __("msg_create"),
				'data' => $claim
			], 201);

		} catch (\Exception $e) {
			/* Rollback on error */
			DB::rollBack();

			return response()->json([
				'success' => false,
				'message' => __('Something went wrong. Please try again.'),
				'error'   => $e->getMessage()
			], 500);
		}
	}


	/**
	 * @OA\Get(
	 *     path="/api/frontend/post-purchase-claims/{id}",
	 *     summary="Get post purchase claim details",
	 *     tags={"FrontEnd-PostPurchaseClaims"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         description="Claim ID",
	 *         required=true,
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\Response(response=200, description="Details retrieved successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function show($id)
	{
		$record = PostPurchaseClaim::where('customer_id', auth()->id())
		->where('id', $id)
		->first();

		if (!$record) {
			return response()->json([
				'success' => false,
				'message' => "Post purchase claim not found."
			]);
		}

		/* Load relationships */
		$record->load([
			'order:id,order_number,customer_address_id',
			'order.customerAddress:id,city,zip_code',
			'orderProduct:id,product_id',
			'orderProduct.product:id,name,images,sku',
		]);

		/* Transform results */
		if ($record->order) {
			$record->order_number = $record->order->order_number ?? null;
			if ($record->order->customerAddress) {
				$record->customer_city = $record->order->customerAddress->city ?? null;
				$record->customer_zip_code = $record->order->customerAddress->zip_code ?? null;
				unset($record->order->customerAddress);
			}
			unset($record->order->customerAddress, $record->order);
		}

		if ($record->orderProduct->product) {
			$record->orderProduct->product_name = $record->orderProduct->product->name ?? null;
			$record->orderProduct->product_images = is_array($record->orderProduct->product->images) ? $record->orderProduct->product->images : (is_array($decoded = json_decode($record->orderProduct->product->images, true)) ? $decoded : null);
			$record->orderProduct->product_sku = $record->orderProduct->product->sku ?? null;
			unset($record->orderProduct->product, $record->orderProduct);
		}

		return response()->json([
			'success' => true,
			'data' => $record
		]);
	}
}
