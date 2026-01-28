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
	 *     path="/api/frontend/pre-purchase-claims",
	 *     summary="Get all pre purchase claims of the authenticated customer",
	 *     tags={"FrontEnd-PrePurchaseClaims"},
	 *     @OA\Parameter(name="page", in="query", description="Page number for pagination", example=1, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="length", in="query", description="Number of records per page.", example=20, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="global", in="query", description="Global search for all fields", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="sort_by", in="query", description="Column name to sort by", @OA\Schema(type="string", enum={"id", "product_name", "product_url", "customer_city", "customer_zipcode", "created_at", "updated_at"})),
	 *     @OA\Parameter(name="sort_dir", in="query", description="Sort direction (asc or desc)", example="asc", @OA\Schema(type="string", enum={"asc", "desc"})),
	 *     @OA\Response(response=200, description="List retrieved successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function index(Request $request)
	{
		$searchableColumns = ['id', 'product_name', 'product_url', 'customer_city', 'customer_zipcode'];
		$sortableColumns = array_merge($searchableColumns, ['created_at', 'updated_at']);

		$sortBy = in_array($request->input('sort_by'), $sortableColumns) ? $request->input('sort_by') : 'id';
		$sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

		$recordsQuery = PrePurchaseClaim::where('pre_purchase_claims.customer_id', auth()->id());

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


			/* Eager load relationships */
			$recordsQuery->with([
				'customerAddress:id,city,zip_code',
				'product:id,name,images,sku',
				'product.seoProductUrl:id,relational_id,relational_type,url',
			]);

			/* Global search */
			if ($request->filled('global')) {
				$search = $request->input('global');
				$recordsQuery->where(function ($q) use ($searchableColumns, $search) {
					foreach ($searchableColumns as $col) {
						if ($col === 'product_name') {
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
			if ($sortBy === 'product_name') {
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
	 * @OA\Post(
	 *     path="/api/frontend/pre-purchase-claims",
	 *     summary="Create a new pre-purchase claim",
	 *     tags={"FrontEnd-PrePurchaseClaims"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="multipart/form-data",
	 *             @OA\Schema(
	 *                 required={"customer_name", "customer_email", "product_url", "product_quantity", "competitor_product_url", "competitor_product_price", "customer_address", "customer_country", "customer_city", "is_confirm", "is_agree"},
	 *
	 *                 @OA\Property(property="customer_name", type="string", example="John Doe"),
	 *                 @OA\Property(property="customer_business_name", type="string", example="Doe Enterprises"),
	 *                 @OA\Property(property="customer_email", type="string", format="email", example="john@example.com"),
	 *                 @OA\Property(property="customer_country_code", type="string", example="+91"),
	 *                 @OA\Property(property="customer_mobile_number", type="string", example="971500000000"),
	 *                 @OA\Property(property="customer_address", type="string", example="123 Main Street"),
	 *                 @OA\Property(property="customer_country", type="string", example="UAE"),
	 *                 @OA\Property(property="customer_state", type="string", example="Dubai"),
	 *                 @OA\Property(property="customer_city", type="string", example="Dubai"),
	 *                 @OA\Property(property="customer_zipcode", type="string", example="560001"),
	 *                 @OA\Property(property="product_url", type="string", example="https://www.thehorecastore.com/products/abc"),
	 *                 @OA\Property(property="product_quantity", type="integer", example=2),
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
	 * )
	 */
	public function store(Request $request)
	{
		/* Validate request */
		$request->validate([
			'customer_name' => 'required|string|max:255',
			'customer_business_name' => 'nullable|string|max:255',
			'customer_email' => 'required|email:strict|max:255',
			'customer_country_code' => 'nullable|string|max:20',
			'customer_mobile_number' => 'nullable|string|max:20',

			'product_url' => [
				'required',
				'url',
				function ($attribute, $value, $fail) {
					$frontEndUrl = rtrim(config('app.url'), '/');
					if (strpos($value, $frontEndUrl) !== 0) {
						$fail("The {$attribute} must start with {$frontEndUrl}");
					}
				},
			],
			'product_quantity' => 'required|integer|min:1',
			'competitor_product_url' => 'required|url',
			'competitor_product_price' => 'required|numeric|min:0',
			'competitor_product_shipping_charge' => 'nullable|numeric|min:0',
			'competitor_screenshot' => 'nullable|file|mimes:jpeg,png,jpg,webp|max:2048',

			'customer_address' => 'required|string',
			'customer_country' => 'required|string',
			'customer_state' => 'nullable|string',
			'customer_city' => 'required|string',
			'customer_zipcode' => 'nullable|string|max:20',

			'is_confirm' => 'required|in:true,1',
			'is_agree' => 'required|in:true,1',
		]);

		/* Extract slug part of product_url */
		$frontEndUrl = config('app.url');
		$productSlug = str_replace($frontEndUrl . '/products/', '', $request->product_url);

		/* Find product ID from SeoManagement */
		$productID = SeoManagement::where('url', $productSlug)->value('relational_id');
		if (!$productID) {
			return response()->json([
				'success' => false,
				'message' => 'Invalid product URL provided.'
			], 422);
		}
		try {
			/* Start transaction */
			DB::beginTransaction();

			$batch = Bus::batch([])->name('Pre Claim Mails')->dispatch();

			$customer = Customer::where('email', $request->customer_email)->first();
			if (!$customer) {
				$randomPassword = Str::random(8);

				$customer = Customer::create([
					'name' => $request->customer_name,
					'business_name' => $request->customer_business_name,
					'email' => $request->customer_email,
					'password' => Hash::make($randomPassword),
					'type' => 'Private',
					'country_code' => $request->customer_country_code,
					'mobile_number' => $request->customer_mobile_number,
				]);
				$this->sendToOdoo($customer);

				$customerAddress = CustomerAddress::create([
					'customer_id' => $customer->id,
					'type' => 'Home',
					'address' => $request->customer_address,
					'country' => $request->customer_country,
					'state' => $request->customer_state,
					'city' => $request->customer_city,
					'zip_code' => $request->customer_zipcode,
					'is_default' => true,
				]);

				$batch->options['queue'] = config('app.website') . '_CLM_WLCM';
				$batch->add(new PreClaimWelcomeMailJob([
					'recordId' => $customer->id,
					'randomPassword' => $randomPassword,
				]));
			} else {
				$customerAddress = $customer->customerAddress()
				->where('address', $request->customer_address)
				->where('country', $request->customer_country)
				->where('city', $request->customer_city)
				->first();

				/* If not found, create new */
				if (!$customerAddress) {
					$customerAddress = CustomerAddress::create([
						'customer_id' => $customer->id,
						'type' => 'Home',
						'address' => $request->customer_address,
						'country' => $request->customer_country,
						'state' => $request->customer_state,
						'city' => $request->customer_city,
						'zip_code' => $request->customer_zipcode,
					]);
				}
			}

			/* Upload competitor screenshot if provided */
			$competitorProductImgURL = null;
			if ($request->hasFile('competitor_screenshot')) {
				$competitorProductImgURL = uploadImageToWebpS3FromFile(
					$request,
					'competitor_screenshot',
					env('STORAGE_ENV') . '/pre_purchase_claims/screenshot'
				);
			}

			$claim = PrePurchaseClaim::create([
				'customer_id' => $customer->id,
				'customer_address_id' => $customerAddress->id ?? null,
				'product_id' => $productID,
				'product_quantity' => $request->product_quantity,
				'competitor_product_url' => $request->competitor_product_url,
				'competitor_product_price' => $request->competitor_product_price,
				'competitor_product_shipping_charge' => $request->competitor_product_shipping_charge ?? 0,
				'competitor_screenshot_url' => $competitorProductImgURL,
			]);

			$batch->options['queue'] = config('app.website') . '_PRE_CLM';
			$batch->add(new PreClaimMailJob([
				'recordId' => $claim->id,
			]));

			/* Commit transaction */
			DB::commit();

			return response()->json([
				'success' => true,
				'message' => __("msg_create"),
				'data' => $claim
			], 201);
		} catch (\Exception $e) {
			/* Rollback transaction */
			DB::rollBack();

			return response()->json([
				'success' => false,
				'message' => __('Something went wrong. Please try again.'),
				'error'   => $e->getMessage()
			], 500);
		}
	}

	private function sendToOdoo($customer)
	{
		try {
			$response = Http::withHeaders([
				'Content-Type' => 'application/json',
				'Cookie' => 'session_id=57ddd7bc96ea7900b2615ef1db5e65409c0e0e98',
			])->post('https://horecastore-staging-20736821.dev.odoo.com/web/dataset/call_kw', [
				'jsonrpc' => '2.0',
				'method' => 'call',
				'params' => [
					'model' => 'res.partner',
					'method' => 'create',
					'args' => [[
						'company_type' => 'person',
						'name' => $customer->name,
						'email' => $customer->email,
						'phone' => ($customer->country_code ? $customer->country_code . ' ' : '') . $customer->mobile_number,
						'mobile' => ($customer->country_code ? $customer->country_code . ' ' : '') . $customer->mobile_number,
						'business_name' =>  $customer->business_name,
						'image_1920' => '',
						'website' => '',
						'is_customer' => "1",
						'vat_not_register' => "0",
						'vat' => '215444',
						'street' => '12',
						'street2' => 'new',
						'city' => 'Surat',
						'zip' => '395006',
						'website_partner_id' => $customer->id,
						'website_customer' => 1
					]],
					'kwargs' => new \stdClass()
				],
				'id' => 1
			]);

			if ($response->successful()) {
				\Log::info('Customer synced to Odoo successfully.', ['odoo_response' => $response->json()]);
			} else {
				\Log::error('Failed to sync customer to Odoo.', [
					'status' => $response->status(),
					'body' => $response->body()
				]);
			}
		} catch (\Exception $e) {
			\Log::error('Exception syncing to Odoo: ' . $e->getMessage());
		}
	}

	/**
	 * @OA\Get(
	 *     path="/api/frontend/pre-purchase-claims/{id}",
	 *     summary="Get pre purchase claim details",
	 *     tags={"FrontEnd-PrePurchaseClaims"},
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
		$record = PrePurchaseClaim::where('customer_id', auth()->id())
		->where('id', $id)
		->first();

		if (!$record) {
			return response()->json([
				'success' => false,
				'message' => "Pre purchase claim not found."
			]);
		}

		/* Load relationships */
		$record->load([
			'customerAddress:id,city,zip_code',
			'product:id,name,images,sku',
			'product.seoProductUrl:id,relational_id,relational_type,url',
		]);

		/* Transform results */
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
