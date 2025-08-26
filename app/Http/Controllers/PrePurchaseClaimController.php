<?php

namespace App\Http\Controllers;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

use Illuminate\Support\Facades\Bus;
use Illuminate\Bus\Batch;

use App\Models\SeoManagement;
use App\Models\FrontEnd\Customer;
use App\Models\FrontEnd\CustomerAddress;
use App\Models\FrontEnd\PrePurchaseClaim;

use App\Jobs\Welcome\PreClaimWelcomeMailJob;
use App\Jobs\Welcome\PreClaimMailJob;

class PrePurchaseClaimController extends BaseController
{
	/**
	 * @OA\Get(
	 *     path="/api/pre-purchase-claims",
	 *     summary="Get all pre purchase claims",
	 *     tags={"PrePurchaseClaims"},
	 *     @OA\Parameter(name="page", in="query", description="Page number for pagination", example=1, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="length", in="query", description="Number of records per page.", example=20, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="global", in="query", description="Global search for all fields", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="sort_by", in="query", description="Column name to sort by", @OA\Schema(type="string", enum={"id", "customer_name", "customer_email", "product_name", "product_url", "customer_city", "customer_zipcode", "created_at", "updated_at"})),
	 *     @OA\Parameter(name="sort_dir", in="query", description="Sort direction (asc or desc)", example="asc", @OA\Schema(type="string", enum={"asc", "desc"})),
	 *     @OA\Response(response=200, description="List retrieved successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function index(Request $request)
	{
		$searchableColumns = ['id', 'customer_name', 'customer_email', 'product_name', 'product_url', 'customer_city', 'customer_zipcode'];
		$sortableColumns = array_merge($searchableColumns, ['created_at', 'updated_at']);

		$sortBy = in_array($request->input('sort_by'), $sortableColumns) ? $request->input('sort_by') : 'id';
		$sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

		$recordsQuery = PrePurchaseClaim::query();

		/* Check if pagination requested */
		if ($request->filled('page') && $request->filled('length')) {
			/* join for product_name */
			if ($sortBy === 'product_name' || ($request->filled('global') && in_array('product_name', $searchableColumns))) {
				$recordsQuery->leftJoin('ec_products as p_name', 'pre_purchase_claims.product_id', '=', 'p_name.id');
				$recordsQuery->addSelect('pre_purchase_claims.*');
			}

			/* join for product_url */
			if ($sortBy === 'product_url' || ($request->filled('global') && in_array('product_url', $searchableColumns))) {
				$recordsQuery
				->leftJoin('ec_products as p_url', 'pre_purchase_claims.product_id', '=', 'p_url.id')
				->leftJoin('seo_management', function ($join) {
					$join->on('p_url.id', '=', 'seo_management.relational_id')
					->where(function ($q) {
						$q->where('seo_management.relational_type', 'Product')
						->orWhere('seo_management.relational_type', 'App\\Models\\Product');
					});
				})
				->addSelect('pre_purchase_claims.*');
			}

			/* join for customer city or zipcode */
			if (
				$sortBy === 'customer_city' ||
				$sortBy === 'customer_zip_code' ||
				($request->filled('global') && (array_intersect(['customer_city', 'customer_zip_code'], $searchableColumns)))
			) {
				$recordsQuery->leftJoin('customer_addresses', 'pre_purchase_claims.customer_address_id', '=', 'customer_addresses.id');
				$recordsQuery->addSelect('pre_purchase_claims.*');
			}

			/* join for customer name or email */
			if (
				$sortBy === 'customer_name' ||
				$sortBy === 'customer_email' ||
				($request->filled('global') && (array_intersect(['customer_name', 'customer_email'], $searchableColumns)))
			) {
				$recordsQuery->leftJoin('customers', 'pre_purchase_claims.customer_id', '=', 'customers.id');
				$recordsQuery->addSelect('pre_purchase_claims.*');
			}


			/* Eager load relationships */
			$recordsQuery->with([
				'customer:id,name,email',
				'customerAddress:id,city,zip_code',
				'product:id,name,images,sku',
				'product.seoProductUrl:id,relational_id,relational_type,url',
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
						} elseif ($col === 'product_name') {
							$q->orWhereHas('product', function ($sub) use ($search) {
								$sub->where('name', 'like', '%' . $search . '%');
							});
						} elseif ($col === 'product_url') {
							$q->orWhereHas('product', function ($sub) use ($search) {
								$sub->whereHas('seoProductUrl', function ($sub1) use ($search) {
									$sub1->where('url', 'like', '%' . $search . '%');
								});
							});
						} elseif ($col === 'customer_city') {
							$q->orWhereHas('customerAddress', function ($sub) use ($search) {
								$sub->where('city', 'like', '%' . $search . '%');
							});
						} elseif ($col === 'customer_zipcode') {
							$q->orWhereHas('customerAddress', function ($sub) use ($search) {
								$sub->where('zip_code', 'like', '%' . $search . '%');
							});
						} else {
							$q->orWhere("pre_purchase_claims.$col", 'like', '%' . $search . '%');
						}
					}
				});
			}

			/* Sorting */
			if ($sortBy === 'customer_name') {
				$recordsQuery->orderBy('customers.name', $sortDir);
			} elseif ($sortBy === 'customer_email') {
				$recordsQuery->orderBy('customers.email', $sortDir);
			} elseif ($sortBy === 'product_name') {
				$recordsQuery->orderBy('ec_products.name', $sortDir);
			} elseif ($sortBy === 'product_url') {
				$recordsQuery->orderBy('seo_management.url', $sortDir);
			} elseif ($sortBy === 'customer_city') {
				$recordsQuery->orderBy('customer_addresses.city', $sortDir);
			} elseif ($sortBy === 'customer_zipcode') {
				$recordsQuery->orderBy('customer_addresses.zip_code', $sortDir);
			} else {
				$recordsQuery->orderBy("pre_purchase_claims.$sortBy", $sortDir);
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

				if ($record->product) {
					$record->product_name = $record->product->name ?? null;
					$record->product_images = is_array($record->product->images) ? $record->product->images : (is_array($decoded = json_decode($record->product->images, true)) ? $decoded : null);
					$record->product_sku = $record->product->sku ?? null;
					$record->product_url = $record->product->seoProductUrl->url ?? null;
					unset($record->product->seoProductUrl, $record->product);
				}

				if ($record->customerAddress) {
					$record->customer_city = $record->customerAddress->city ?? null;
					$record->customer_zip_code = $record->customerAddress->zip_code ?? null;
					unset($record->customerAddress);
				}
				return $record;
			});
		} else {
			/* No pagination: just fetch id */
			$records = PrePurchaseClaim::orderBy('id', 'asc')->get(['id']);
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
	 *     path="/api/pre-purchase-claims/{id}",
	 *     summary="Get pre purchase claim details",
	 *     tags={"PrePurchaseClaims"},
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
		$record = PrePurchaseClaim::find($id);

		if (!$record) {
			return response()->json([
				'success' => false,
				'message' => "Pre purchase claim not found."
			]);
		}

		/* Load relationships */
		$record->load([
			'customer:id,name,email',
			'customerAddress:id,city,zip_code',
			'product:id,name,images,sku',
			'product.seoProductUrl:id,relational_id,relational_type,url',
		]);

		/* Transform results */
		if ($record->customer) {
			$record->customer_name = $record->customer->name ?? null;
			$record->customer_email = $record->customer->email ?? null;
			unset($record->customer);
		}

		if ($record->product) {
			$record->product_name = $record->product->name ?? null;
			$record->product_images = is_array($record->product->images) ? $record->product->images : (is_array($decoded = json_decode($record->product->images, true)) ? $decoded : null);
			$record->product_sku = $record->product->sku ?? null;
			$record->product_url = $record->product->seoProductUrl->url ?? null;
			// $record->product_url = $record->product->seoProductUrl?->url ? rtrim(config('app.url'), '/') . '/products/' . $record->product->seoProductUrl->url : null;

			unset($record->product->seoProductUrl, $record->product);
		}

		if ($record->customerAddress) {
			$record->customer_city = $record->customerAddress->city ?? null;
			$record->customer_zip_code = $record->customerAddress->zip_code ?? null;
			unset($record->customerAddress);
		}

		return response()->json([
			'success' => true,
			'data' => $record
		]);
	}
}
