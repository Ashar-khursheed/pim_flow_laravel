<?php
namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\FrontEnd\HorecaPage;

class HorecaPageController extends BaseController
{
	/**
	 * @OA\Post(
	 *     path="/api/frontend/horeca-pages",
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
			'categories.seoUrl:id,relational_id,relational_type,url',
			'productTypes:id,horeca_page_id,type,description,order',
			'productTypes.products' => function($query) {
				$query->select([
						'ec_products.id',
						'ec_products.name',
						'ec_products.sku',
						'ec_products.currency_id',
						'ec_products.alt_tags',
						'ec_products.quote_available',
						'ec_products.brand_id'
					])
					->with([
						'seoUrl:id,relational_id,relational_type,url',
						'productSuppliers' => function($q) {
							$q->select(['id', 'product_id', 'vendor_id', 'vendor_sku', 'cost_per_item', 'sale_price', 'price', 'inventory', 'in_stock', 'min_quantity', 'is_fixed', 'delivery_days', 'return_policy', 'free_shipping', 'shipping_charge', 'warranty_information'])
								->cheapest();
						},
						'reviews:id,product_id,star',
						'currency:id,title,symbol',
						'sellingUnitAttribute',
					])
					->withCount('reviews')
					->withAvg('reviews', 'star');
			}
		])->find($id);

			/* Transform categories data */
			if ($page->categories) {
				$page->categories->transform(function ($category) {
					$category->order = optional($category->pivot)->order ?? null;
					$category->slug = optional($category->seoUrl)->url ?? null;
					unset($category->pivot);
					unset($category->seoUrl);
					return $category;
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