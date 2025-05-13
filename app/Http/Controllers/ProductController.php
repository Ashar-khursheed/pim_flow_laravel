<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

use Illuminate\Support\Facades\Bus;
use Illuminate\Bus\Batch;

use App\Models\Product;
use App\Models\Category;
use App\Models\Tax;
use App\Models\Currency;
use App\Models\Unit;
use App\Models\Store;
use App\Models\Review;
use App\Models\Brand;
use App\Models\Slug;
use App\Models\TransactionLog;
use App\Models\Faq;
use App\Models\Attribute;
use App\Models\UnitOfMeasurement;
use Illuminate\Support\Facades\DB;
use App\Jobs\ImportProductJob;
use App\Services\CsvImporterService;

class ProductController extends BaseController
{
	/**
	 * @OA\Get(
	 *     path="/api/products",
	 *     summary="Get paginated list of products",
	 *     description="Retrieves a paginated list of products with brand, store, categories, and slug details. Can search across product name, SKU, brand, store, and categories.",
	 *     tags={"Products"},
	 *     @OA\Parameter(
	 *         name="page",
	 *         in="query",
	 *         description="Page number for pagination",
	 *         required=false,
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\Parameter(
	 *         name="per_page",
	 *         in="query",
	 *         description="Number of products per page (default: 50)",
	 *         required=false,
	 *         @OA\Schema(type="integer", example=50)
	 *     ),
	 *     @OA\Parameter(
	 *         name="search",
	 *         in="query",
	 *         description="Search term for filtering products by name, SKU, brand, store, or category",
	 *         required=false,
	 *         @OA\Schema(type="string", example="samsung")
	 *     ),
	 *		@OA\Parameter(
	 * 				name="status",
	 *				in="query",
	 *				description="Filter products by status (e.g., active, inactive)",
	 *				required=false,
	 *				@OA\Schema(type="string", example="active")
	 *				),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Successful response",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="message", type="string", example="Products retrieved successfully"),
	 *             @OA\Property(
	 *                 property="data",
	 *                 type="array",
	 *                 @OA\Items(
	 *                     @OA\Property(property="id", type="integer", example=1),
	 *                     @OA\Property(property="name", type="string", example="Sample Product"),
	 *                     @OA\Property(property="sku", type="string", example="PROD-123"),
	 *                     @OA\Property(property="image", type="string", example="http://example.com/storage/products/sample.jpg"),
	 *                     @OA\Property(property="brand", type="string", example="Brand Name"),
	 *                     @OA\Property(property="store", type="string", example="Store Name"),
	 *                     @OA\Property(property="status", type="string", example="active"),
	 *                     @OA\Property(
	 *                         property="product_family",
	 *                         type="array",
	 *                         @OA\Items(type="string", example="Category Name")
	 *                     ),
	 *                     @OA\Property(property="taxonomy_path", type="string", example="category/product-name")
	 *                 )
	 *             ),
	 *             @OA\Property(
	 *                 property="pagination",
	 *                 type="object",
	 *                 @OA\Property(property="total", type="integer", example=100),
	 *                 @OA\Property(property="per_page", type="integer", example=50),
	 *                 @OA\Property(property="current_page", type="integer", example=1),
	 *                 @OA\Property(property="last_page", type="integer", example=5),
	 *                 @OA\Property(property="next_page_url", type="string", nullable=true, example="http://example.com/api/products?page=2"),
	 *                 @OA\Property(property="prev_page_url", type="string", nullable=true, example=null)
	 *             )
	 *         )
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function index(Request $request)
	{
		$perPage = $request->input('per_page', 50);
		$search = $request->input('search');
		$status = $request->input('status'); // Add this line


		$query = Product::with([
			'brand:id,name',
			'store:id,name',
			'categories:id,name',
			'slug:id,key,reference_id'
		])
		->select(['id', 'name', 'sku', 'images', 'brand_id', 'store_id', 'status']);

		/* Apply search if provided */

		  // Apply status filter
		  if ($status !== null) {
			$query->where('status', $status);
		}

		if ($search) {
			$query->where(function($q) use ($search) {
				$q->where('name', 'like', "%{$search}%")
				->orWhere('sku', 'like', "%{$search}%")
				->orWhereHas('brand', function($brandQuery) use ($search) {
					$brandQuery->where('name', 'like', "%{$search}%");
				})
				->orWhereHas('store', function($storeQuery) use ($search) {
					$storeQuery->where('name', 'like', "%{$search}%");
				})
				->orWhereHas('categories', function($categoryQuery) use ($search) {
					$categoryQuery->where('name', 'like', "%{$search}%");
				});
			});
		}

		$products = $query->orderBy('id', 'desc')
		->paginate($perPage);

		/* Formatting response */
		$formattedProducts = $products->map(function ($product) {
			return [
				'id' => $product->id,
				'name' => $product->name,
				'sku' => $product->sku,
				'image' => ($imageUrls = json_decode($product->images, true)) && isset($imageUrls[0]) ? $imageUrls[0] : null,
				'brand' => optional($product->brand)->name,
				'store' => optional($product->store)->name,
				'status' => $product->status,
				'product_family' => $product->categories->pluck('name')->toArray(),
				'taxonomy_path' => optional($product->slug)->key ?? '',
			];
		});

		return response()->json([
			'success' => true,
			'message' => 'Products retrieved successfully',
			'data' => $formattedProducts,
			'pagination' => [
				'total' => $products->total(),
				'per_page' => $products->perPage(),
				'current_page' => $products->currentPage(),
				'last_page' => $products->lastPage(),
				'next_page_url' => $products->nextPageUrl(),
				'prev_page_url' => $products->previousPageUrl(),
			],
		]);
	}

	/**
	 * @OA\Post(
	 *     path="/api/products",
	 *     summary="Create a new product",
	 *     description="Creates a new product with the required details.",
	 *     tags={"Products"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"name", "product_family", "sku", "website"},
	 *             @OA\Property(property="name", type="string", example="Sample Product", description="Name of the product"),
	 *             @OA\Property(property="product_family", type="integer", example=1, description="ID of the product family"),
	 *             @OA\Property(property="sku", type="string", example="PROD-123", description="Stock Keeping Unit (SKU) of the product"),
	 *             @OA\Property(
	 *                 property="websites",
	 *                 type="array",
	 *                 description="Array of website IDs where the product is available",
	 *                 @OA\Items(type="integer", example=1)
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=201,
	 *         description="Success",
	 *          @OA\MediaType(
	 *              mediaType="application/json",
	 *          )
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function store(Request $request)
	{
		/* Validate request data */
		$request->validate([
			'name' => "required|string",
			'product_family' => "required|integer",
			'sku' => "required|unique:ec_products,sku",
			'websites'=> "required|array",
		]);

		$product = new Product();
		$product->name = $request->name;
		$product->sku = $request->sku;
		$product->website_ids = implode(',', $request->websites);
		$product->status = 'draft';
		$product->created_at = now();
		$product->updated_at = now();
		$product->created_by_id = auth()->id();
		$product->created_by_type = User::class;
		$product->save();
		$this->saveProductCategory($product, $request->product_family);

		return response()->json([
			'success' => true,
			'message' => 'Product created successfully',
			'product' => $product
		]);
	}

	private function saveProductCategory($product, $categoryId)
	{
		/* Step 1: Fetch existing pivot data for the product */
		$existingCategories = $product->categories()->pluck('category_id')->toArray();

		if (!in_array($categoryId, $existingCategories)) {
			/* Clear existing specs if the category is different */
			$product->specifications()->delete();
		}

		/* Step 2: Prepare the category for syncing */
		$categoryWithTimestamp = in_array($categoryId, $existingCategories)
		? [$categoryId => []]
		: [$categoryId => ['created_at' => now()]];

		/* Step 3: Sync the single category */
		$product->categories()->sync($categoryWithTimestamp);
	}

	/**
	 * @OA\Get(
	 *     path="/api/products/{product_id}",
	 *     summary="Get product details",
	 *     description="Fetches product details based on the given product ID and attribute type.",
	 *     tags={"Products"},
	 *     @OA\Parameter(
	 *         name="product_id",
	 *         in="path",
	 *         required=true,
	 *         description="ID of the product",
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\Parameter(
	 *         name="attr_type",
	 *         in="query",
	 *         required=true,
	 *         description="Filter product attributes by category",
	 *         @OA\Schema(
	 *             type="string",
	 *             enum={"General", "Inventory & Stock Management", "Pricing & Sales", "Marketing", "Media", "Shipping & Dimensions", "Product Variations", "Store & Vendor Information", "Performance & Analytics", "Comparison & Bundling", "SEO", "Other", "All"},
	 *             example="General"
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=201,
	 *         description="Success",
	 *          @OA\MediaType(
	 *              mediaType="application/json",
	 *          )
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function show($productId, Request $request)
	{
		$attributeGroup = [
			'General' => ['sku', 'barcode', 'warranty_information', 'refund' , 'status' ],

			'Inventory & Stock Management' => ['quantity', 'allow_checkout_when_out_of_stock', 'with_storehouse_management', 'stock_status', 'variant_inventory_tracker', 'variant_inventory_quantity', 'variant_inventory_policy', 'variant_fulfillment_service'],
			'Pricing & Sales' => ['price', 'sale_price', 'sale_type', 'unit_of_measurement_id', 'cost_per_item', 'tax_id', 'currency_id', 'minimum_order_quantity', 'maximum_order_quantity', 'approved_by', 'cost_per_item_currency', 'cost_type', 'additional_cost_percentage', 'additional_cost_value', 'total_cost_per_item'],
			'Marketing' => ['name', 'content', 'description'],
			'Media' => ['images', 'image', 'video_url', 'video_path', 'documents' , 'benefits_features'],
			'Shipping & Dimensions' => ['length', 'length_unit_id', 'width', 'height', 'depth', 'weight', 'weight_unit_id', 'shipping_weight_option', 'shipping_weight', 'shipping_dimension_option', 'shipping_width', 'shipping_depth', 'shipping_height', 'shipping_length', 'shipping_length_id'],
			'Product Variations' => ['is_variation', 'variant_grams', 'variant_requires_shipping', 'variant_barcode', 'variant_color_title', 'variant_color_value'],
			'Store & Vendor Information' => ['store_id', 'brand_id', 'created_by_id', 'created_by_type'],
			'Performance & Analytics' => ['views', 'units_sold', 'frequently_bought_together'],
			'Comparison & Bundling' => ['compare_type', 'compare_products'],
			'SEO' => ['google_shopping_category', 'google_shopping_mpn'],
			'Other' => ['order', 'box_quantity', 'delivery_days' , 'website_ids'],
			'All' => []
		];

		$attributeGroup['All'] = array_merge(...array_values(array_filter($attributeGroup, fn($key) => $key !== 'All', ARRAY_FILTER_USE_KEY)));

		$relations = [
			'General' => ['categories:id,name,parent_id'],
			'Pricing & Sales' => ['currency:id,title' ,'unitOfMeasurement:id,name'],
			'Shipping & Dimensions' => ['lengthUnit:id,symbol', 'weightUnit:id,symbol', 'shippingLengthUnit:id,symbol'],
			'Store & Vendor Information' => ['store:id,name', 'brand:id,name', 'creator:id,name'],
			'SEO' => ['seoMetaData:id,reference_id,meta_value'],
			'All' => ['categories:id,name,parent_id', 'currency:id,title', 'lengthUnit:id,symbol', 'weightUnit:id,symbol', 'shippingLengthUnit:id,symbol', 'store:id,name', 'brand:id,name', 'creator:id,name', 'seoMetaData:id,reference_id,meta_value']
		];

		$attrType = $request->attr_type ?? 'All';

		$attributes = $attributeGroup[$attrType] ?? $attributeGroup['All'];
		// $with = array_merge($relations[$attrType] ?? [], ['categories:id,name,parent_id']);
		$with = array_merge($relations[$attrType] ?? [], [
			'categories:id,name,parent_id',
			'categories.parent:id,name,parent_id',
			'categories.parent.parent:id,name,parent_id',
			'categories.children:id,name,parent_id'
		]);

		$product = Product::with($with)->where('id', $productId)->first(array_merge(['id'], $attributes));
		$formattedCategories = [];

		foreach ($product->categories as $category) {
			$chain = [];

			// Step 1: Build the chain from current to root
			$current = $category;
			while ($current) {
				$chain[] = $current;
				$current = $current->parent;
			}

			// Step 2: Reverse to go from root to child
			$chain = array_reverse($chain);

			// Step 3: Build nested structure, merging by ID
			$ref = &$formattedCategories;

			foreach ($chain as $cat) {
				// Check if this category already exists at this level
				$found = false;
				foreach ($ref as &$item) {
					if ($item['id'] == $cat->id) {
						$ref = &$item['children'];
						$found = true;
						break;
					}
				}

				if (! $found) {
					$new = [
						'id' => $cat->id,
						'name' => $cat->name,
						'children' => []
					];
					$ref[] = $new;
					$ref = &$ref[array_key_last($ref)]['children'];
				}
			}

			unset($ref); // Clear reference
		}


		if (!$product) {
			return response()->json([
				'success' => false,
				'message' => 'Product does not exist.'
			]);
		}

		/* Fetch reviews where customer_id is null */
		$adminReviews = Review::where('product_id', $productId)
		->whereNull('customer_id')
		->get();


		/* Fetch FAQs using the FAQ model */
		$faqs = FAQ::where('product_id', $productId)->get();

		if (!empty($product->images) && is_string($product->images)) {
			$product->images = json_decode($product->images, true) ?? [];
		}

		if (!empty($product->video_path) && is_string($product->video_path)) {
			$product->video_path = json_decode($product->video_path, true) ?? [];
		}

		if (!empty($product->documents) && is_string($product->documents)) {
			$product->documents = json_decode($product->documents, true) ?? [];
		}

		/* Normalize the documents field */
		if (!empty($product->documents) && is_array($product->documents)) {
			$product->documents = array_map(function($item) {
				/* Check if the item is an array with 'title' and 'path', or just a string */
				if (is_array($item) && isset($item['path'])) {
					return [
						'title' => $item['title'],
						'path' => $item['path']
					];
				} elseif (is_string($item)) {
					return [
						'title' => basename($item),
						'path' => $item
					];
				}
				return null;
			}, $product->documents);
		}
		$formattedProduct = [];

		$formattedProduct['categories'] = $formattedCategories;

		foreach ($attributes as $attribute) {
			$value = $product->$attribute ?? null;

			switch ($attribute) {
				case 'refund':
				$formattedProduct[$attribute] = [['value' => $value]];

				break;
				case 'allow_checkout_when_out_of_stock':
				case 'with_storehouse_management':
				case 'variant_requires_shipping':
				case 'is_variation':
				$formattedProduct[$attribute] = [
					'type' => 'checkbox',
					'selected' => (int) $value,
					'values' => [
						'0' => 'unchecked',
						'1' => 'checked'
					]
				];
				break;

				case 'shipping_weight_option':
				$formattedProduct[$attribute] = [
					'type' => 'Dropdown',
					'selected' => $value,
					'values' => [
						'lbs' => 'LBS',
						'kg' => 'KG',
						'g' => 'Grams'
					]
				];
				break;

				case 'shipping_dimension_option':
				$formattedProduct[$attribute] = [
					'type' => 'Dropdown',
					'selected' => $value,
					'values' => [
						'inch' => 'Inch',
						'cm' => 'CM',
						'mm' => 'MM'
					]
				];
				break;
				case 'benefits_features':
				$formattedProduct['benefits_features'] = json_decode($value, true);
				break;

				case 'stock_status':
				$stockStatusMappings = [
					'in_stock' => 'In Stock',
					'out_of_stock' => 'Out of Stock',
					'on_backorder' => 'Pre Order'
				];

				/* Map selected value to frontend readable text */
				$selectedStockStatus = $stockStatusMappings[$value] ?? $value;

				$formattedProduct['stock_status'] = [
					'selected' => $selectedStockStatus, /* This will now show 'In Stock', 'Out of Stock', etc. */
					'values' => $stockStatusMappings /* Values remain the same */
				];
				break;

				case 'tax_id':
				$tax = Tax::find($value);
				if ($tax) {
					$formattedProduct['tax'] = [['title' => $tax->title, 'rate' => $tax->percentage]];
				} else {
					$formattedProduct['tax'] = [['title' => null, 'rate' => null]];
				}
				break;

				case 'currency_id':
				$formattedProduct['currency'] = $product->currency ? [[
					'id' => $product->currency->id,
					'title' => $product->currency->title
				]] : null;
				break;
				case 'brand_id':
				$formattedProduct['brand'] = $product->brand ? [[
					'id' => $product->brand->id,
					'name' => $product->brand->name
				]] : null;
				break;
				case 'store_id':
				$formattedProduct['store'] = $product->store ? [[
					'id' => $product->store->id,
					'name' => $product->store->name
				]] : null;
				break;

				case 'shipping_length_id':
				$formattedProduct['shipping_length'] = [
					'selected' => optional($product->shippingLengthUnit)->symbol, /* Selected unit symbol */
					'values' => [
						'cm' => 'cm',
						'in' => 'in',
						'mm' => 'mm'
					]
				];
				break;


				case 'weight_unit_id':
				$formattedProduct['weight_unit'] = [
					'selected' => optional($product->weightUnit)->symbol,
					'values' => [
						'kg' => 'Kilograms',
						'lbs' => 'Pounds',
						'grams' => 'Grams'
					]
				];
				break;
				case 'weight_unit_id':
				$formattedProduct['weight_unit'] = [
					'selected' => optional($product->weightUnit)->symbol,
					'values' => [
						'kg' => 'Kilograms',
						'lbs' => 'Pounds',
						'grams' => 'Grams'
					]
				];
				break;
				case 'length_unit_id':
				$formattedProduct['length_unit'] = [
					'selected' => optional($product->lengthUnit)->symbol,
					'values' => [
						'mm' => 'mm',
						'cm' => 'cm',
						'inch' => 'inch'
					]
				];
				break;
				break;
				case 'categories':
				$formattedProduct['categories'] = $product->categories ? $product->categories->map(function ($category) {
					return [
						'id' => $category->id,
						'name' => $category->name,
						'parent_id' => $category->parent_id
					];
				}) : [];
				break;


				break;
				case 'content':
				/* Extract <li> items from the content and remove HTML tags */
				preg_match_all('/<li>(.*?)<\/li>/', $value, $matches);
				$formattedProduct[$attribute] = $matches[1] ?? [];
				break;


				case 'frequently_bought_together':
				/* Ensure $value is a valid JSON string */
				$decoded = json_decode($value, true);

				if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
					$formattedProduct[$attribute] = []; /* Default to an empty array if decoding fails */
				} else {
					/* Get all product IDs from the frequently bought together items */
					$productIds = array_map(function($item) {
						/* Check if the item is an array and contains 'value' or it's a comma-separated string */
						return is_array($item) ? ($item['value'] ?? null) : $item;
					}, $decoded);

					/* Flatten any possible comma-separated IDs into an array of individual IDs */
					$productIds = array_merge(...array_map(function($id) {
						return explode(',', $id);  /* Split comma-separated values */
					}, $productIds));

					/* Filter out null or empty values */
					$productIds = array_filter($productIds, function($id) {
						return !empty($id); /* Ensure we only have non-empty IDs */
					});

					/* If we have product IDs, fetch their SKUs from the Product model */
					$productSkus = [];
					if (!empty($productIds)) {
						/* Query the Product model to get SKUs for these product IDs */
						$products = \App\Models\Product::whereIn('id', $productIds)
						->select('id', 'sku')
						->get()
						->keyBy('id');

						/* Create a mapping of product ID to SKU */
						foreach ($products as $product) {
							$productSkus[$product->id] = $product->sku;
						}
					}

					/* Now map the original items, but include SKUs as key-value pairs (ID => SKU) */
					$formattedProduct[$attribute] = array_map(function($item) use ($productSkus) {
						$productIds = is_array($item) ? ($item['value'] ?? null) : $item;
						/* Split the IDs, fetch the SKUs, and return them as an array of key-value pairs (ID => SKU) */
						$ids = explode(',', $productIds);
						$skus = array_map(function($id) use ($productSkus) {
							return $productSkus[$id] ?? null;
						}, $ids);

						/* Return a flat array with 'id' => ID and 'sku' => SKU */
						return array_map(function($id, $sku) {
							return ['id' => $id, 'sku' => $sku]; /* Pair each ID with its SKU */
						}, $ids, $skus);
					}, $decoded);

					/* Flatten the nested arrays (if any) and merge them into one array */
					$formattedProduct[$attribute] = array_merge(...$formattedProduct[$attribute]);
				}
				break;


				case 'compare_type':
				$decoded = json_decode($value, true);
				$decoded = is_array($decoded) ? $decoded : []; /* Ensure it's an array */
				$formattedProduct[$attribute] = array_map(fn($item) => ['value' => trim($item)], $decoded);
				break;

				case 'compare_products':
				$decoded = json_decode($value, true);
				$decoded = is_array($decoded) ? $decoded : []; /* Ensure it's an array */
				$formattedProduct[$attribute] = array_map(fn($item) => ['value' => trim($item)], $decoded);
				break;

				case 'images':
				case 'video_path':
				case 'documents':
				$formattedProduct[$attribute] = is_array($value) ? $value : [];
				break;

				case 'status':
				$formattedProduct[$attribute] = [['value' => $value]];
				break;

				case 'unit_of_measurement_id':
				$formattedProduct['unit_of_measurement'] = $product->unitOfMeasurement ? [
					'id' => $product->unitOfMeasurement->id,
					'name' => $product->unitOfMeasurement->name
				] : null;
				break;



				default:
				$formattedProduct[$attribute] = $value;
				break;
			}
		}

		return response()->json([
			'success' => true,
			'message' => 'Product detail',
			'product' => $formattedProduct,
			'categories_hierarchy' => $formattedCategories,
			'admin_reviews' => $adminReviews,
			'faq' => $faqs ?? [],

		]);
	}

	/**
	 * @OA\Post(
	 *     path="/api/products/{product}",
	 *     summary="Update a product using POST with _method=PUT",
	 *     description="Updates an existing product based on the provided form data using POST with _method=PUT. Can also create or update a product review within the same request.",
	 *     operationId="updateProductPost",
	 *     tags={"Products"},
	 *     @OA\Parameter(
	 *         name="product",
	 *         in="path",
	 *         description="ID of the product to update",
	 *         required=true,
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="multipart/form-data",
	 *             @OA\Schema(
	 *                 @OA\Property(property="_method", type="string", example="PUT"),
	 *                 @OA\Property(property="sku", type="string", example="PROD-123"),
	 *                 @OA\Property(property="barcode", type="string", example="9509297558375"),
	 *                 @OA\Property(property="warranty_information", type="string", example="One Year Warranty"),
	 *                 @OA\Property(property="refund", type="string", example="1"),
	 *				   @OA\Property(
	*					property="categories",
	*					type="array",
	*					@OA\Items(type="integer", example=1),
	*					description="Array of category IDs (can include parent and child)"
	*					),
	*                 @OA\Property(property="quantity", type="integer", example=100),
	*                 @OA\Property(property="allow_checkout_when_out_of_stock", type="boolean", example=false),
	*                 @OA\Property(property="status", type="string", example="draft"),
	*                 @OA\Property(property="with_storehouse_management", type="boolean", example=true),
	*                 @OA\Property(property="stock_status", type="string", example="1"),
	*                 @OA\Property(property="variant_inventory_tracker", type="string", example="shopify"),
	*                 @OA\Property(property="variant_inventory_quantity", type="integer", example=50),
	*                 @OA\Property(property="variant_inventory_policy", type="string", example="deny"),
	*                 @OA\Property(property="variant_fulfillment_service", type="string", example="manual"),
	*                 @OA\Property(property="price", type="number", format="float", example=199.99),
	*                 @OA\Property(property="sale_price", type="number", format="float", example=149.99),
	*                 @OA\Property(property="unit_of_measurement_id", type="integer", example=1, description="ID of the unit of measurement from the UnitOfMeasurement table"),
	*                 @OA\Property(property="sale_type", type="string", example="percentage"),
	*                 @OA\Property(property="cost_per_item", type="number", format="float", example=50.00),
	*                 @OA\Property(property="cost_per_item_currency", type="string", example="USD", description="Currency of the cost per item"),
	*                 @OA\Property(
	*                     property="cost_type",
	*                     type="string",
	*                     enum={"percentage", "value"},
	*                     example="percentage",
	*                     description="Defines how additional cost is calculated"
	*                 ),
	*                 @OA\Property(property="additional_cost_percentage", type="number", format="float", example=10.0, description="Percentage to add to the base cost_per_item"),
	*                 @OA\Property(property="additional_cost_value", type="number", format="float", example=5.0, description="Fixed value to add to cost_per_item"),
	*                 @OA\Property(property="total_cost_per_item", type="number", format="float", example=55.0, description="Automatically calculated total cost per item"),
	*                 @OA\Property(property="tax_id", type="integer", example=3),
	*                 @OA\Property(property="currency_id", type="integer", example=1),
	*                 @OA\Property(property="minimum_order_quantity", type="integer", example=1),
	*                 @OA\Property(property="maximum_order_quantity", type="integer", example=10),
	*                 @OA\Property(property="name", type="string", example="Sample Product"),
	*                 @OA\Property(property="content", type="string", example="Detailed content about the product."),
	*                 @OA\Property(property="description", type="string", example="Short description."),
	*                    @OA\Property(
	*                 property="benefits_features",
	*                 type="array",
	*                 @OA\Items(
	*                  type="object",
	*                  @OA\Property(property="benifit", type="string", example="Fast shipping"),
	*                  @OA\Property(property="description", type="string", example="Get your order delivered within 24 hours.")
	*            			  )
	* 					),
	*                 @OA\Property(property="images[]", type="array", @OA\Items(type="string", format="binary")),
	*                 @OA\Property(property="image", type="string", format="binary"),
	*                 @OA\Property(property="video_url", type="string", example="https://www.youtube.com/watch?v=xyz"),
	*                 @OA\Property(property="video_path[]", type="array", @OA\Items(type="string", format="binary")),
	*                 @OA\Property(property="documents[]", type="array", @OA\Items(type="string", format="binary")),
	*                 @OA\Property(property="length", type="number", format="float", example=10.5),
	*                 @OA\Property(property="length_unit_id", type="integer", example=1),
	*                 @OA\Property(property="width", type="number", format="float", example=5.0),
	*                 @OA\Property(property="height", type="number", format="float", example=3.0),
	*                 @OA\Property(property="depth", type="number", format="float", example=2.0),
	*                 @OA\Property(property="weight", type="number", format="float", example=1.5),
	*                 @OA\Property(property="weight_unit_id", type="integer", example=5),
	*                 @OA\Property(property="shipping_weight_option", type="string", example="lbs"),
	*                 @OA\Property(property="shipping_weight", type="number", format="float", example=2.0),
	*                 @OA\Property(property="shipping_dimension_option", type="string", example="inch"),
	*                 @OA\Property(property="shipping_width", type="number", format="float", example=6.0),
	*                 @OA\Property(property="shipping_depth", type="number", format="float", example=4.0),
	*                 @OA\Property(property="shipping_height", type="number", format="float", example=3.5),
	*                 @OA\Property(property="shipping_length", type="number", format="float", example=11.0),
	*                 @OA\Property(property="shipping_length_id", type="integer", example=1),
	*                 @OA\Property(property="is_variation", type="boolean", example=false),
	*                 @OA\Property(property="variant_grams", type="number", format="float", example=500),
	*                 @OA\Property(property="variant_requires_shipping", type="boolean", example=true),
	*                 @OA\Property(property="variant_barcode", type="string", example="123456789012"),
	*                 @OA\Property(property="variant_color_title", type="string", example="Red"),
	*                 @OA\Property(property="variant_color_value", type="string", example="#FF0000"),
	*                 @OA\Property(property="store_id", type="integer", example=7),
	*                 @OA\Property(property="brand_id", type="integer", example=13),
	*                 @OA\Property(property="views", type="integer", example=200),
	*                 @OA\Property(property="units_sold", type="integer", example=50),
	*                 @OA\Property(property="frequently_bought_together[]", type="array", @OA\Items(type="integer", example=101)),
	*                 @OA\Property(property="compare_type", type="string", example=""),
	*                 @OA\Property(property="compare_products[]", type="array", @OA\Items(type="integer", example=102)),
	*                 @OA\Property(property="google_shopping_category", type="string", example="Electronics"),
	*                 @OA\Property(property="google_shopping_mpn", type="string", example="123-ABC"),
	*                 @OA\Property(property="order", type="integer", example=1),
	*                 @OA\Property(property="box_quantity", type="integer", example=5),
	*                 @OA\Property(property="delivery_days", type="integer", example=3),
	*
	*                  @OA\Property(
	*                     property="review_customer_name",
	*                     type="string",
	*                     example="John Doe",
	*                     description="Name of the customer leaving the review"
	*                 ),
	*                 @OA\Property(
	*                     property="review_customer_email",
	*                     type="string",
	*                     format="email",
	*                     example="john.doe@example.com",
	*                     description="Email of the customer leaving the review"
	*                 ),
	*                 @OA\Property(
	*                     property="review_star",
	*                     type="integer",
	*                     minimum=1,
	*                     maximum=5,
	*                     example=5,
	*                     description="Star rating given by the customer"
	*                 ),
	*                 @OA\Property(
	*                     property="review_comment",
	*                     type="string",
	*                     example="Great product, highly recommended!",
	*                     description="Review comment provided by the customer"
	*                 ),
	*                 @OA\Property(
	*                     property="review_status",
	*                     type="string",
	*                     enum={"pending", "published", "rejected"},
	*                     example="pending",
	*                     description="Status of the review"
	*                 ),
	*                 @OA\Property(
	*                     property="review_images[]",
	*                     type="array",
	*                     @OA\Items(type="string", format="binary"),
	*                     description="Review images to upload"
	*                 ),
	* 				  @OA\Property(
	*                     property="faqs",
	*                     type="array",
	*                     @OA\Items(
	*                         @OA\Property(property="question", type="string", example="What is the warranty period?"),
	*                         @OA\Property(property="answer", type="string", example="The warranty period is 1 year."),
	*                         @OA\Property(property="category_id", type="integer", nullable=true, example=2),
	*                         @OA\Property(property="status", type="integer", example=1)
	*                     )
	*                 ),
	* 				  @OA\Property(
	* 				      property="product_attributes",
	* 				      type="object",
	* 				      description="Dynamic attributes with attribute_id as key",
	* 				      @OA\AdditionalProperties(
	* 				          type="string",
	* 				          description="Attribute value corresponding to the attribute_id"
	* 				      ),
	* 				      example={
	* 				          "1": "1111",
	* 				          "4": "tanuj",
	* 				          "5": "raaj",
	* 				          "11": "ahmad"
	* 				      },
	* 				      nullable=true
	* 				  )
	*             )
	*         )
	*     ),

	*     @OA\Response(
	*         response=201,
	*         description="Review created successfully",
	*         @OA\JsonContent(
	*             type="object",
	*             @OA\Property(property="success", type="boolean", example=true),
	*             @OA\Property(property="message", type="string", example="Review created successfully."),
	*             @OA\Property(property="review", type="object",
	*                 @OA\Property(property="id", type="integer", example=1),
	*                 @OA\Property(property="customer_id", type="integer", example=1),
	*                 @OA\Property(property="customer_name", type="string", example="John Doe"),
	*                 @OA\Property(property="customer_email", type="string", example="john.doe@example.com"),
	*                 @OA\Property(property="product_id", type="integer", example=5),
	*                 @OA\Property(property="star", type="integer", example=5),
	*                 @OA\Property(property="comment", type="string", example="Great product, highly recommended!"),
	*                 @OA\Property(property="status", type="string", example="pending"),
	*                 @OA\Property(property="images", type="array", @OA\Items(type="string")),
	*                 @OA\Property(property="created_at", type="string", format="date-time"),
	*                 @OA\Property(property="updated_at", type="string", format="date-time"),
	* 				   @OA\Property(property="faqs", type="array",
	*                 @OA\Items(
	*                     @OA\Property(property="question", type="string"),
	*                     @OA\Property(property="answer", type="string"),
	*                     @OA\Property(property="category_id", type="integer", nullable=true),
	*                     @OA\Property(property="status", type="integer")
	*                 )
	*             )
	*             )
	*         )
	*     ),
	*     @OA\Response(
	*         response=200,
	*         description="Review updated successfully",
	*         @OA\JsonContent(
	*             type="object",
	*             @OA\Property(property="success", type="boolean", example=true),
	*             @OA\Property(property="message", type="string", example="Review updated successfully."),
	*             @OA\Property(property="review", type="object",
	*                 @OA\Property(property="id", type="integer", example=1),
	*                 @OA\Property(property="customer_id", type="integer", example=1),
	*                 @OA\Property(property="customer_name", type="string", example="John Doe"),
	*                 @OA\Property(property="customer_email", type="string", example="john.doe@example.com"),
	*                 @OA\Property(property="product_id", type="integer", example=5),
	*                 @OA\Property(property="star", type="integer", example=5),
	*                 @OA\Property(property="comment", type="string", example="Great product, highly recommended!"),
	*                 @OA\Property(property="status", type="string", example="published"),
	*                 @OA\Property(property="images", type="array", @OA\Items(type="string")),
	*                 @OA\Property(property="created_at", type="string", format="date-time"),
	*                 @OA\Property(property="updated_at", type="string", format="date-time")
	*             )
	*         )
	*     ),

	*     @OA\Response(
	*         response=400,
	*         description="Validation Error",
	*         @OA\JsonContent(
	*             type="object",
	*             @OA\Property(property="success", type="boolean", example=false),
	*             @OA\Property(property="message", type="array", @OA\Items(type="string"), example={
	*                 "Refund policy should be numeric and either 1 for Non-Refundable, 2 for 15 Days Refund, or 3 for 90 Days Refund.",
	*                 "Stock status should be numeric and either 1 for In Stock, 2 for Out of Stock, or 3 for On Backorder.",
	*                 "Invalid length unit value. Valid values are 1 (cm), 3 (inch), or 11 (mm).",
	*                 "Invalid weight unit value. Valid values are 5 (kg), 6 (g), or 9 (lbs).",
	*                 "Invalid shipping length value. Valid values are 1 (cm), 3 (inch), or 11 (mm).",
	*                 "Invalid store value. Valid store IDs are: 1, 7, 8, 16, 17, ... 60",
	*                 "Invalid brand value. Valid brand IDs are: 13, 14, 18, 19, ... 60"
	*             })
	*         )
	*     ),
	*     security={{"bearerAuth":{}}}
	* )
	*/
	public function update(Request $request, $productId)
	{
		/* Log the incoming request for debugging */
		\Log::info('Product update request:', $request->all());
		$unitOfMeasurements = UnitOfMeasurement::all(['id', 'name']);

		$product = Product::find($productId);

		if (!$product) {
			return response()->json([
				'success' => false,
				'message' => 'Product does not exist.'
			]);
		}

		/* Handle categories - IMPROVED VERSION */
		if ($request->has('categories')) {
			// Log incoming data for debugging
			\Log::info('Categories input:', [
				'raw' => $request->input('categories'),
				'type' => gettype($request->input('categories'))
			]);
			
			$categories = $request->input('categories');
			
			// Handle cases where categories might be sent as a JSON string
			if (is_string($categories) && (
				strpos($categories, '[') === 0 || 
				strpos($categories, '{') === 0
			)) {
				$categories = json_decode($categories, true);
			} 
			// Handle comma-separated string format
			else if (is_string($categories) && strpos($categories, ',') !== false) {
				$categories = array_map('trim', explode(',', $categories));
			}
			// Handle single value
			else if (is_string($categories) && is_numeric($categories)) {
				$categories = [(int)$categories];
			}
			
			// Ensure we have a valid array
			if (is_array($categories)) {
				// Convert all values to integers to ensure proper comparison
				$categories = array_map('intval', array_filter($categories));
				$product->categories()->sync($categories);
			} else {
				return response()->json([
					'success' => false,
					'message' => 'Categories must be provided as a valid array of category IDs.'
				], 400);
			}
		}

		if ($request->product_attributes) {
			$productAttributes = json_decode($request->product_attributes, true);

			if (is_array($productAttributes) && count($productAttributes) > 0) {
				$productAttributes = array_filter($productAttributes, function ($value) {
					return !is_null($value) && $value !== '';
				});

				$existingProductAttributes = $product->productAttributes->pluck('attribute_value', 'attribute_id')->toArray();

				$attributesToDelete = array_diff(array_keys($existingProductAttributes), array_keys($productAttributes));

				if (!empty($attributesToDelete)) {
					$product->productAttributes()->whereIn('attribute_id', $attributesToDelete)->delete();
				}

				foreach ($productAttributes as $attributeId => $attributeValue) {
					$existingAttribute = Attribute::find($attributeId);

					if (!$existingAttribute) {
						return response()->json([
							'success' => false,
							'message' => "Attribute ID: $attributeId does not exist."
						]);
					}

					$product->productAttributes()->updateOrCreate(
						['attribute_id' => $attributeId],
						['attribute_value' => $attributeValue]
					);

					if ($existingAttribute->attributeValues()->where('attribute_value', $attributeValue)->doesntExist()) {
						$existingAttribute->attributeValues()->create([
							'attribute_id' => $attributeId,
							'attribute_value' => $attributeValue
						]);
					}
				}
			}
		}

		$faqs = $request->input('faqs', []); /* Default to an empty array if not provided */

		/* Check if faqs is a string and decode it properly */
		if (is_string($faqs)) {
			$decoded = json_decode($faqs, true);

			/* Handle invalid JSON */
			if (json_last_error() !== JSON_ERROR_NONE) {
				return response()->json([
					'success' => false,
					'message' => 'Invalid JSON format for faqs.'
				], 400);
			}

			/* Ensure we extract faqs correctly */
			$faqs = is_array($decoded) && isset($decoded[0]) ? $decoded : ($decoded['faqs'] ?? []);
		}

		/* Validate that faqs is an array */
		if (!is_array($faqs)) {
			return response()->json([
				'success' => false,
				'message' => 'The field faqs must be a valid JSON array.'
			], 400);
		}

		/* Process and store FAQs */
		foreach ($faqs as $faqData) {
			if (!empty($faqData['question']) && !empty($faqData['answer'])) {
				Faq::updateOrCreate(
					[
						'product_id' => $product->id,
						'question' => $faqData['question'],
					],
					[
						'answer' => $faqData['answer'],
						'category_id' => $faqData['category_id'] ?? null,
						'status' => $faqData['status'] == 1 ? 'published' : 'draft' /* Map status */
					]
				);
			}
		}

		if ($request->hasAny(['review_customer_email', 'review_customer_name', 'review_comment', 'review_status', 'review_star', 'review_images'])) {

			/* ✅ Check if a review already exists for this customer & product */
			$review = Review::where('product_id', $product->id)
			->where('customer_email', $request->input('review_customer_email'))
			->first();

			if (!$review) {
				/* ✅ No existing review, create a new one */
				$review = new Review();
				$review->product_id = $product->id;
				$review->customer_id = $request->input('customer_id');
				$review->customer_email = $request->input('review_customer_email');
				$review->customer_name = $request->input('review_customer_name');
			}

			/* ✅ Update fields (applies to both new & existing reviews) */
			$review->comment = $request->input('review_comment');
			$review->status = $request->input('review_status', 'pending');
			$review->star = $request->input('review_star', null);

			/* ✅ Handle review images upload */
			if ($request->hasFile('review_images')) {
				$uploadedReviewImages = [];
				foreach ($request->file('review_images') as $image) {
					$path = $image->store('production/reviews', 's3');
					$uploadedReviewImages[] = Storage::disk('s3')->url($path);
				}
				$review->images = $uploadedReviewImages; /* Store as an array */
			}

			$review->save(); /* ✅ Save either as new or updated review */
		}

		/* Get all input data except '_method' */
		$input = $request->except('_method');
		/* Remove 'faqs' from the input before validation */

		/* Process the new fields if they exist in the request */
		if ($request->has('cost_per_item')) {
			$input['cost_per_item'] = $request->input('cost_per_item');
		}

		if ($request->has('cost_per_item_currency')) {
			$input['cost_per_item_currency'] = $request->input('cost_per_item_currency');
		}

		if ($request->has('cost_type')) {
			$input['cost_type'] = $request->input('cost_type');
		}

		if ($request->has('additional_cost_percentage')) {
			$input['additional_cost_percentage'] = $request->input('additional_cost_percentage');
		}

		if ($request->has('additional_cost_value')) {
			$input['additional_cost_value'] = $request->input('additional_cost_value');
		}

		/* Calculate the total cost if it's not already provided */
		if ($request->has('cost_per_item') && ($request->has('additional_cost_percentage') || $request->has('additional_cost_value'))) {
			if ($input['cost_type'] === 'percentage' && $request->has('additional_cost_percentage')) {
				$input['total_cost_per_item'] = $input['cost_per_item'] + ($input['cost_per_item'] * $input['additional_cost_percentage'] / 100);
			} elseif ($input['cost_type'] === 'value' && $request->has('additional_cost_value')) {
				$input['total_cost_per_item'] = $input['cost_per_item'] + $input['additional_cost_value'];
			}
		}
		/* ✅ Remove review-related fields before validation */
		$reviewFields = ['review_customer_email', 'review_customer_name', 'review_comment', 'review_status', 'review_star', 'review_images'];
		foreach ($reviewFields as $field) {
			unset($input[$field]);
		}

		$fieldsToUnset = ['faqs', 'categories']; /* Added categories to fields to unset */

		foreach ($fieldsToUnset as $field) {
			unset($input[$field]);
		}

		$imagePath = 'production/products';
		$videoPath = 'production/videos';
		$documentPath = 'production/documents';
		$reviewImagePath = 'production/reviews';

		/* ✅ Handle Single Image Upload */
		if ($request->hasFile('image')) {
			$path = $request->file('image')->store($imagePath, 's3');
			$input['image'] = Storage::disk('s3')->url($path); /* ✅ Full S3 URL */
		}
		$existingImages = is_array($product->images) ? $product->images : json_decode($product->images, true);
		$existingImages = is_array($existingImages) ? $existingImages : []; /* Ensure it's an array */

		if ($request->hasFile('images')) {
			$uploadedImages = [];
			foreach ($request->file('images') as $image) {
				$path = $image->store($imagePath, 's3');
				$uploadedImages[] = Storage::disk('s3')->url($path);
			}

			/* Merge old and new images */
			$input['images'] = array_merge($existingImages, $uploadedImages);
		} else {
			/* Keep existing images if no new images are uploaded */
			$input['images'] = $existingImages;
		}

		/* Convert to JSON with unescaped slashes before saving */
		$input['images'] = json_encode($input['images'], JSON_UNESCAPED_SLASHES);

		/* Handle video upload */
		$existingVideos = is_array($product->video_path) ? $product->video_path : json_decode($product->video_path, true);
		$existingVideos = is_array($existingVideos) ? $existingVideos : [];

		if ($request->hasFile('video_path')) {
			$uploadedVideos = [];
			foreach ($request->file('video_path') as $video) {
				$path = $video->store($videoPath, 's3');
				$uploadedVideos[] = Storage::disk('s3')->url($path);
			}

			/* Merge with existing videos */
			$input['video_path'] = array_merge($existingVideos, $uploadedVideos);
		} else {
			/* Retain existing videos if no new files are uploaded */
			$input['video_path'] = $existingVideos;
		}

		/* Convert to JSON with unescaped slashes */
		$input['video_path'] = json_encode($input['video_path'], JSON_UNESCAPED_SLASHES);

		/* Handle document upload */
		$existingDocs = is_array($product->documents) ? $product->documents : json_decode($product->documents, true);
		$existingDocs = is_array($existingDocs) ? $existingDocs : [];

		if ($request->hasFile('documents')) {
			$uploadedDocs = [];
			foreach ($request->file('documents') as $doc) {
				$path = $doc->store($documentPath, 's3');

				/* Check if the title is provided, if not, use the document's name */
				$title = $request->input('title', $doc->getClientOriginalName()); /* default to original name if title is empty */

				/* If title is still empty, use the document name as title */
				if (empty($title)) {
					$title = basename($doc->getClientOriginalName());  /* Use document name if title is empty */
				}

				/* Create an array with title and path for each uploaded document */
				$uploadedDocs[] = [
					'title' => $title,
					'path' => Storage::disk('s3')->url($path)
				];
			}

			/* Merge with existing documents */
			$input['documents'] = array_merge($existingDocs, $uploadedDocs);
		} else {
			/* Retain existing documents if no new files are uploaded */
			$input['documents'] = $existingDocs;
		}

		/* Convert to JSON with unescaped slashes */
		$input['documents'] = json_encode($input['documents'], JSON_UNESCAPED_SLASHES);




		$input['allow_checkout_when_out_of_stock'] = filter_var($request->input('allow_checkout_when_out_of_stock'), FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
		$input['with_storehouse_management'] = filter_var($request->input('with_storehouse_management'), FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
		$input['is_variation'] = filter_var($request->input('is_variation'), FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
		$input['variant_requires_shipping'] = filter_var($request->input('variant_requires_shipping'), FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
		$input['sale_type'] = $request->input('sale_type') === 'percentage' ? 1 : 0;


		/* List of valid fields allowed for updating */
		$validArray = [
			"sku", "status", "barcode", "warranty_information", "refund", "quantity",
			"allow_checkout_when_out_of_stock", "with_storehouse_management",
			"stock_status", "variant_inventory_tracker", "variant_inventory_quantity",
			"variant_inventory_policy", "variant_fulfillment_service", "price",
			"sale_price", "sale_type", "cost_per_item", "cost_per_item_currency",
			"cost_type", "additional_cost_percentage", "additional_cost_value",
			"total_cost_per_item", "tax_id", "currency_id", "minimum_order_quantity",
			"maximum_order_quantity", "name", "content", "description", "images",
			"image", "video_url", "video_path", "videos", "documents", "length",
			"length_unit_id", "width", "height", "depth", "weight", "weight_unit_id",
			"shipping_weight_option", "shipping_weight", "shipping_dimension_option",
			"shipping_width", "shipping_depth", "shipping_height", "shipping_length",
			"shipping_length_id", "is_variation", "variant_grams", "variant_requires_shipping",
			"variant_barcode", "variant_color_title", "variant_color_value", "store_id",
			"brand_id", "views", "units_sold", "frequently_bought_together", "compare_type",
			"compare_products", "google_shopping_category", "google_shopping_mpn", "order",
			"box_quantity", "delivery_days", "unit_of_measurement_id", "benefits_features"
		];

		unset($input['product_attributes']);

		/* Check for invalid fields */
		$invalidFields = array_diff(array_keys($input), $validArray);
		if (!empty($invalidFields)) {
			return response()->json([
				'success' => false,
				'message' => 'The field' . (count($invalidFields) > 1 ? 's' : '') . ' ' . implode(', ', $invalidFields) . ' ' . (count($invalidFields) > 1 ? 'are' : 'is') . ' not valid.'
			]);
		}

		/* Initialize an error array to store validation errors */
		$rowError = [];

		/* Refund policy validation */
		$usRefundPolicyArray = [
			1 => "non-refundable",
			2 => "15 days",
			3 => "90 days"
		];
		if (isset($input['refund'])) {
			if (!is_numeric($input['refund']) || !array_key_exists((int) $input['refund'], $usRefundPolicyArray)) {
				$rowError[] = "Refund policy should be numeric and either 1 for Non-Refundable, 2 for 15 Days Refund, or 3 for 90 Days Refund.";
			} else {
				$product->refund = $usRefundPolicyArray[(int) $input['refund']];
				unset($input['refund']); /* Remove processed field */
			}
		}

		if (isset($input['status'])) {
			$validStatuses = ['draft', 'published', 'pending']; /* Define allowed statuses */

			if (!in_array($input['status'], $validStatuses)) {
				return response()->json([
					'success' => false,
					'message' => 'Invalid status value. Allowed values: draft, published, archived.'
				]);
			}

			$product->status = $input['status']; /* Assign status */
		}

		if (isset($input['unit_of_measurement_id'])) {
			/* Fetch all valid unit IDs from the database */
			$validUnitIds = UnitOfMeasurement::pluck('id')->toArray();

			if (!is_numeric($input['unit_of_measurement_id']) || !in_array((int) $input['unit_of_measurement_id'], $validUnitIds)) {
				return response()->json([
					'success' => false,
					'message' => 'Invalid unit_of_measurement_id. Please provide a valid ID from the UnitOfMeasurement table.'
				]);
			}

			$product->unit_of_measurement_id = $input['unit_of_measurement_id']; /* Assign the valid ID */
		}

		/* Decode existing benefits_features if available */
		$existingBenefits = json_decode($product->benefits_features, true);

		/* Ensure existingBenefits is an array */
		if (!is_array($existingBenefits)) {
			$existingBenefits = [];
		}

		/* Decode incoming request JSON */
		$newBenefits = json_decode($request->input('benefits_features'), true);

		/* Ensure newBenefits is an array */
		if (!is_array($newBenefits)) {
			return response()->json([
				'success' => false,
				'message' => 'Invalid benefits_features format.'
			], 400);
		}

		/* Merge existing benefits with new ones */
		$mergedBenefits = array_merge($existingBenefits, $newBenefits);

		/* Save back as JSON */
		$product->benefits_features = json_encode($mergedBenefits, JSON_UNESCAPED_SLASHES);


		/* Stock status validation */
		$usStockStatusArray = [
			1 => "in_stock",
			2 => "out_of_stock",
			3 => "Pre Order"
		];
		if (isset($input['stock_status'])) {
			if (!is_numeric($input['stock_status']) || !array_key_exists((int) $input['stock_status'], $usStockStatusArray)) {
				$rowError[] = "Stock status should be numeric and either 1 for In Stock, 2 for Out of Stock, or 3 for On Backorder.";
			} else {
				$product->stock_status = $usStockStatusArray[(int) $input['stock_status']];
				unset($input['stock_status']); /* Remove processed field */
			}
		}

		/* Tax ID validation */
		if (isset($input['tax_id'])) {
			$taxArray = Tax::pluck("id")->toArray();
			if (!is_numeric($input['tax_id']) || !in_array((int) $input['tax_id'], $taxArray)) {
				$rowError[] = "Invalid tax value. Please select a valid tax ID.";
			} else {
				$product->tax_id = (int) $input['tax_id'];
				unset($input['tax_id']); /* Remove processed field */
			}
		}

		/* Currency ID validation */
		if (isset($input['currency_id'])) {
			$currencyArray = Currency::pluck("id")->toArray();
			if (!is_numeric($input['currency_id']) || !in_array((int) $input['currency_id'], $currencyArray)) {
				$rowError[] = "Invalid currency value. Please select a valid currency ID.";
			} else {
				$product->currency_id = (int) $input['currency_id'];
				unset($input['currency_id']); /* Remove processed field */
			}
		}

		/* Unit ID validation for length, weight, and shipping */
		$lengthUnitArray = [
			1 => "cm",
			3 => "inch",
			11 => "mm",
		];
		$weightUnitArray = [
			5 => "kg",
			6 => "g",
			9 => "lbs",
		];

		if (isset($input['length_unit_id'])) {
			if (!is_numeric($input['length_unit_id']) || !array_key_exists((int) $input['length_unit_id'], $lengthUnitArray)) {
				$rowError[] = "Invalid length unit value. Valid values are 1 (cm), 3 (inch), or 11 (mm).";
			} else {
				$product->length_unit_id = (int) $input['length_unit_id'];
				unset($input['length_unit_id']); /* Remove processed field */
			}
		}

		if (isset($input['weight_unit_id'])) {
			if (!is_numeric($input['weight_unit_id']) || !array_key_exists((int) $input['weight_unit_id'], $weightUnitArray)) {
				$rowError[] = "Invalid weight unit value. Valid values are 5 (kg), 6 (g), or 9 (lbs).";
			} else {
				$product->weight_unit_id = (int) $input['weight_unit_id'];
				unset($input['weight_unit_id']); /* Remove processed field */
			}
		}

		if (isset($input['shipping_length_id'])) {
			if (!is_numeric($input['shipping_length_id']) || !array_key_exists((int) $input['shipping_length_id'], $lengthUnitArray)) {
				$rowError[] = "Invalid shipping length value. Valid values are 1 (cm), 3 (inch), or 11 (mm).";
			} else {
				$product->shipping_length_id = (int) $input['shipping_length_id'];
				unset($input['shipping_length_id']); /* Remove processed field */
			}
		}

		if (isset($input['google_shopping_category'])) {
			$product->google_shopping_category = $input['google_shopping_category'];
			unset($input['google_shopping_category']);
		}

		if (isset($input['google_shopping_mpn'])) {
			$product->google_shopping_mpn = $input['google_shopping_mpn'];
			unset($input['google_shopping_mpn']);
		}

		if (isset($input['box_quantity'])) {
			/* If box_quantity should be an integer */
			$product->box_quantity = (int)$input['box_quantity'];
			unset($input['box_quantity']);
		}

		/* Store ID validation */
		if (isset($input['store_id'])) {
			$storeArray = Store::pluck("id")->toArray();
			if (!is_numeric($input['store_id']) || !in_array((int) $input['store_id'], $storeArray)) {
				$storeList = implode(', ', $storeArray);
				$rowError[] = "Invalid store value. Valid store IDs are: " . $storeList;
			} else {
				$product->store_id = (int) $input['store_id'];
				unset($input['store_id']); /* Remove processed field */
			}
		}

		/* Brand ID validation */
		if (isset($input['brand_id'])) {
			$brandArray = Brand::pluck("id")->toArray();
			if (!is_numeric($input['brand_id']) || !in_array((int) $input['brand_id'], $brandArray)) {
				$brandList = implode(', ', $brandArray);
				$rowError[] = "Invalid brand value. Valid brand IDs are: " . $brandList;
			} else {
				$product->brand_id = (int) $input['brand_id'];
				unset($input['brand_id']); /* Remove processed field */
			}
		}

		/* If any validation errors exist, return them */
		if (!empty($rowError)) {
			return response()->json([
				'success' => false,
				'message' => $rowError
			]);
		}

		/* Assign remaining valid fields to the product */
		foreach ($input as $key => $value) {
			$product->$key = $value;
		}


		if ($request->has('review')) {
			$reviewInput = $request->input('review');

			/* Ensure required fields exist */
			if (empty($reviewInput['comment'])) {
				return response()->json([
					'success' => false,
					'message' => 'Review comment is required.',
				]);
			}

			/* Ensure product exists */
			if (!$product) {
				return response()->json([
					'success' => false,
					'message' => 'Product not found.',
				]);
			}

			/* Ensure a valid customer_id is used */
			$customerId =  1; /* Default to 1 if not logged in */

			/* Create new review */
			$review = new Review();
			$review->product_id = $product->id;
			$review->customer_id = $customerId;
			$review->customer_name = $reviewInput['customer_name'] ?? 'Guest';
			$review->customer_email = $reviewInput['customer_email'] ?? null;
			$review->star = isset($reviewInput['star']) ? (int) $reviewInput['star'] : null;
			$review->comment = $reviewInput['comment'];
			$review->status = 'pending'; /* Set a default status if needed */

			if ($review->save()) {
				/* Handle review images (if any) */
				if ($request->hasFile('review_images')) {
					foreach ($request->file('review_images') as $image) {
						$path = $image->store('reviews', 'public');

						ReviewImage::create([
							'review_id' => $review->id,
							'image_path' => $path,
						]);
					}
				}

				return response()->json([
					'success' => true,
					'message' => 'Review saved successfully.',
				]);
			} else {
				\Log::error('Failed to save review:', $review->toArray());
				return response()->json(['success' => false, 'message' => 'Failed to save review.']);
			}
		}

		/* Save the product */
		$product->save();

		$product = Product::find($product->id);


		/* Return success response */
		return response()->json([
			'success' => true,
			'message' => 'Product updated successfully.',
			'product' => $product->load('productAttributes:id,product_id,attribute_id,attribute_value'),
			'unitOfMeasurements' => $unitOfMeasurements ,
			'review' => $review ?? null,
			'faq' => $faqs ?? null,
		]);
	}

	/**
	 * @OA\Get(
	 *     path="/api/products/product-input",
	 *     summary="Get product input fields",
	 *     description="Fetches all available product input fields.",
	 *     operationId="getProductInputs",
	 *     tags={"Products"},
	 *     @OA\Response(
	 *         response=201,
	 *         description="Success",
	 *          @OA\MediaType(
	 *              mediaType="application/json",
	 *          )
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function getProductInputs()
	{
		$refund['type'] = 'Dropdown';
		$refund['values'] = [
			"1" => "Non-Refundable",
			"2" => "15 Days Refund",
			"3" => "90 Days Refund"
		];

		$allowCheckoutWhenOutOfStock['type'] = 'checkbox';
		$allowCheckoutWhenOutOfStock['values'] = [
			"0" => "unchecked",
			"1" => "checked",
		];

		$withStorehouseManagement['type'] = 'checkbox';
		$withStorehouseManagement['values'] = [
			"0" => "unchecked",
			"1" => "checked",
		];

		$stockStatus['type'] = 'Dropdown';
		$stockStatus['values'] = [
			"1" => "In Stock",
			"2" => "Out of Stock",
			"3" => "Pre Order"
		];

		$tax['type'] = 'Dropdown';
		$tax['values'] = Tax::pluck("title", "id")->all();

		$currency['type'] = 'Dropdown';
		$currency['values'] = Currency::pluck("title", "id")->all();

		$lengthUnit['type'] = 'Dropdown';
		$lengthUnit['values'] = [
			1 => "cm",
			3 => "inch",
			11 => "mm",
		];

		$weightUnit['type'] = 'Dropdown';
		$weightUnit['values'] = [
			5 => "kg",
			6 => "g",
			9 => "lbs",
		];

		$shippingWeightOption['type'] = 'Dropdown';
		$shippingWeightOption['values'] = [
			"lbs" => "LBS",
			"kg" => "KG",
			"g" => "Grams",
		];

		$shippingDimensionOption['type'] = 'Dropdown';
		$shippingDimensionOption['values'] = [
			"inch" => "Inch",
			"cm" => "CM",
			"mm" => "MM",
		];

		$shippingLength['type'] = 'Dropdown';
		$shippingLength['values'] = [
			1 => "cm",
			3 => "inch",
			11 => "mm",
		];

		$isVariation['type'] = 'checkbox';
		$isVariation['values'] = [
			"0" => "unchecked",
			"1" => "checked",
		];

		$variantRequiresShipping['type'] = 'checkbox';
		$variantRequiresShipping['values'] = [
			"0" => "unchecked",
			"1" => "checked",
		];

		$store['type'] = 'Dropdown';
		$store['values'] = Store::pluck("name", "id")->all();

		$brand['type'] = 'Dropdown';
		$brand['values'] = Brand::pluck("name", "id")->all();

		$allKeyword = [
			"sku",
			"barcode",
			"warranty_information",
			"refund",
			"quantity",
			"allow_checkout_when_out_of_stock",
			"with_storehouse_management",
			"stock_status",
			"variant_inventory_tracker",
			"variant_inventory_quantity",
			"variant_inventory_policy",
			"variant_fulfillment_service",
			"price",
			"sale_price",
			"sale_type",
			"cost_per_item",
			"tax_id",
			"currency_id",
			"minimum_order_quantity",
			"maximum_order_quantity",
			"name",
			"content",
			"description",
			"images",
			"image",
			"video_url",
			"video_path",
			"documents",
			"length",
			"length_unit_id",
			"width",
			"height",
			"depth",
			"weight",
			"weight_unit_id",
			"shipping_weight_option",
			"shipping_weight",
			"shipping_dimension_option",
			"shipping_width",
			"shipping_depth",
			"shipping_height",
			"shipping_length",
			"shipping_length_id",
			"is_variation",
			"variant_grams",
			"variant_requires_shipping",
			"variant_barcode",
			"variant_color_title",
			"variant_color_value",
			"store_id",
			"brand_id",
			"views",
			"units_sold",
			"frequently_bought_together",
			"compare_type",
			"compare_products",
			"google_shopping_category",
			"google_shopping_mpn",
			"order",
			"box_quantity",
			"delivery_days",
			/* "created_by_id", */
			/* "created_by_type", */
		];
		$keywordType = [
			"refund" => $refund,
			"allow_checkout_when_out_of_stock" => $allowCheckoutWhenOutOfStock,
			"with_storehouse_management" => $withStorehouseManagement,
			"stock_status" => $stockStatus,
			"tax" => $tax,
			"currency" => $currency,
			"length_unit" => $lengthUnit,
			"weight_unit" => $weightUnit,
			"shipping_weight_option" => $shippingWeightOption,
			"shipping_dimension_option" => $shippingDimensionOption,
			"shipping_length" => $shippingLength,
			"is_variation" => $isVariation,
			"variant_requires_shipping" => $variantRequiresShipping,
			"store" => $store,
			"brand" => $brand,
		];

		return response()->json([
			'success' => true,
			'message' => 'Product keyword detail',
			'all_keyword' => $allKeyword,
			'keyword_type' => $keywordType,
		], 201, [], JSON_FORCE_OBJECT);
	}

	/**
	 * @OA\Delete(
	 *     path="/api/products/{product}",
	 *     summary="Delete a product",
	 *     description="Deletes a specific product by its ID.",
	 *     tags={"Products"},
	 *     @OA\Parameter(
	 *         name="product",
	 *         in="path",
	 *         description="ID of the product to delete",
	 *         required=true,
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Product deleted successfully",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="message", type="string", example="Product deleted successfully.")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=500,
	 *         description="Failed to delete product",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=false),
	 *             @OA\Property(property="message", type="string", example="Failed to delete product."),
	 *             @OA\Property(property="error", type="string", example="Error details...")
	 *         )
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function destroy(Product $product)
	{
		try {
			$product->delete();

			return response()->json([
				'success' => true,
				'message' => 'Product deleted successfully.',
			], 200);
		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Failed to delete product.',
				'error' => $e->getMessage(),
			], 500);
		}
	}

	/**
	 * @OA\Get(
	 *     path="/api/products/{productId}/product-category-attribute-groups",
	 *     summary="Get product category attribute groups list",
	 *     description="Retrieve attribute groups of the latest category for a given product.",
	 *     tags={"Products"},
	 *     @OA\Parameter(
	 *         name="productId",
	 *         in="path",
	 *         description="Product ID",
	 *         required=true,
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function productCategoryAttributeGroups($productId)
	{
		$product = Product::find($productId);
		if (!$product) {
			return response()->json([
				'success' => false,
				'message' => 'Product does not exist.'
			], 404);
		}

		$category = $product->latestChildCategory();
		if (!$category) {
			return response()->json([
				'success' => false,
				'message' => 'No category found for this product.'
			], 404);
		}

		$productAttributes = $product->productAttributes->pluck('attribute_value', 'attribute_id');

		$attributeGroup = $category->categoryAttributeGroups()
		->with(['groupsAttributes'])
		->get()
		->map(function ($group) use ($productAttributes) {
			return [
				'id' => $group->id,
				'name' => $group->name,
				'group_attributes' => $group->groupsAttributes->map(function ($attribute) use ($productAttributes) {
					return [
						'id' => $attribute->id,
						'name' => $attribute->name,
						'code' => $attribute->code,
						'type' => $attribute->type,
						'validations' => json_decode($attribute->validations, true),
						'attributeValues' => $attribute->attributeValues->pluck('attribute_value')->values()->all(), /* Reset array keys */
						'currentValue' => $productAttributes[$attribute->id] ?? null,
					];
				})->toArray(),
			];
		});

		return response()->json([
			'success' => true,
			'message' => 'Product category attribute groups',
			'data' => $attributeGroup
		]);
	}

	/**
	 * @OA\Post(
	 *     path="/api/products/import",
	 *     summary="Import products from an Excel file",
	 *     tags={"Products"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="multipart/form-data",
	 *             @OA\Schema(
	 *                 required={"upload_file"},
	 *                 @OA\Property(property="upload_file", type="string", format="binary", description="CSV file (.csv) max 5MB"),
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function import(Request $request, CsvImporterService $csvImporter)
	{
		/* Validate request data */
		$request->validate([
			'upload_file' => 'required|file|mimes:csv,txt|max:5120',
		]);

		try {
			$productFileFormatArray = [];
			$idArray = [
				'Id' => 'id',
			];

			$urlArray = [
				'URL' => 'url',
			];

			$generalFieldArray = [
				'Name' => 'name',
				'SKU' => 'sku',
				'Brand' => 'brand',
				'Categories' => 'category',
			];

			$descriptionSectionArray = [
				'Description1' => 'description1',
				'Description2' => 'description2',
				'Description3' => 'description3',
				'Description4' => 'description4',
			];

			$benefitSectionArray = [
				'Benefit1' => 'benefit1',
				'Feature1' => 'feature1',
				'Benefit2' => 'benefit2',
				'Feature2' => 'feature2',
				'Benefit3' => 'benefit3',
				'Feature3' => 'feature3',
				'Benefit4' => 'benefit4',
				'Feature4' => 'feature4',
				'Benefit5' => 'benefit5',
				'Feature5' => 'feature5',
				'Benefit6' => 'benefit6',
				'Feature6' => 'feature6',
				'Benefit7' => 'benefit7',
				'Feature7' => 'feature7',
				'Benefit8' => 'benefit8',
				'Feature8' => 'feature8',
				'Benefit9' => 'benefit9',
				'Feature9' => 'feature9',
				'Benefit10' => 'benefit10',
				'Feature10' => 'feature10',
			];

			$faqSectionArray = [
				"FAQ Question1" => "faq_question1",
				"FAQ Answer1" => "faq_answer1",
				"FAQ Question2" => "faq_question2",
				"FAQ Answer2" => "faq_answer2",
				"FAQ Question3" => "faq_question3",
				"FAQ Answer3" => "faq_answer3",
				"FAQ Question4" => "faq_question4",
				"FAQ Answer4" => "faq_answer4",
				"FAQ Question5" => "faq_question5",
				"FAQ Answer5" => "faq_answer5",
				"FAQ Question6" => "faq_question6",
				"FAQ Answer6" => "faq_answer6",
				"FAQ Question7" => "faq_question7",
				"FAQ Answer7" => "faq_answer7",
				"FAQ Question8" => "faq_question8",
				"FAQ Answer8" => "faq_answer8",
				"FAQ Question9" => "faq_question9",
				"FAQ Answer9" => "faq_answer9",
				"FAQ Question10" => "faq_question10",
				"FAQ Answer10" => "faq_answer10",
			];


			$advanceFieldArray = [
				'Warranty Information' => 'warrantyInformation',
				'Vendor' => 'vendor',
				'Tags' => 'tags',
				'Stock Status' => 'stockStatus',
				'With Storehouse Management' => 'withStorehouseManagement',
				'Quantity' => 'quantity',
				'Cost Per Item' => 'costPerItem',
				'Unit of Measurement' => 'unitOfMeasurement',
				'Price' => 'price',
				'Sale Price' => 'salePrice',
				'Start Date Sale Price' => 'startDateSalePrice',
				'End Date Sale Price' => 'endDateSalePrice',
				'Minimum Order Quantity' => 'minimumOrderQuantity',
				'Box Quantity' => 'boxQuantity',
				'Delivery Days' => 'deliveryDays',
				'Variant Requires Shipping' => 'variantRequiresShipping',
				'Images' => 'images',
				'Upload Video' => 'uploadVideo',
				'Barcode (ISBN, UPC, GTIN, etc.)' => 'barcode',
				'Refund Policy' => 'refundPolicy',
				'Status' => 'status',
				'Google Shopping Category' => 'googleShoppingCategory',
				'Google Shopping Mpn' => 'googleShoppingMpn',
				'Is Featured' => 'isFeatured',
				'Weight Option' => 'weightOption',
				'Weight' => 'weight',
				'Dimension Option' => 'dimensionOption',
				'Length' => 'length',
				'Width' => 'width',
				'Height' => 'height',
				'Depth' => 'depth',
				'Shipping Weight Option' => 'shippingWeightOption',
				'Shipping Weight' => 'shippingWeight',
				'Shipping Dimension Option' => 'shippingDimensionOption',
				'Shipping Width' => 'shippingWidth',
				'Shipping Depth' => 'shippingDepth',
				'Shipping Height' => 'shippingHeight',
				'Shipping Length' => 'shippingLength',
				'Frequently Bought Together' => 'frequentlyBoughtTogether',
				'Compare Products' => 'compareProducts',
				'Variant 1 Title' => 'variant1Title',
				'Variant 1 Value' => 'variant1Value',
				'Variant 1 Products' => 'variant1Products',
				'Variant 2 Title' => 'variant2Title',
				'Variant 2 Value' => 'variant2Value',
				'Variant 2 Products' => 'variant2Products',
				'Variant 3 Title' => 'variant3Title',
				'Variant 3 Value' => 'variant3Value',
				'Variant 3 Products' => 'variant3Products',
				'Variant Color Title' => 'variantColorTitle',
				'Variant Color Value' => 'variantColorValue',
				'Variant Color Products' => 'variantColorProducts',
			];

			$seoSection = [
				"Meta Title" => "meta_title",
				"Meta Description" => "meta_description",
			];


			$discountSectionArray = [
				'Buying Quantity1' => 'buyingQuantity1',
				'Discount1' => 'discount1',
				'Start Date1' => 'startDate1',
				'End Date1' => 'endDate1',
				'Buying Quantity2' => 'buyingQuantity2',
				'Discount2' => 'discount2',
				'Start Date2' => 'startDate2',
				'End Date2' => 'endDate2',
				'Buying Quantity3' => 'buyingQuantity3',
				'Discount3' => 'discount3',
				'Start Date3' => 'startDate3',
				'End Date3' => 'endDate3',
			];

			$translationSectionArray = [
				'Name (AR)' => 'nameAr',
				'Description (AR)' => 'descriptionAr',
				'Content (AR)' => 'contentAr',
				'Warranty Information (AR)' => 'warrantyInformationAr',
			];

			$userRole = auth()->user()->getRoleNames()->first() ?? null;

			if (empty($userRole) || !in_array($userRole, ['Content Writing Manager', 'Content Writer'])) {
				$productFileFormatArray = array_merge(
					$idArray,
					$urlArray,
					$generalFieldArray,
					$descriptionSectionArray,
					$benefitSectionArray,
					$faqSectionArray,
					$advanceFieldArray,
					$seoSection,
					$discountSectionArray,
					$translationSectionArray
				);
			} elseif (in_array($userRole, ['Content Writing Manager', 'Content Writer'])) {
				$productFileFormatArray = array_merge(
					$idArray,
					$generalFieldArray,
					$descriptionSectionArray,
					$benefitSectionArray,
					$faqSectionArray,
					$seoSection,
				);
			}

			$csvImporter->processImport(
				$request->file('upload_file')->getRealPath(),
				$productFileFormatArray,
				'Product',
				'JOB1',
				'Product Import',
				\App\Jobs\ImportProductJob::class,
				$userRole
			);

			return response()->json([
				'success' => true,
				'message' => 'The import process has been scheduled successfully. Please track it under import log.'
			]);
		} catch(\Exception $exception) {
			$error[] = 'Error: ' . $exception->getMessage();
			$error[] = 'File: ' . $exception->getFile();
			$error[] = 'Line: ' . $exception->getLine();
			return response()->json([
				'success' => false,
				'message' => $error
			]);
		}
	}

	/**
	 * @OA\Get(
	 *     path="/api/products/category/{category_id}",
	 *     summary="Get list of products by category",
	 *     description="Retrieves a list of products from a specific category.",
	 *     tags={"Products"},
	 *     @OA\Parameter(
	 *         name="category_id",
	 *         in="path",
	 *         description="ID of the category to filter products by",
	 *         required=true,
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Successful response",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="message", type="string", example="Products retrieved successfully"),
	 *             @OA\Property(
	 *                 property="data",
	 *                 type="array",
	 *                 @OA\Items(
	 *                     @OA\Property(property="id", type="integer", example=1),
	 *                     @OA\Property(property="name", type="string", example="Sample Product"),
	 *                     @OA\Property(property="sku", type="string", example="PROD-123"),
	 *                     @OA\Property(property="image", type="string", example="http://example.com/storage/products/sample.jpg")
	 *                 )
	 *             )
	 *         )
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function getProductsByCategory($category_id)
	{
		/* Fetch all products related to the given category ID */
		$products = Product::whereHas('categories', function ($query) use ($category_id) {
			$query->where('category_id', $category_id);
		})
		->select(['id', 'name', 'sku', 'images'])
		->orderBy('id', 'desc') /* Order by product ID in descending order */
		->get();

		/* Formatting the response to include only id, name, sku, and image */
		$formattedProducts = $products->map(function ($product)use ($category_id) {
			return [
				'id' => $product->id,
				'name' => $product->name,
				'sku' => $product->sku,
				'image' => ($imageUrls = json_decode($product->images, true)) && isset($imageUrls[0]) ? $imageUrls[0] : null,
				'category_id' => $category_id,

			];
		});

		/* Return the list of products without pagination */
		return response()->json([
			'success' => true,
			'message' => 'Products retrieved successfully for category ' . $category_id,
			'data' => $formattedProducts
		]);
	}

	/**
	 * @OA\Get(
	 *     path="/api/products/filtered-category/{category_ids}",
	 *     summary="Get filtered products by multiple categories",
	 *     description="Retrieves products from the specified categories based on the category_id JSON field in brand_temp_2 table.",
	 *     tags={"Products"},
	 *     @OA\Parameter(
	 *         name="category_ids",
	 *         in="path",
	 *         description="IDs of the categories to filter products by (comma-separated)",
	 *         required=true,
	 *         @OA\Schema(type="string", example="180,529")
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Successful response",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="message", type="string", example="Filtered products retrieved successfully"),
	 *             @OA\Property(
	 *                 property="data",
	 *                 type="array",
	 *                 @OA\Items(
	 *                     @OA\Property(property="id", type="integer", example=1683),
	 *                     @OA\Property(property="name", type="string", example="Commercial Electric Range"),
	 *                     @OA\Property(property="sku", type="string", example="ELEC-RANGE-001"),
	 *                     @OA\Property(property="image", type="string", example="http://example.com/storage/products/elec-range.jpg"),
	 *                     @OA\Property(property="category_id", type="integer", example=529)
	 *                 )
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=404,
	 *         description="No products found",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=false),
	 *             @OA\Property(property="message", type="string", example="No products found for the given categories")
	 *         )
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function getFilteredProductsByCategorybd1($category_ids)
	{
		/* Convert comma-separated string to array of integers */
		$categoryIdArray = array_map('intval', explode(',', $category_ids));

		if (empty($categoryIdArray)) {
			return response()->json([
				'success' => false,
				'message' => 'No valid category IDs provided.',
				'data' => []
			]);
		}

		/* Get data from brand_temp_2 table */
		$brandData = DB::table('brand_temp_1')->get();

		if ($brandData->isEmpty()) {
			return response()->json([
				'success' => false,
				'message' => 'No data found in brand_temp_1.',
				'data' => []
			]);
		}

		/* Initialize array to store product IDs */
		$allProductIds = [];
		$productCategoryMap = [];
		$categoryResults = [];

		/* Initialize category results for each requested category */
		foreach ($categoryIdArray as $categoryId) {
			$categoryResults[$categoryId] = [];
		}

		/* Loop through each record in brand_temp_2 */
		foreach ($brandData as $record) {
			/* Decode the category_id JSON field */
			$categoryData = json_decode($record->category_id, true);

			if (!is_array($categoryData)) {
				continue;
			}

			/* Look for matching categories in the JSON data */
			foreach ($categoryData as $category) {
				if (isset($category['category_id']) && in_array($category['category_id'], $categoryIdArray)) {
					$categoryId = $category['category_id'];

					/* If this category matches one of our requested categories */
					if (isset($category['product_ids']) && is_array($category['product_ids'])) {
						foreach ($category['product_ids'] as $productId) {
							$allProductIds[] = $productId;
							$productCategoryMap[$productId] = $categoryId;
							$categoryResults[$categoryId][] = $productId;
						}
					}
				}
			}
		}

		/* Remove duplicate product IDs */
		$allProductIds = array_unique($allProductIds);

		/* Even if some categories have no products, we still want to return products from categories that do have products */
		if (empty($allProductIds)) {
			return response()->json([
				'success' => false,
				'message' => 'No products found for any of the given categories.',
				'data' => []
			]);
		}

		/* Fetch products from the database */
		$products = Product::whereIn('id', $allProductIds)
		->select(['id', 'name', 'sku', 'images'])
		->orderBy('id', 'desc')
		->get();

		/* Format product data */
		$formattedProducts = $products->map(function ($product) use ($productCategoryMap) {
			return [
				'id' => $product->id,
				'name' => $product->name,
				'sku' => $product->sku,
				'image' => ($imageUrls = json_decode($product->images, true)) && isset($imageUrls[0]) ? $imageUrls[0] : null,
				'category_id' => $productCategoryMap[$product->id] ?? null,
			];
		});

		/* Summary of what was found for each category */
		$categorySummary = [];
		foreach ($categoryIdArray as $categoryId) {
			$categorySummary[] = [
				'category_id' => $categoryId,
				'product_count' => count($categoryResults[$categoryId])
			];
		}

		return response()->json([
			'success' => true,
			'message' => 'Products retrieved successfully for categories: ' . $category_ids,
			'category_summary' => $categorySummary,
			'data' => $formattedProducts
		]);
	}

	public function getFilteredProductsByCategory($category_ids)
	{
		/* Convert comma-separated string to array of integers */
		$categoryIdArray = array_map('intval', explode(',', $category_ids));

		if (empty($categoryIdArray)) {
			return response()->json([
				'success' => false,
				'message' => 'No valid category IDs provided.',
				'data' => []
			]);
		}

		/* Get data from brand_temp_2 table */
		$brandData = DB::table('brand_temp_2')->get();

		if ($brandData->isEmpty()) {
			return response()->json([
				'success' => false,
				'message' => 'No data found in brand_temp_2.',
				'data' => []
			]);
		}

		/* Initialize array to store product IDs */
		$allProductIds = [];
		$productCategoryMap = [];
		$categoryResults = [];

		/* Initialize category results for each requested category */
		foreach ($categoryIdArray as $categoryId) {
			$categoryResults[$categoryId] = [];
		}

		/* Loop through each record in brand_temp_2 */
		foreach ($brandData as $record) {
			/* Decode the category_id JSON field */
			$categoryData = json_decode($record->category_id, true);

			if (!is_array($categoryData)) {
				continue;
			}

			/* Look for matching categories in the JSON data */
			foreach ($categoryData as $category) {
				if (isset($category['category_id']) && in_array($category['category_id'], $categoryIdArray)) {
					$categoryId = $category['category_id'];

					/* If this category matches one of our requested categories */
					if (isset($category['product_ids']) && is_array($category['product_ids'])) {
						foreach ($category['product_ids'] as $productId) {
							$allProductIds[] = $productId;
							$productCategoryMap[$productId] = $categoryId;
							$categoryResults[$categoryId][] = $productId;
						}
					}
				}
			}
		}

		/* Remove duplicate product IDs */
		$allProductIds = array_unique($allProductIds);

		/* Even if some categories have no products, we still want to return products from categories that do have products */
		if (empty($allProductIds)) {
			return response()->json([
				'success' => false,
				'message' => 'No products found for any of the given categories.',
				'data' => []
			]);
		}

		/* Fetch products from the database */
		$products = Product::whereIn('id', $allProductIds)
		->select(['id', 'name', 'sku', 'images'])
		->orderBy('id', 'desc')
		->get();

		/* Format product data */
		$formattedProducts = $products->map(function ($product) use ($productCategoryMap) {
			return [
				'id' => $product->id,
				'name' => $product->name,
				'sku' => $product->sku,
				'image' => ($imageUrls = json_decode($product->images, true)) && isset($imageUrls[0]) ? $imageUrls[0] : null,
				'category_id' => $productCategoryMap[$product->id] ?? null,
			];
		});

		/* Summary of what was found for each category */
		$categorySummary = [];
		foreach ($categoryIdArray as $categoryId) {
			$categorySummary[] = [
				'category_id' => $categoryId,
				'product_count' => count($categoryResults[$categoryId])
			];
		}

		return response()->json([
			'success' => true,
			'message' => 'Products retrieved successfully for categories: ' . $category_ids,
			'category_summary' => $categorySummary,
			'data' => $formattedProducts
		]);
	}


	public function getFilteredProductsByCategorybd3($category_ids)
	{
		/* Convert comma-separated string to array of integers */
		$categoryIdArray = array_map('intval', explode(',', $category_ids));

		if (empty($categoryIdArray)) {
			return response()->json([
				'success' => false,
				'message' => 'No valid category IDs provided.',
				'data' => []
			]);
		}

		/* Get data from brand_temp_2 table */
		$brandData = DB::table('brand_temp_3')->get();

		if ($brandData->isEmpty()) {
			return response()->json([
				'success' => false,
				'message' => 'No data found in brand_temp_3.',
				'data' => []
			]);
		}

		/* Initialize array to store product IDs */
		$allProductIds = [];
		$productCategoryMap = [];
		$categoryResults = [];

		/* Initialize category results for each requested category */
		foreach ($categoryIdArray as $categoryId) {
			$categoryResults[$categoryId] = [];
		}

		/* Loop through each record in brand_temp_2 */
		foreach ($brandData as $record) {
			/* Decode the category_id JSON field */
			$categoryData = json_decode($record->category_id, true);

			if (!is_array($categoryData)) {
				continue;
			}

			/* Look for matching categories in the JSON data */
			foreach ($categoryData as $category) {
				if (isset($category['category_id']) && in_array($category['category_id'], $categoryIdArray)) {
					$categoryId = $category['category_id'];

					/* If this category matches one of our requested categories */
					if (isset($category['product_ids']) && is_array($category['product_ids'])) {
						foreach ($category['product_ids'] as $productId) {
							$allProductIds[] = $productId;
							$productCategoryMap[$productId] = $categoryId;
							$categoryResults[$categoryId][] = $productId;
						}
					}
				}
			}
		}

		/* Remove duplicate product IDs */
		$allProductIds = array_unique($allProductIds);

		/* Even if some categories have no products, we still want to return products from categories that do have products */
		if (empty($allProductIds)) {
			return response()->json([
				'success' => false,
				'message' => 'No products found for any of the given categories.',
				'data' => []
			]);
		}

		/* Fetch products from the database */
		$products = Product::whereIn('id', $allProductIds)
		->select(['id', 'name', 'sku', 'images'])
		->orderBy('id', 'desc')
		->get();

		/* Format product data */
		$formattedProducts = $products->map(function ($product) use ($productCategoryMap) {
			return [
				'id' => $product->id,
				'name' => $product->name,
				'sku' => $product->sku,
				'image' => ($imageUrls = json_decode($product->images, true)) && isset($imageUrls[0]) ? $imageUrls[0] : null,
				'category_id' => $productCategoryMap[$product->id] ?? null,
			];
		});

		/* Summary of what was found for each category */
		$categorySummary = [];
		foreach ($categoryIdArray as $categoryId) {
			$categorySummary[] = [
				'category_id' => $categoryId,
				'product_count' => count($categoryResults[$categoryId])
			];
		}

		return response()->json([
			'success' => true,
			'message' => 'Products retrieved successfully for categories: ' . $category_ids,
			'category_summary' => $categorySummary,
			'data' => $formattedProducts
		]);
	}
}