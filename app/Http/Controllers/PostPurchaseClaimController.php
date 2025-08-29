<?php

namespace App\Http\Controllers;

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
	 *     path="/api/post-purchase-claims",
	 *     summary="Get all post purchase claims of the authenticated customer",
	 *     tags={"PostPurchaseClaims"},
	 *     @OA\Parameter(name="page", in="query", description="Page number for pagination", example=1, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="length", in="query", description="Number of records per page.", example=20, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="global", in="query", description="Global search for all fields", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="sort_by", in="query", description="Column name to sort by", @OA\Schema(type="string", enum={"id", "customer_name", "customer_email", "order_number", "product_name", "customer_city", "customer_zipcode", "created_at", "updated_at"})),
	 *     @OA\Parameter(name="sort_dir", in="query", description="Sort direction (asc or desc)", example="asc", @OA\Schema(type="string", enum={"asc", "desc"})),
	 *     @OA\Response(response=200, description="List retrieved successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function index(Request $request)
	{
		$searchableColumns = ['id', 'customer_name', 'customer_email', 'order_number', 'product_name', 'customer_city', 'customer_zipcode'];
		$sortableColumns = array_merge($searchableColumns, ['created_at', 'updated_at']);

		$sortBy = in_array($request->input('sort_by'), $sortableColumns) ? $request->input('sort_by') : 'id';
		$sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

		$recordsQuery = PostPurchaseClaim::query();

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

			/* join for customer name or email */
			if (
				$sortBy === 'customer_name' ||
				$sortBy === 'customer_email' ||
				($request->filled('global') && (array_intersect(['customer_name', 'customer_email'], $searchableColumns)))
			) {
				$recordsQuery->leftJoin('customers', 'post_purchase_claims.customer_id', '=', 'customers.id');
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
				'customer:id,name,email',
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
						if ($col === 'customer_name') {
							$q->orWhereHas('customer', function ($sub) use ($search) {
								$sub->where('name', 'like', '%' . $search . '%');
							});
						} elseif ($col === 'customer_email') {
							$q->orWhereHas('customer', function ($sub) use ($search) {
								$sub->where('email', 'like', '%' . $search . '%');
							});
						} elseif ($col === 'order_number') {
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
			if ($sortBy === 'customer_name') {
				$recordsQuery->orderBy('customers.name', $sortDir);
			} elseif ($sortBy === 'customer_email') {
				$recordsQuery->orderBy('customers.email', $sortDir);
			} elseif ($sortBy === 'order_number') {
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
				if ($record->customer) {
					$record->customer_name = $record->customer->name ?? null;
					$record->customer_email = $record->customer->email ?? null;
					unset($record->customer);
				}
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
	 * @OA\Get(
	 *     path="/api/post-purchase-claims/{id}",
	 *     summary="Get post purchase claim details",
	 *     tags={"PostPurchaseClaims"},
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
		$record = PostPurchaseClaim::find($id);

		if (!$record) {
			return response()->json([
				'success' => false,
				'message' => "Post purchase claim not found."
			]);
		}

		/* Load relationships */
		$record->load([
			'customer:id,name,email',
			'order:id,order_number,customer_address_id',
			'order.customerAddress:id,city,zip_code',
			'orderProduct:id,product_id',
			'orderProduct.product:id,name,images,sku',
		]);

		/* Transform results */
		if ($record->customer) {
			$record->customer_name = $record->customer->name ?? null;
			$record->customer_email = $record->customer->email ?? null;
			unset($record->customer);
		}
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
