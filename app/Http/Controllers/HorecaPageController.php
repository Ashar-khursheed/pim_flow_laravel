<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\FrontEnd\HorecaPage;

class HorecaPageController extends BaseController
{
	/**
	 * @OA\Get(
	 *     path="/api/horeca-pages",
	 *     summary="Get list of horeca pages",
	 *     description="Get paginated list of horeca pages with search and sort functionality",
	 *     tags={"Horeca Pages"},
	 *     @OA\Parameter(name="page", in="query", description="Page number for pagination", example=1, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="length", in="query", description="Number of records per page", example=20, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="global", in="query", description="Global search for all fields", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="sort_by", in="query", description="Column name to sort by", @OA\Schema(type="string", enum={"id", "name", "is_active", "created_by", "created_at", "updated_at"})),
	 *     @OA\Parameter(name="sort_dir", in="query", description="Sort direction (asc or desc)", example="asc", @OA\Schema(type="string", enum={"asc", "desc"})),
	 *     @OA\Response(response=200, description="Horeca pages list retrieved successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function index(Request $request)
	{
		$searchableColumns = ['id', 'name', 'is_active', 'created_by'];
		$sortableColumns = array_merge($searchableColumns, ['created_at', 'updated_at']);

		$sortBy = in_array($request->input('sort_by'), $sortableColumns) ? $request->input('sort_by') : 'id';
		$sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

		$recordsQuery = HorecaPage::query();

		if ($request->filled('page') && $request->filled('length')) {
			/* Join users table if needed for sorting or searching by creator */
			if ($sortBy === 'created_by' || ($request->filled('global') && in_array('created_by', $searchableColumns))) {
				$recordsQuery->leftJoin('users as creator_user', 'horeca_pages.created_by', '=', 'creator_user.id');
				$recordsQuery->select('horeca_pages.*');
			}

			/* Load relationships */
			$recordsQuery->with([
				'creator:id,first_name,last_name',
				'updator:id,first_name,last_name',
			]);

			/* Global search */
			if ($request->filled('global')) {
				$search = $request->input('global');
				$recordsQuery->where(function ($q) use ($searchableColumns, $search) {
					foreach ($searchableColumns as $col) {
						if ($col === 'is_active') {
							/* Search for 'active' or 'inactive' status */
							$searchLower = strtolower($search);
							if (strpos($searchLower, 'active') !== false) {
								if (strpos($searchLower, 'inactive') !== false) {
									$q->orWhere('horeca_pages.is_active', 0);
								} else {
									$q->orWhere('horeca_pages.is_active', 1);
								}
							}
						} elseif ($col === 'created_by') {
							$q->orWhereHas('creator', function ($sub) use ($search) {
								$sub->where(function ($q2) use ($search) {
									$q2->where('first_name', 'like', '%' . $search . '%')
									->orWhere('last_name', 'like', '%' . $search . '%')
									->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ['%' . $search . '%']);
								});
							});
						} else {
							$q->orWhere("horeca_pages.$col", 'like', '%' . $search . '%');
						}
					}
				});
			}

			/* Sorting */
			if ($sortBy === 'created_by') {
				$recordsQuery->orderByRaw("CONCAT(creator_user.first_name, ' ', creator_user.last_name) $sortDir");
			} elseif ($sortBy === 'is_active') {
				$recordsQuery->orderBy('horeca_pages.is_active', $sortDir);
			} else {
				$recordsQuery->orderBy("horeca_pages.$sortBy", $sortDir);
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
			->get(['horeca_pages.id', 'horeca_pages.name', 'horeca_pages.banner_url', 'horeca_pages.is_active', 'horeca_pages.created_by', 'horeca_pages.updated_by', 'horeca_pages.updated_at']);

			/* Transform records */
			$records->transform(function ($record) {
				$record->created_by = $record->creator
				? trim($record->creator->first_name . ' ' . $record->creator->last_name)
				: null;
				$record->updated_by = $record->updator
				? trim($record->updator->first_name . ' ' . $record->updator->last_name)
				: null;
				unset($record->creator, $record->updator);
				$record->is_active = $record->is_active == 1 ? 'Active' : 'Inactive';
				return $record;
			});

		} else {
			/* Simple list without pagination */
			$records = $recordsQuery->orderBy('name', 'asc')->get(['id', 'name']);
			$totalRecords = $records->count();
			$totalPages = 1;
		}

		return response()->json([
			'success' => true,
			'message' => 'Records retrieved successfully',
			'data' => $records,
			'total_pages' => $totalPages,
			'total_records' => $totalRecords,
		]);
	}

	/**
	 * @OA\Post(
	 *     path="/api/horeca-pages",
	 *     tags={"Horeca Pages"},
	 *     summary="Create a new horeca page",
	 *     description="Create a new horeca page with categories and products",
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="multipart/form-data",
	 *             @OA\Schema(
	 *                 required={"name", "banner", "categories", "products"},
	 *                 @OA\Property(property="name", type="string", example="Premium Coffee Solutions"),
	 *                 @OA\Property(property="description", type="string", nullable=true, example="Complete coffee solutions for hotels and restaurants"),
	 *                 @OA\Property(property="link_name", type="string", nullable=true, example="View Coffee Range"),
	 *                 @OA\Property(property="link_url", type="string", nullable=true, example="/products/coffee"),
	 *                 @OA\Property(property="banner", type="string", format="binary", description="Banner image (jpeg,jpg,png,webp)"),
	 *                 @OA\Property(property="left_para_description", type="string", nullable=true, example="Left side description content"),
	 *                 @OA\Property(property="right_para_description", type="string", nullable=true, example="Right side description content"),
	 *                 @OA\Property(property="faqs", type="string", nullable=true, example="JSON or HTML formatted FAQs"),
	 *                 @OA\Property(property="is_active", type="boolean", example=true),
	 *
	 *                 @OA\Property(
	 *                     property="categories",
	 *                     type="array",
	 *                     description="Array of categories",
	 *                     @OA\Items(
	 *                         required={"category_id", "order"},
	 *                         @OA\Property(property="category_id", type="integer", example=101, description="Category ID"),
	 *                         @OA\Property(property="order", type="integer", example=5, description="Category order")
	 *                     )
	 *                 ),
	 *
	 *                 @OA\Property(
	 *                     property="products",
	 *                     type="array",
	 *                     description="Array of product groups by type",
	 *                     @OA\Items(
	 *                         required={"type", "items"},
	 *                         @OA\Property(property="type", type="string", example="Featured", description="Product type/group name"),
	 *                         @OA\Property(property="description", type="string", nullable=true, example="Our best-selling products", description="Common description for this type"),
	 *                         @OA\Property(property="order", type="integer", example=1, description="Type order"),
	 *                         @OA\Property(
	 *                             property="items",
	 *                             type="array",
	 *                             description="Products in this type",
	 *                             @OA\Items(
	 *                                 required={"product_id"},
	 *                                 @OA\Property(property="product_id", type="integer", example=101, description="Product ID"),
	 *                                 @OA\Property(property="order", type="integer", example=1, description="Product order within type")
	 *                             )
	 *                         )
	 *                     )
	 *                 )
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=201, description="Horeca page created successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}},
	 * )
	 */
	public function store(Request $request)
	{
		/* Parse boolean strings to actual booleans */
		$booleanFields = [
			'is_active',
		];

		/* Laravel's boolean() method handles this better */
		foreach ($booleanFields as $field) {
			if ($request->has($field)) {
				$request->merge([
					$field => $request->boolean($field)
				]);
			}
		}

		/* Handle JSON string conversion for categories */
		if ($request->has('categories') && is_string($request->categories)) {
			$categoryString = $request->categories;
			if (strpos(trim($categoryString), '{') === 0 && strpos(trim($categoryString), '[') !== 0) {
				$categoryString = '[' . $categoryString . ']';
			}
			$categories = json_decode($categoryString, true);
			$request->merge(['categories' => $categories]);
		}

		/* Handle JSON string conversion for products */
		if ($request->has('products') && is_string($request->products)) {
			$productsString = $request->products;
			if (strpos(trim($productsString), '{') === 0 && strpos(trim($productsString), '[') !== 0) {
				$productsString = '[' . $productsString . ']';
			}
			$products = json_decode($productsString, true);
			$request->merge(['products' => $products]);
		}

		/* Validate the request */
		$data = $request->validate(
			[
				'name' => 'required|string|max:255',
				'description' => 'nullable|string',
				'link_name' => 'nullable|string|max:255',
				'link_url' => 'nullable|string',
				'banner' => 'required|file|mimes:jpeg,jpg,png,webp|max:2048',
				'left_para_description' => 'nullable|string',
				'right_para_description' => 'nullable|string',
				'faqs' => 'nullable|string',
				'is_active' => 'nullable|boolean',

				/* Categories validation */
				'categories' => 'required|array|min:1',
				'categories.*.category_id' => 'required|integer|exists:categories,id,status,published',
				'categories.*.order' => 'nullable|integer',

				/* Products validation - grouped by type */
				'products' => 'required|array|min:1',
				'products.*.type' => 'required|string|max:255',
				'products.*.description' => 'nullable|string',
				'products.*.order' => 'nullable|integer',
				'products.*.items' => 'required|array|min:1',
				'products.*.items.*.product_id' => 'required|integer|exists:ec_products,id,status,published',
				'products.*.items.*.order' => 'nullable|integer',
			],
			[
				'categories.*.category_id.exists' => 'The selected category must be published and active.',
				'products.*.items.*.product_id.exists' => 'The selected product must be published and active.',
			]
		);

		try {
			DB::beginTransaction();

			/* Handle File Upload to S3 */
			$data['banner_url'] = uploadImageToWebpS3FromFile($request, 'banner', env('STORAGE_ENV') . 'horeca_pages/banners');
			unset($data['banner']);

			/* Add created_by and updated_by */
			$data['created_by'] = auth()->id();
			$data['updated_by'] = auth()->id();

			/* Remove categories and products from data array */
			$categories = $data['categories'];
			$productGroups = $data['products'];
			unset($data['categories'], $data['products']);

			/* Create the horeca page */
			$horecaPage = HorecaPage::create($data);

			/* Attach categories */
			if (!empty($categories)) {
				$categoriesData = [];
				foreach ($categories as $category) {
					$categoriesData[$category['category_id']] = [
						'order' => $category['order'] ?? 0,
					];
				}
				$horecaPage->categories()->attach($categoriesData);
			}

			/* Create product types and attach products */
			if (!empty($productGroups)) {
				foreach ($productGroups as $group) {
					/* Create product type */
					$productType = $horecaPage->productTypes()->create([
						'type' => $group['type'],
						'description' => $group['description'] ?? null,
						'order' => $group['order'] ?? 0,
					]);

					/* Attach products to this type */
					$productsData = [];
					foreach ($group['items'] as $item) {
						$productsData[$item['product_id']] = [
							'horeca_page_id' => $horecaPage->id,
							'order' => $item['order'] ?? 0,
						];
					}
					$productType->products()->attach($productsData);
				}
			}

			DB::commit();

			/* Load relationships */
			$horecaPage->load(['categories', 'productTypes.products']);

			return response()->json([
				'success' => true,
				'message' => 'Horeca page created successfully',
				'data' => $horecaPage
			], 201);

		} catch (\Exception $e) {
			DB::rollBack();

			return response()->json([
				'success' => false,
				'message' => 'Failed to create horeca page',
				'error' => $e->getMessage()
			], 500);
		}
	}

	/**
	 * @OA\Get(
	 *     path="/api/horeca-pages/{id}",
	 *     summary="Get Horeca page details",
	 *     tags={"Horeca Pages"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         description="Horeca Page ID",
	 *         required=true,
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\Response(response=200, description="Horeca page details retrieved successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function show($id)
	{
		try {
			/* Find the horeca page with relationships */
			$page = HorecaPage::with([
				'categories:id,name,image',
				'productTypes:id,horeca_page_id,type,description,order',
				'productTypes.products' => function($query) {
					$query->select([
						'ec_products.id',
						'ec_products.name',
						'ec_products.images',
						'ec_products.sku',
						'ec_products.currency_id',
					])
					->with([
						'productSuppliers' => function($q) {
							$q->select(['id', 'product_id', 'sale_price', 'price'])
							->cheapest();
						},
						'currency:id,title,symbol',
					]);
				}
			])->find($id);

			/* Transform categories data */
			if ($page->categories) {
				$page->categories->transform(function ($category) {
					$category->order = optional($category->pivot)->order ?? null;
					unset($category->pivot);
					return $category;
				});
			}

			/* Transform product types and their products */
			if ($page->productTypes) {
				$page->productTypes->transform(function ($productType) {
					if ($productType->products) {
						$productType->products->transform(function ($product) {
							/* Get cheapest supplier (already filtered by cheapest() scope) */
							$supplier = $product->productSuppliers->first();

							/* Add custom attributes */
							$product->order = $product->pivot->order ?? null;
							$product->currency_title = $product->currency->title ?? null;
							$product->currency_symbol = $product->currency->symbol ?? null;

							/* Calculate price from cheapest supplier */
							if ($supplier) {
								$product->price = ($supplier->sale_price > 0 && $supplier->sale_price < $supplier->price)
								? $supplier->sale_price
								: $supplier->price;
							} else {
								$product->price = null;
							}

							/* Remove relations */
							unset($product->pivot);
							unset($product->currency);
							unset($product->currency_id);
							unset($product->productSuppliers);

							return $product;
						});
					}
					return $productType;
				});
			}


			/* Check if page exists */
			if (!$page) {
				return response()->json([
					'success' => false,
					'message' => 'Horeca page not found'
				], 404);
			}

			return response()->json([
				'success' => true,
				'message' => 'Horeca page retrieved successfully',
				'data' => $page
			], 200);

		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Failed to retrieve horeca page',
				'error' => $e->getMessage()
			], 500);
		}
	}
}