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
use App\Models\Vendor;
use App\Models\Review;
use App\Models\Brand;
use App\Models\Slug;
use App\Models\TransactionLog;
use App\Models\Faq;
use App\Models\Attribute;
use App\Models\UnitOfMeasurement;
use Illuminate\Support\Facades\DB;
use App\Jobs\ImportProductJob;
use App\Services\ExcelImporterService;


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
	 *     @OA\Parameter(
	 *         name="sort_by",
	 *         in="query",
	 *         description="Column to sort by (id, name, sku, brand_id, vendor_id, status)",
	 *         required=false,
	 *         @OA\Schema(type="string", example="id")
	 *     ),
	 *     @OA\Parameter(
	 *         name="sort_direction",
	 *         in="query",
	 *         description="Sort direction (asc or desc)",
	 *         required=false,
	 *         @OA\Schema(type="string", enum={"asc", "desc"}, example="desc")
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
		$status = $request->input('status');
		$sortBy = $request->input('sort_by', 'id');
		$sortDirection = $request->input('sort_direction', 'desc');

		// Validate sort columns to prevent SQL injection
		$allowedSortColumns = ['id', 'name', 'sku', 'brand_id', 'vendor_id', 'status' , 'price' , 'sale_price' , 'gen_type'];
		if (!in_array($sortBy, $allowedSortColumns)) {
			$sortBy = 'id'; // Default to id if invalid column
		}

		// Validate sort direction
		if (!in_array(strtolower($sortDirection), ['asc', 'desc'])) {
			$sortDirection = 'desc'; // Default to descending if invalid direction
		}

		$query = Product::with([
			'brand:id,name',
			'vendor:id,name',
			'categories:id,name',
			'slug:id,key,reference_id'
		])
		->select(['id', 'name', 'sku', 'images', 'brand_id', 'vendor_id', 'status' , 'price' , 'sale_price' , 'gen_type']);

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
				->orWhereHas('vendor', function($storeQuery) use ($search) {
					$storeQuery->where('name', 'like', "%{$search}%");
				})
				->orWhereHas('categories', function($categoryQuery) use ($search) {
					$categoryQuery->where('name', 'like', "%{$search}%");
				});
			});
		}

		$products = $query->orderBy($sortBy, $sortDirection)
		->paginate($perPage);


		/* Formatting response */
		$formattedProducts = $products->map(function ($product) {
			$margin = $product->sale_price - $product->price;
			$marginPercent = $product->sale_price > 0
			? (($product->sale_price - $product->price) / $product->sale_price) * 100
			: 0;
			return [
				'id' => $product->id,
				'name' => $product->name,
				'gen_type' => $product->gen_type,
				'sku' => $product->sku,
				'image' => ($imageUrls = json_decode($product->images, true)) && isset($imageUrls[0]) ? $imageUrls[0] : null,
				'brand' => optional($product->brand)->name,
				'vendor_id' => $product->vendor_id,
				'vendor' => optional($product->vendor)->name,
				'status' => $product->status,
				'price'=> $product->price,
				'sale_price'=> $product->sale_price,
				'margin' => $margin,
				'margin_percent' => round($marginPercent, 2), // round to 2 decimals
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
		// $product->created_by_id = auth()->id();
		// $product->created_by_type = User::class;
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

			'Inventory & Stock Management' => ['quantity', 'stock_status'],
			'Pricing & Sales' => ['price', 'sale_price', 'cost_per_item', 'tax_id', 'currency_id', 'approved_by', 'cost_per_item_currency'],
			'Marketing' => ['name', 'description', 'gen_type'],
			'Media' => ['images', 'video_path', 'documents' , 'benefits_features'],
			'Product Variations' => ['is_variation', 'variant_requires_shipping', 'variant_color_title', 'variant_color_value'],
			'Store & Vendor Information' => ['vendor_id', 'brand_id'],
			'Performance & Analytics' => ['views', 'units_sold', 'frequently_bought_together'],
			'SEO' => ['google_shopping_category', 'google_shopping_mpn'],
			'Other' => ['order', 'box_quantity', 'delivery_days' , 'website_ids'],
			'All' => []
		];

		$attributeGroup['All'] = array_merge(...array_values(array_filter($attributeGroup, fn($key) => $key !== 'All', ARRAY_FILTER_USE_KEY)));

		$relations = [
			'General' => ['categories:id,name,parent_id'],
			'Pricing & Sales' => ['currency:id,title' ,'unitOfMeasurement:id,name'],
			'Shipping & Dimensions' => ['lengthUnit:id,symbol', 'weightUnit:id,symbol', 'shippingLengthUnit:id,symbol'],
			'Store & Vendor Information' => ['vendor:id,name', 'brand:id,name', 'creator:id,name'],
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
				// case 'allow_checkout_when_out_of_stock':
				// case 'with_storehouse_management':
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

				// case 'shipping_weight_option':
				// $formattedProduct[$attribute] = [
				// 	'type' => 'Dropdown',
				// 	'selected' => $value,
				// 	'values' => [
				// 		'lbs' => 'LBS',
				// 		'kg' => 'KG',
				// 		'g' => 'Grams'
				// 	]
				// ];
				// break;

				// case 'shipping_dimension_option':
				// $formattedProduct[$attribute] = [
				// 	'type' => 'Dropdown',
				// 	'selected' => $value,
				// 	'values' => [
				// 		'inch' => 'Inch',
				// 		'cm' => 'CM',
				// 		'mm' => 'MM'
				// 	]
				// ];
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
				case 'vendor_id':
				$formattedProduct['vendor'] = $product->vendor ? [[
					'id' => $product->vendor->id,
					'name' => $product->vendor->name
				]] : null;
				break;

				// case 'shipping_length_id':
				// $formattedProduct['shipping_length'] = [
				// 	'selected' => optional($product->shippingLengthUnit)->symbol, /* Selected unit symbol */
				// 	'values' => [
				// 		'cm' => 'cm',
				// 		'in' => 'in',
				// 		'mm' => 'mm'
				// 	]
				// ];
				// break;


				// case 'weight_unit_id':
				// $formattedProduct['weight_unit'] = [
				// 	'selected' => optional($product->weightUnit)->symbol,
				// 	'values' => [
				// 		'kg' => 'Kilograms',
				// 		'lbs' => 'Pounds',
				// 		'grams' => 'Grams'
				// 	]
				// ];
				// break;
				// case 'weight_unit_id':
				// $formattedProduct['weight_unit'] = [
				// 	'selected' => optional($product->weightUnit)->symbol,
				// 	'values' => [
				// 		'kg' => 'Kilograms',
				// 		'lbs' => 'Pounds',
				// 		'grams' => 'Grams'
				// 	]
				// ];
				// break;
				// case 'length_unit_id':
				// $formattedProduct['length_unit'] = [
				// 	'selected' => optional($product->lengthUnit)->symbol,
				// 	'values' => [
				// 		'mm' => 'mm',
				// 		'cm' => 'cm',
				// 		'inch' => 'inch'
				// 	]
				// ];
				// break;
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
				// case 'content':
				// /* Extract <li> items from the content and remove HTML tags */
				// preg_match_all('/<li>(.*?)<\/li>/', $value, $matches);
				// $formattedProduct[$attribute] = $matches[1] ?? [];
				// break;

				case 'description':
					$decodedDescription = json_decode($value, true); // Decode JSON string to array
					// Send as array if valid, else send raw string
					$formattedProduct['description'] = is_array($decodedDescription) ? $decodedDescription : [$value];
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


				// case 'compare_type':
				// $decoded = json_decode($value, true);
				// $decoded = is_array($decoded) ? $decoded : []; /* Ensure it's an array */
				// $formattedProduct[$attribute] = array_map(fn($item) => ['value' => trim($item)], $decoded);
				// break;

				// case 'compare_products':
				// $decoded = json_decode($value, true);
				// $decoded = is_array($decoded) ? $decoded : []; /* Ensure it's an array */
				// $formattedProduct[$attribute] = array_map(fn($item) => ['value' => trim($item)], $decoded);
				// break;

					case 'images':
					case 'video_path':
					case 'documents':
					$formattedProduct[$attribute] = is_array($value) ? $value : [];
					break;

					case 'status':
					$formattedProduct[$attribute] = [['value' => $value]];
					break;

				// case 'unit_of_measurement_id':
				// $formattedProduct['unit_of_measurement'] = $product->unitOfMeasurement ? [
				// 	'id' => $product->unitOfMeasurement->id,
				// 	'name' => $product->unitOfMeasurement->name
				// ] : null;
				// break;



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
	*                 @OA\Property(property="status", type="string", example="draft"),
	*                 @OA\Property(property="stock_status", type="string", example="1"),
	*                 @OA\Property(property="price", type="number", format="float", example=199.99),
	*                 @OA\Property(property="sale_price", type="number", format="float", example=149.99),
	*                 @OA\Property(property="cost_per_item", type="number", format="float", example=50.00),
	*                 @OA\Property(property="cost_per_item_currency", type="string", example="USD", description="Currency of the cost per item"),
	*                 @OA\Property(property="tax_id", type="integer", example=3),
	*                 @OA\Property(property="currency_id", type="integer", example=1),
	*                 @OA\Property(property="name", type="string", example="Sample Product"),
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
	*                 @OA\Property(property="video_path[]", type="array", @OA\Items(type="string", format="binary")),
	*                 @OA\Property(property="documents[]", type="array", @OA\Items(type="string", format="binary")),
	*                 @OA\Property(property="is_variation", type="boolean", example=false),
	*                 @OA\Property(property="variant_requires_shipping", type="boolean", example=true),
	*                 @OA\Property(property="variant_color_title", type="string", example="Red"),
	*                 @OA\Property(property="variant_color_value", type="string", example="#FF0000"),
	*                 @OA\Property(property="vendor_id", type="integer", example=7),
	*                 @OA\Property(property="brand_id", type="integer", example=13),
	*                 @OA\Property(property="views", type="integer", example=200),
	*                 @OA\Property(property="units_sold", type="integer", example=50),
	*                 @OA\Property(property="frequently_bought_together[]", type="array", @OA\Items(type="integer", example=101)),
	*                 @OA\Property(property="google_shopping_category", type="string", example="Electronics"),
	*                 @OA\Property(property="google_shopping_mpn", type="string", example="123-ABC"),
	*                 @OA\Property(property="order", type="integer", example=1),
	*                 @OA\Property(property="box_quantity", type="integer", example=5),
	*                 @OA\Property(property="delivery_days", type="integer", example=3),
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
	
	// public function update(Request $request, $productId)
	// {
	// 	/* Log the incoming request for debugging */
	// 	\Log::info('Product update request:', $request->all());
	// 	$unitOfMeasurements = UnitOfMeasurement::all(['id', 'name']);

	// 	$product = Product::find($productId);

	// 	if (!$product) {
	// 		return response()->json([
	// 			'success' => false,
	// 			'message' => 'Product does not exist.'
	// 		]);
	// 	}

	// 	// Get the authenticated user and their role
	// 	$user = auth()->user();
	// 	$userRole = $user ? $user->getRoleNames()->first() : null;
	// 	$allowedRoles = [
	// 		'Super Admin', 
	// 		'Admin', 
	// 		'Graphic Designer Manager'
	// 	];  // Define which roles can modify images
	// 	$canModifyImages = $userRole && in_array($userRole, $allowedRoles);

	// 	$contentAllowedRoles = [
	// 		'Super Admin',
	// 		'Admin',
	// 		'Content Writing Manager',
	// 		'Content Writer'
	// 	];
	// 	$canModifyContent = $userRole && in_array($userRole, $contentAllowedRoles);
	

	// 	/* Handle categories - IMPROVED VERSION */
	// 	if ($request->has('categories')) {
	// 		// Log incoming data for debugging
	// 		\Log::info('Categories input:', [
	// 			'raw' => $request->input('categories'),
	// 			'type' => gettype($request->input('categories'))
	// 		]);

	// 		$categories = $request->input('categories');

	// 		// Handle cases where categories might be sent as a JSON string
	// 		if (is_string($categories) && (
	// 			strpos($categories, '[') === 0 ||
	// 			strpos($categories, '{') === 0
	// 		)) {
	// 			$categories = json_decode($categories, true);
	// 		}
	// 		// Handle comma-separated string format
	// 		else if (is_string($categories) && strpos($categories, ',') !== false) {
	// 			$categories = array_map('trim', explode(',', $categories));
	// 		}
	// 		// Handle single value
	// 		else if (is_string($categories) && is_numeric($categories)) {
	// 			$categories = [(int)$categories];
	// 		}

	// 		// Ensure we have a valid array
	// 		if (is_array($categories)) {
	// 			// Convert all values to integers to ensure proper comparison
	// 			$categories = array_map('intval', array_filter($categories));
	// 			$product->categories()->sync($categories);
	// 		} else {
	// 			return response()->json([
	// 				'success' => false,
	// 				'message' => 'Categories must be provided as a valid array of category IDs.'
	// 			], 400);
	// 		}
	// 	}

	// 	if ($request->product_attributes) {
	// 		$productAttributes = is_array($request->product_attributes) ? $request->product_attributes : json_decode($request->product_attributes, true);

	// 		if (is_array($productAttributes) && count($productAttributes) > 0) {
	// 			$productAttributes = array_filter($productAttributes, function ($value) {
	// 				return !is_null($value) && $value !== '';
	// 			});

	// 			$existingProductAttributes = $product->productAttributes->pluck('attribute_value', 'attribute_id')->toArray();

	// 			$attributesToDelete = array_diff(array_keys($existingProductAttributes), array_keys($productAttributes));

	// 			if (!empty($attributesToDelete)) {
	// 				$product->productAttributes()->whereIn('attribute_id', $attributesToDelete)->delete();
	// 			}

	// 			foreach ($productAttributes as $attributeId => $attributeValue) {
	// 				$existingAttribute = Attribute::find($attributeId);
	// 				if (!$existingAttribute) {
	// 					return response()->json([
	// 						'success' => false,
	// 						'message' => "Attribute ID: $attributeId does not exist."
	// 					]);
	// 				}

	// 				$value = null;
	// 				$measurementUnitID = null;

	// 				if ($existingAttribute->type == 'measurement' && is_array($attributeValue)) {
	// 					$value = $attributeValue['value'] ?? null;
	// 					$measurementUnitID = $attributeValue['measurement_id'] ?? null;

	// 					if (!$value || !$measurementUnitID) {
	// 						/* Delete attribute if either value or measurement ID is missing */
	// 						$product->productAttributes()
	// 						->where('attribute_id', $attributeId)
	// 						->delete();
	// 					} else {
	// 						/* Update or create measurement attribute */
	// 						$product->productAttributes()->updateOrCreate(
	// 							['attribute_id' => $attributeId],
	// 							[
	// 								'attribute_value' => $value,
	// 								'measurement_unit_id' => $measurementUnitID
	// 							]
	// 						);
	// 					}
	// 				} else {
	// 					$value = $attributeValue;

	// 					if (empty($value)) {
	// 						/* Delete non-measurement attribute if empty */
	// 						$product->productAttributes()
	// 						->where('attribute_id', $attributeId)
	// 						->delete();
	// 					} else {
	// 						/* Update or create normal attribute */
	// 						$product->productAttributes()->updateOrCreate(
	// 							['attribute_id' => $attributeId],
	// 							[
	// 								'attribute_value' => $value,
	// 								'measurement_unit_id' => null
	// 							]
	// 						);
	// 					}
	// 				}

	// 				if ($existingAttribute->type === 'select') {
	// 					if ($existingAttribute->attributeValues()->where('attribute_value', $value)->doesntExist()) {
	// 						$existingAttribute->attributeValues()->create([
	// 							'attribute_value' => $value
	// 						]);
	// 					}
	// 				}
	// 			}
	// 		}
	// 	}

		
	// 	$faqs = $request->input('faqs', []); /* Default to an empty array if not provided */

	// 	/* Check if faqs is a string and decode it properly */
	// 	if (is_string($faqs)) {
	// 		$decoded = json_decode($faqs, true);

	// 		/* Handle invalid JSON */
	// 		if (json_last_error() !== JSON_ERROR_NONE) {
	// 			return response()->json([
	// 				'success' => false,
	// 				'message' => 'Invalid JSON format for faqs.'
	// 			], 400);
	// 		}

	// 		/* Ensure we extract faqs correctly */
	// 		$faqs = is_array($decoded) && isset($decoded[0]) ? $decoded : ($decoded['faqs'] ?? []);
	// 	}

	// 	/* Validate that faqs is an array */
	// 	if (!is_array($faqs)) {
	// 		return response()->json([
	// 			'success' => false,
	// 			'message' => 'The field faqs must be a valid JSON array.'
	// 		], 400);
	// 	}

	// 	/* Get all existing FAQ IDs for this product */
	// 	$existingFaqIds = Faq::where('product_id', $product->id)->pluck('id')->toArray();
	// 	$processedFaqIds = [];

	// 	/* Process and store FAQs */
	// 	foreach ($faqs as $faqData) {
	// 		if (!empty($faqData['question']) && !empty($faqData['answer'])) {

	// 			if (!empty($faqData['id'])) {
	// 				/* Update existing FAQ */
	// 				$faq = Faq::where('id', $faqData['id'])
	// 				->where('product_id', $product->id)
	// 				->first();

	// 				if ($faq) {
	// 					$faq->update([
	// 						'question' => $faqData['question'],
	// 						'answer' => $faqData['answer'],
	// 						'category_id' => $faqData['category_id'] ?? null,
	// 						'status' => 'published',
	// 					]);
	// 					$processedFaqIds[] = $faq->id;
	// 				}
	// 			} else {
	// 				/* Create new FAQ */
	// 				$newFaq = Faq::create([
	// 					'product_id' => $product->id,
	// 					'question' => $faqData['question'],
	// 					'answer' => $faqData['answer'],
	// 					'category_id' => $faqData['category_id'] ?? null,
	// 					'status' => 'published',
	// 				]);
	// 				$processedFaqIds[] = $newFaq->id;
	// 			}
	// 		}
	// 	}

	// 	/* Delete FAQs that were not included in the update */
	// 	$faqsToDelete = array_diff($existingFaqIds, $processedFaqIds);
	// 	if (!empty($faqsToDelete)) {
	// 		Faq::where('product_id', $product->id)
	// 		->whereIn('id', $faqsToDelete)
	// 		->delete();
	// 	}
	// 	/* Get all input data except '_method' */
	// 	$input = $request->except('_method');
	// 	/* Remove 'faqs' from the input before validation */

	// 	/* Process the new fields if they exist in the request */
	// 	if ($request->has('cost_per_item')) {
	// 		$input['cost_per_item'] = $request->input('cost_per_item');
	// 	}

	// 	if ($request->has('cost_per_item_currency')) {
	// 		$input['cost_per_item_currency'] = $request->input('cost_per_item_currency');
	// 	}

	// 	if ($request->has('cost_type')) {
	// 		$input['cost_type'] = $request->input('cost_type');
	// 	}

	// 	if ($request->has('additional_cost_percentage')) {
	// 		$input['additional_cost_percentage'] = $request->input('additional_cost_percentage');
	// 	}

	// 	if ($request->has('additional_cost_value')) {
	// 		$input['additional_cost_value'] = $request->input('additional_cost_value');
	// 	}

	// 	/* Calculate the total cost if it's not already provided */
	// 	if ($request->has('cost_per_item') && ($request->has('additional_cost_percentage') || $request->has('additional_cost_value'))) {
	// 		if ($input['cost_type'] === 'percentage' && $request->has('additional_cost_percentage')) {
	// 			$input['total_cost_per_item'] = $input['cost_per_item'] + ($input['cost_per_item'] * $input['additional_cost_percentage'] / 100);
	// 		} elseif ($input['cost_type'] === 'value' && $request->has('additional_cost_value')) {
	// 			$input['total_cost_per_item'] = $input['cost_per_item'] + $input['additional_cost_value'];
	// 		}
	// 	}
	// 	/* ✅ Remove review-related fields before validation */
	// 	$reviewFields = ['review_customer_email', 'review_customer_name', 'review_comment', 'review_status', 'review_star', 'review_images'];
	// 	foreach ($reviewFields as $field) {
	// 		unset($input[$field]);
	// 	}

	// 	$fieldsToUnset = ['faqs', 'categories']; /* Added categories to fields to unset */

	// 	foreach ($fieldsToUnset as $field) {
	// 		unset($input[$field]);
	// 	}

	// 	$imagePath = 'production/products';
	// 	$videoPath = 'production/videos';
	// 	$documentPath = 'production/documents';
	// 	$reviewImagePath = 'production/reviews';

	// 	// Handle images with role-based permission
	// 	if ($request->has('images')) {
	// 		// Check if user is actually trying to modify images (upload new files)
	// 		$hasNewImageFiles = false;
	// 		foreach ($request->images as $key => $image) {
	// 			if ($request->hasFile("images.$key")) {
	// 				$hasNewImageFiles = true;
	// 				break;
	// 			}
	// 		}
			
	// 		// Only check permissions if user is uploading new image files
	// 		if ($hasNewImageFiles && !$canModifyImages) {
	// 			return response()->json([
	// 				'success' => false,
	// 				'message' => 'You do not have permission to modify product images.'
	// 			], 403);
	// 		}
		
	// 		$finalImages = [];
	// 		foreach ($request->images as $key => $image) {
	// 			if (is_string($image) && filter_var($image, FILTER_VALIDATE_URL)) {
	// 				// It's a URL, keep it as is
	// 				$finalImages[] = $image;
	// 			} elseif ($request->hasFile("images.$key")) {
	// 				// It's an uploaded file, store it to S3
	// 				$file = $request->file("images.$key");
	// 				$path = $file->store($imagePath, 's3');
	// 				$finalImages[] = Storage::disk('s3')->url($path);
	// 			}
	// 			// else ignore invalid inputs
	// 		}
		
	// 		// Save as JSON with unescaped slashes
	// 		$input['images'] = json_encode($finalImages, JSON_UNESCAPED_SLASHES);
	// 	} else {
	// 		// If images are not being updated, preserve existing images
	// 		$input['images'] = $product->images;
	// 	}

	// 	// Handle videos with role-based permission
	// 	if ($request->has('video_path')) {
	// 		// Check if user is actually trying to modify videos (upload new files)
	// 		$hasNewVideoFiles = false;
	// 		$videoPaths = is_array($request->video_path) ? $request->video_path : [$request->video_path];
	// 		foreach ($videoPaths as $key => $video) {
	// 			if ($request->hasFile("video_path.$key")) {
	// 				$hasNewVideoFiles = true;
	// 				break;
	// 			}
	// 		}
			
	// 		// Only check permissions if user is uploading new video files
	// 		if ($hasNewVideoFiles && !$canModifyImages) {
	// 			return response()->json([
	// 				'success' => false,
	// 				'message' => 'You do not have permission to modify product videos.'
	// 			], 403);
	// 		}
		
	// 		$finalVideos = [];
	// 		foreach ($videoPaths as $key => $video) {
	// 			if (is_string($video) && filter_var($video, FILTER_VALIDATE_URL)) {
	// 				// It's a URL, keep as is
	// 				$finalVideos[] = $video;
	// 			} elseif ($request->hasFile("video_path.$key")) {
	// 				// It's an uploaded file, upload to S3
	// 				$file = $request->file("video_path.$key");
	// 				$path = $file->store($videoPath, 's3');
	// 				$finalVideos[] = Storage::disk('s3')->url($path);
	// 			}
	// 			// ignore invalid inputs
	// 		}
		
	// 		$input['video_path'] = json_encode($finalVideos, JSON_UNESCAPED_SLASHES);
	// 	} else {
	// 		// If videos are not being updated, preserve existing videos
	// 		$input['video_path'] = $product->video_path;
	// 	}
	// 	// Handle document upload (keeping existing logic)
	// 	$existingDocs = is_array($product->documents) ? $product->documents : json_decode($product->documents, true);
	// 	$existingDocs = is_array($existingDocs) ? $existingDocs : [];

	// 	if ($request->hasFile('documents')) {
	// 		$uploadedDocs = [];
	// 		foreach ($request->file('documents') as $doc) {
	// 			$path = $doc->store($documentPath, 's3');

	// 			/* Check if the title is provided, if not, use the document's name */
	// 			$title = $request->input('title', $doc->getClientOriginalName()); /* default to original name if title is empty */

	// 			/* If title is still empty, use the document name as title */
	// 			if (empty($title)) {
	// 				$title = basename($doc->getClientOriginalName());  /* Use document name if title is empty */
	// 			}

	// 			/* Create an array with title and path for each uploaded document */
	// 			$uploadedDocs[] = [
	// 				'title' => $title,
	// 				'path' => Storage::disk('s3')->url($path)
	// 			];
	// 		}

	// 		/* Merge with existing documents */
	// 		$input['documents'] = array_merge($existingDocs, $uploadedDocs);
	// 	} else {
	// 		/* Retain existing documents if no new files are uploaded */
	// 		$input['documents'] = $existingDocs;
	// 	}

	// 	/* Convert to JSON with unescaped slashes */
	// 	$input['documents'] = json_encode($input['documents'], JSON_UNESCAPED_SLASHES);

	// 	$input['is_variation'] = filter_var($request->input('is_variation'), FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
	// 	$input['variant_requires_shipping'] = filter_var($request->input('variant_requires_shipping'), FILTER_VALIDATE_BOOLEAN) ? 1 : 0;

	// 	/* List of valid fields allowed for updating */
	// 	$validArray = [
	// 		"sku", "status", "barcode", "warranty_information", "refund", "quantity",
	// 		"stock_status", "price",
	// 		"sale_price", "cost_per_item", "cost_per_item_currency",
	// 		"cost_type", "additional_cost_percentage", "additional_cost_value",
	// 		"total_cost_per_item", "tax_id", "currency_id", "name", "description", "images",
	// 		"image", "video_path", "videos", "documents", "is_variation", "variant_requires_shipping",
	// 		"variant_barcode", "variant_color_title", "variant_color_value", "vendor_id",
	// 		"brand_id", "views", "units_sold", "frequently_bought_together", "google_shopping_category", "google_shopping_mpn", "order",
	// 		"box_quantity", "delivery_days", "unit_of_measurement_id", "benefits_features" , "gen_type"
	// 	];

	// 	unset($input['product_attributes']);

	// 	/* Check for invalid fields */
	// 	$invalidFields = array_diff(array_keys($input), $validArray);
	// 	if (!empty($invalidFields)) {
	// 		return response()->json([
	// 			'success' => false,
	// 			'message' => 'The field' . (count($invalidFields) > 1 ? 's' : '') . ' ' . implode(', ', $invalidFields) . ' ' . (count($invalidFields) > 1 ? 'are' : 'is') . ' not valid.'
	// 		]);
	// 	}

	// 	/* Initialize an error array to store validation errors */
	// 	$rowError = [];

	// 	/* Refund policy validation */
	// 	$usRefundPolicyArray = [
	// 		1 => "non-refundable",
	// 		2 => "15 days",
	// 		3 => "90 days"
	// 	];
	// 	if (isset($input['refund'])) {
	// 		if (!is_numeric($input['refund']) || !array_key_exists((int) $input['refund'], $usRefundPolicyArray)) {
	// 			$rowError[] = "Refund policy should be numeric and either 1 for Non-Refundable, 2 for 15 Days Refund, or 3 for 90 Days Refund.";
	// 		} else {
	// 			$product->refund = $usRefundPolicyArray[(int) $input['refund']];
	// 			unset($input['refund']); /* Remove processed field */
	// 		}
	// 	}

	// 	if (isset($input['status'])) {
	// 		$validStatuses = ['draft', 'published', 'pending']; /* Define allowed statuses */
	// 		if (!in_array($input['status'], $validStatuses)) {
	// 			return response()->json([
	// 				'success' => false,
	// 				'message' => 'Invalid status value. Allowed values: draft, published, archived.'
	// 			]);
	// 		}

	// 		if($input['status'] == 'published' && $product->productAttributes->count() < 5) {
	// 			return response()->json([
	// 				'success' => false,
	// 				'message' => 'You must assign at least 5 attributes to the product before it can be published'
	// 			]);
	// 		}

	// 		$product->status = $input['status']; /* Assign status */
	// 	}

	// 	/* Handle benefits_features field */
	// 	if ($request->has('benefits_features')) {
	// 		/* Decode existing benefits_features if available */
	// 		$existingBenefits = json_decode($product->benefits_features, true);

	// 		/* Ensure existingBenefits is an array */
	// 		if (!is_array($existingBenefits)) {
	// 			$existingBenefits = [];
	// 		}

	// 		/* Get the incoming benefits_features */
	// 		$benefitsFeaturesInput = $request->input('benefits_features');

	// 		/* Handle different input formats */
	// 		if (is_string($benefitsFeaturesInput)) {
	// 			/* Try to decode JSON string */
	// 			$newBenefits = json_decode($benefitsFeaturesInput, true);

	// 			/* If JSON decode failed, treat as invalid */
	// 			if (json_last_error() !== JSON_ERROR_NONE) {
	// 				return response()->json([
	// 					'success' => false,
	// 					'message' => 'Invalid benefits_features JSON format.'
	// 				], 400);
	// 			}
	// 		} elseif (is_array($benefitsFeaturesInput)) {
	// 			/* Already an array */
	// 			$newBenefits = $benefitsFeaturesInput;
	// 		} else {
	// 			/* Invalid format */
	// 			return response()->json([
	// 				'success' => false,
	// 				'message' => 'Invalid benefits_features format. Must be JSON string or array.'
	// 			], 400);
	// 		}

	// 		/* Ensure newBenefits is an array */
	// 		if (!is_array($newBenefits)) {
	// 			$newBenefits = [];
	// 		}

	// 		/* Merge existing benefits with new ones */
	// 		$mergedBenefits = array_merge($existingBenefits, $newBenefits);

	// 		/* Save back as JSON */
	// 		$product->benefits_features = json_encode($mergedBenefits, JSON_UNESCAPED_SLASHES);
	// 	}

	// 	/* Stock status validation */
	// 	$usStockStatusArray = [
	// 		1 => "in_stock",
	// 		2 => "out_of_stock",
	// 		3 => "Pre Order"
	// 	];
	// 	if (isset($input['stock_status'])) {
	// 		if (!is_numeric($input['stock_status']) || !array_key_exists((int) $input['stock_status'], $usStockStatusArray)) {
	// 			$rowError[] = "Stock status should be numeric and either 1 for In Stock, 2 for Out of Stock, or 3 for On Backorder.";
	// 		} else {
	// 			$product->stock_status = $usStockStatusArray[(int) $input['stock_status']];
	// 			unset($input['stock_status']); /* Remove processed field */
	// 		}
	// 	}

	// 	/* Tax ID validation */
	// 	if (isset($input['tax_id'])) {
	// 		$taxArray = Tax::pluck("id")->toArray();
	// 		if (!is_numeric($input['tax_id']) || !in_array((int) $input['tax_id'], $taxArray)) {
	// 			$rowError[] = "Invalid tax value. Please select a valid tax ID.";
	// 		} else {
	// 			$product->tax_id = (int) $input['tax_id'];
	// 			unset($input['tax_id']); /* Remove processed field */
	// 		}
	// 	}

	// 	/* Currency ID validation */
	// 	if (isset($input['currency_id'])) {
	// 		$currencyArray = Currency::pluck("id")->toArray();
	// 		if (!is_numeric($input['currency_id']) || !in_array((int) $input['currency_id'], $currencyArray)) {
	// 			$rowError[] = "Invalid currency value. Please select a valid currency ID.";
	// 		} else {
	// 			$product->currency_id = (int) $input['currency_id'];
	// 			unset($input['currency_id']); /* Remove processed field */
	// 		}
	// 	}

	// 	/* Unit ID validation for length, weight, and shipping */
	// 	$lengthUnitArray = [
	// 		1 => "cm",
	// 		3 => "inch",
	// 		11 => "mm",
	// 	];
	// 	$weightUnitArray = [
	// 		5 => "kg",
	// 		6 => "g",
	// 		9 => "lbs",
	// 	];

	// 	if (isset($input['google_shopping_category'])) {
	// 		$product->google_shopping_category = $input['google_shopping_category'];
	// 		unset($input['google_shopping_category']);
	// 	}

	// 	if (isset($input['google_shopping_mpn'])) {
	// 		$product->google_shopping_mpn = $input['google_shopping_mpn'];
	// 		unset($input['google_shopping_mpn']);
	// 	}

	// 	if (isset($input['box_quantity'])) {
	// 		/* If box_quantity should be an integer */
	// 		$product->box_quantity = (int)$input['box_quantity'];
	// 		unset($input['box_quantity']);
	// 	}

	// 	/* Store ID validation */
	// 	if (isset($input['vendor_id'])) {
	// 		$storeArray = Vendor::pluck("id")->toArray();
	// 		if (!is_numeric($input['vendor_id']) || !in_array((int) $input['vendor_id'], $storeArray)) {
	// 			$storeList = implode(', ', $storeArray);
	// 			$rowError[] = "Invalid store value. Valid store IDs are: " . $storeList;
	// 		} else {
	// 			$product->vendor_id = (int) $input['vendor_id'];
	// 			unset($input['vendor_id']); /* Remove processed field */
	// 		}
	// 	}

	// 	/* Brand ID validation */
	// 	if (isset($input['brand_id'])) {
	// 		$brandArray = Brand::pluck("id")->toArray();
	// 		if (!is_numeric($input['brand_id']) || !in_array((int) $input['brand_id'], $brandArray)) {
	// 			$brandList = implode(', ', $brandArray);
	// 			$rowError[] = "Invalid brand value. Valid brand IDs are: " . $brandList;
	// 		} else {
	// 			$product->brand_id = (int) $input['brand_id'];
	// 			unset($input['brand_id']); /* Remove processed field */
	// 		}
	// 	}

	// 	/* If any validation errors exist, return them */
	// 	if (!empty($rowError)) {
	// 		return response()->json([
	// 			'success' => false,
	// 			'message' => $rowError
	// 		]);
	// 	}

	// 	/* Assign remaining valid fields to the product */
	// 	foreach ($input as $key => $value) {
	// 		$product->$key = $value;
	// 	}

	// 	if ($request->has('review')) {
	// 		$reviewInput = $request->input('review');

	// 		/* Ensure required fields exist */
	// 		if (empty($reviewInput['comment'])) {
	// 			return response()->json([
	// 				'success' => false,
	// 				'message' => 'Review comment is required.',
	// 			]);
	// 		}

	// 		/* Ensure product exists */
	// 		if (!$product) {
	// 			return response()->json([
	// 				'success' => false,
	// 				'message' => 'Product not found.',
	// 			]);
	// 		}

	// 		/* Ensure a valid customer_id is used */
	// 		$customerId =  1; /* Default to 1 if not logged in */

	// 		/* Create new review */
	// 		$review = new Review();
	// 		$review->product_id = $product->id;
	// 		$review->customer_id = $customerId;
	// 		$review->customer_name = $reviewInput['customer_name'] ?? 'Guest';
	// 		$review->customer_email = $reviewInput['customer_email'] ?? null;
	// 		$review->star = isset($reviewInput['star']) ? (int) $reviewInput['star'] : null;
	// 		$review->comment = $reviewInput['comment'];
	// 		$review->status = 'pending'; /* Set a default status if needed */

	// 		if ($review->save()) {
	// 			/* Handle review images (if any) */
	// 			if ($request->hasFile('review_images')) {
	// 				foreach ($request->file('review_images') as $image) {
	// 					$path = $image->store('reviews', 'public');

	// 					ReviewImage::create([
	// 						'review_id' => $review->id,
	// 						'image_path' => $path,
	// 					]);
	// 				}
	// 			}

	// 			return response()->json([
	// 				'success' => true,
	// 				'message' => 'Review saved successfully.',
	// 			]);
	// 		} else {
	// 			\Log::error('Failed to save review:', $review->toArray());
	// 			return response()->json(['success' => false, 'message' => 'Failed to save review.']);
	// 		}
	// 	}

	// 	/* Save the product */
	// 	$product->save();

	// 	$product = Product::find($product->id);

	// 	/* Return success response */
	// 	return response()->json([
	// 		'success' => true,
	// 		'message' => 'Product updated successfully.',
	// 		'product' => $product->load('productAttributes:id,product_id,attribute_id,attribute_value'),
	// 		'unitOfMeasurements' => $unitOfMeasurements ,
	// 		// 'review' => $review ?? null,
	// 		'faq' => $faqs ?? null,
	// 	]);
	// }
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

		// Get the authenticated user and their role
		$user = auth()->user();
		$userRole = $user ? $user->getRoleNames()->first() : null;
		$allowedRoles = [
			'Super Admin', 
			'Admin', 
			'Graphic Designer Manager'
		];  // Define which roles can modify images
		$canModifyImages = $userRole && in_array($userRole, $allowedRoles);

		$contentAllowedRoles = [
			'Super Admin',
			'Admin',
			'Content Writing Manager',
			'Content Writer'
		];
		$canModifyContent = $userRole && in_array($userRole, $contentAllowedRoles);
	

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
			$productAttributes = is_array($request->product_attributes) ? $request->product_attributes : json_decode($request->product_attributes, true);

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

					$value = null;
					$measurementUnitID = null;

					if ($existingAttribute->type == 'measurement' && is_array($attributeValue)) {
						$value = $attributeValue['value'] ?? null;
						$measurementUnitID = $attributeValue['measurement_id'] ?? null;

						if (!$value || !$measurementUnitID) {
							/* Delete attribute if either value or measurement ID is missing */
							$product->productAttributes()
							->where('attribute_id', $attributeId)
							->delete();
						} else {
							/* Update or create measurement attribute */
							$product->productAttributes()->updateOrCreate(
								['attribute_id' => $attributeId],
								[
									'attribute_value' => $value,
									'measurement_unit_id' => $measurementUnitID
								]
							);
						}
					} else {
						$value = $attributeValue;

						if (empty($value)) {
							/* Delete non-measurement attribute if empty */
							$product->productAttributes()
							->where('attribute_id', $attributeId)
							->delete();
						} else {
							/* Update or create normal attribute */
							$product->productAttributes()->updateOrCreate(
								['attribute_id' => $attributeId],
								[
									'attribute_value' => $value,
									'measurement_unit_id' => null
								]
							);
						}
					}

					if ($existingAttribute->type === 'select') {
						if ($existingAttribute->attributeValues()->where('attribute_value', $value)->doesntExist()) {
							$existingAttribute->attributeValues()->create([
								'attribute_value' => $value
							]);
						}
					}
				}
			}
		}

		$faqs = $request->input('faqs', []);
		$hasNewContent = false;

		/* Decode FAQs if passed as JSON string */
		if (is_string($faqs)) {
			$decoded = json_decode($faqs, true);

			if (json_last_error() !== JSON_ERROR_NONE) {
				return response()->json([
					'success' => false,
					'message' => 'Invalid JSON format for faqs.'
				], 400);
			}

			$faqs = is_array($decoded) && isset($decoded[0]) ? $decoded : ($decoded['faqs'] ?? []);
		}

		if (!is_array($faqs)) {
			return response()->json([
				'success' => false,
				'message' => 'The field faqs must be a valid JSON array.'
			], 400);
		}

		$existingFaqs = Faq::where('product_id', $product->id)->get();
		$existingFaqIds = $existingFaqs->pluck('id')->toArray();
		$processedFaqIds = [];

		foreach ($faqs as $faqData) {
			if (!empty($faqData['question']) && !empty($faqData['answer'])) {
				if (!empty($faqData['id'])) {
					$faq = $existingFaqs->where('id', $faqData['id'])->first();
					if ($faq) {
						// Check if any content has changed
						if (
							$faq->question !== $faqData['question'] ||
							$faq->answer !== $faqData['answer'] ||
							$faq->category_id != ($faqData['category_id'] ?? null)
						) {
							$hasNewContent = true;
						}

						$faq->update([
							'question' => $faqData['question'],
							'answer' => $faqData['answer'],
							'category_id' => $faqData['category_id'] ?? null,
							'status' => 'published',
						]);
						$processedFaqIds[] = $faq->id;
					}
				} else {
					$hasNewContent = true;
					$newFaq = Faq::create([
						'product_id' => $product->id,
						'question' => $faqData['question'],
						'answer' => $faqData['answer'],
						'category_id' => $faqData['category_id'] ?? null,
						'status' => 'published',
					]);
					$processedFaqIds[] = $newFaq->id;
				}
			}
		}

		// Detect deleted FAQs
		$faqsToDelete = array_diff($existingFaqIds, $processedFaqIds);
		if (!empty($faqsToDelete)) {
			$hasNewContent = true;
			Faq::where('product_id', $product->id)
				->whereIn('id', $faqsToDelete)
				->delete();
		}

		// Permission check
		if ($hasNewContent && !$canModifyContent) {
			return response()->json([
				'success' => false,
				'message' => 'You do not have permission to modify product FAQs.'
			], 403);
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

		// Handle images with role-based permission
		if ($request->has('images')) {
			// Check if user is actually trying to modify images (upload new files)
			$hasNewImageFiles = false;
			foreach ($request->images as $key => $image) {
				if ($request->hasFile("images.$key")) {
					$hasNewImageFiles = true;
					break;
				}
			}
			
			// Only check permissions if user is uploading new image files
			if ($hasNewImageFiles && !$canModifyImages) {
				return response()->json([
					'success' => false,
					'message' => 'You do not have permission to modify product images.'
				], 403);
			}
		
			$finalImages = [];
			foreach ($request->images as $key => $image) {
				if (is_string($image) && filter_var($image, FILTER_VALIDATE_URL)) {
					// It's a URL, keep it as is
					$finalImages[] = $image;
				} elseif ($request->hasFile("images.$key")) {
					// It's an uploaded file, store it to S3
					$file = $request->file("images.$key");
					$path = $file->store($imagePath, 's3');
					$finalImages[] = Storage::disk('s3')->url($path);
				}
				// else ignore invalid inputs
			}
		
			// Save as JSON with unescaped slashes
			$input['images'] = json_encode($finalImages, JSON_UNESCAPED_SLASHES);
		} else {
			// If images are not being updated, preserve existing images
			$input['images'] = $product->images;
		}

		// Handle videos with role-based permission
		if ($request->has('video_path')) {
			// Check if user is actually trying to modify videos (upload new files)
			$hasNewVideoFiles = false;
			$videoPaths = is_array($request->video_path) ? $request->video_path : [$request->video_path];
			foreach ($videoPaths as $key => $video) {
				if ($request->hasFile("video_path.$key")) {
					$hasNewVideoFiles = true;
					break;
				}
			}
			
			// Only check permissions if user is uploading new video files
			if ($hasNewVideoFiles && !$canModifyImages) {
				return response()->json([
					'success' => false,
					'message' => 'You do not have permission to modify product videos.'
				], 403);
			}
		
			$finalVideos = [];
			foreach ($videoPaths as $key => $video) {
				if (is_string($video) && filter_var($video, FILTER_VALIDATE_URL)) {
					// It's a URL, keep as is
					$finalVideos[] = $video;
				} elseif ($request->hasFile("video_path.$key")) {
					// It's an uploaded file, upload to S3
					$file = $request->file("video_path.$key");
					$path = $file->store($videoPath, 's3');
					$finalVideos[] = Storage::disk('s3')->url($path);
				}
				// ignore invalid inputs
			}
		
			$input['video_path'] = json_encode($finalVideos, JSON_UNESCAPED_SLASHES);
		} else {
			// If videos are not being updated, preserve existing videos
			$input['video_path'] = $product->video_path;
		}
		// Handle document upload (keeping existing logic)
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

		$input['is_variation'] = filter_var($request->input('is_variation'), FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
		$input['variant_requires_shipping'] = filter_var($request->input('variant_requires_shipping'), FILTER_VALIDATE_BOOLEAN) ? 1 : 0;

		/* List of valid fields allowed for updating */
		$validArray = [
			"sku", "status", "barcode", "warranty_information", "refund", "quantity",
			"stock_status", "price",
			"sale_price", "cost_per_item", "cost_per_item_currency",
			"cost_type", "additional_cost_percentage", "additional_cost_value",
			"total_cost_per_item", "tax_id", "currency_id", "name", "description", "images",
			"image", "video_path", "videos", "documents", "is_variation", "variant_requires_shipping",
			"variant_barcode", "variant_color_title", "variant_color_value", "vendor_id",
			"brand_id", "views", "units_sold", "frequently_bought_together", "google_shopping_category", "google_shopping_mpn", "order",
			"box_quantity", "delivery_days", "unit_of_measurement_id", "benefits_features" , "gen_type"
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

			if($input['status'] == 'published' && $product->productAttributes->count() < 5) {
				return response()->json([
					'success' => false,
					'message' => 'You must assign at least 5 attributes to the product before it can be published'
				]);
			}

			$product->status = $input['status']; /* Assign status */
		}

		/* Handle benefits_features field */
		if ($request->has('benefits_features')) {
			/* Decode existing benefits_features if available */
			$existingBenefits = json_decode($product->benefits_features, true);

			/* Ensure existingBenefits is an array */
			if (!is_array($existingBenefits)) {
				$existingBenefits = [];
			}

			/* Get the incoming benefits_features */
			$benefitsFeaturesInput = $request->input('benefits_features');

			/* Handle different input formats */
			if (is_string($benefitsFeaturesInput)) {
				/* Try to decode JSON string */
				$newBenefits = json_decode($benefitsFeaturesInput, true);

				/* If JSON decode failed, treat as invalid */
				if (json_last_error() !== JSON_ERROR_NONE) {
					return response()->json([
						'success' => false,
						'message' => 'Invalid benefits_features JSON format.'
					], 400);
				}
			} elseif (is_array($benefitsFeaturesInput)) {
				/* Already an array */
				$newBenefits = $benefitsFeaturesInput;
			} else {
				/* Invalid format */
				return response()->json([
					'success' => false,
					'message' => 'Invalid benefits_features format. Must be JSON string or array.'
				], 400);
			}

			/* Ensure newBenefits is an array */
			if (!is_array($newBenefits)) {
				$newBenefits = [];
			}

			/* Merge existing benefits with new ones */
			$mergedBenefits = array_merge($existingBenefits, $newBenefits);

			/* Save back as JSON */
			$product->benefits_features = json_encode($mergedBenefits, JSON_UNESCAPED_SLASHES);
		}

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
		if (isset($input['vendor_id'])) {
			$storeArray = Vendor::pluck("id")->toArray();
			if (!is_numeric($input['vendor_id']) || !in_array((int) $input['vendor_id'], $storeArray)) {
				$storeList = implode(', ', $storeArray);
				$rowError[] = "Invalid store value. Valid store IDs are: " . $storeList;
			} else {
				$product->vendor_id = (int) $input['vendor_id'];
				unset($input['vendor_id']); /* Remove processed field */
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
			// 'review' => $review ?? null,
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
			"vendor_id",
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
		$productAttributeMeasurement = $product->productAttributes->pluck('measurement_unit_id', 'attribute_id');

		$attributeGroup = $category->categoryAttributeGroups()
		->with(['groupsAttributes.attributeValues', 'groupsAttributes.measurementUnits'])
		->get()
		->map(function ($group) use ($productAttributes, $productAttributeMeasurement) {
			return [
				'id' => $group->id,
				'name' => $group->name,
				'group_attributes' => $group->groupsAttributes->map(function ($attribute) use ($productAttributes, $productAttributeMeasurement) {
					$data = [
						'id' => $attribute->id,
						'name' => $attribute->name,
						'code' => $attribute->code,
						'type' => $attribute->type,
						'validations' => json_decode($attribute->validations, true),
						'currentValue' => $productAttributes[$attribute->id] ?? null,
					];

					if ($attribute->type === 'select') {
						$data['attributeValues'] = $attribute->attributeValues->pluck('attribute_value')->values()->all();
					}

					if ($attribute->type === 'measurement') {
						$data['attributeMeasurement'] = $attribute->measurementUnits->pluck('name', 'id')->all();
						$data['currentMeasurementId'] = $productAttributeMeasurement[$attribute->id] ?? null;
					}

					return $data;
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
	 *     summary="Import products from an excel file",
	 *     tags={"Products"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="multipart/form-data",
	 *             @OA\Schema(
	 *                 required={"upload_file"},
	 *                 @OA\Property(property="upload_file", type="string", format="binary", description="xlsx file (.xlsx) max 2MB"),
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Imported successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function import(Request $request, ExcelImporterService $excelImporter)
	{
		/* Validate request data */
		$request->validate([
			'upload_file' => 'required|file|mimes:xlsx,xls|max:2048',
		]);

		try {
			$productFileFormatArray = [];

			$idArray = product_import_constants('ID');
			$urlArray = product_import_constants('URL');
			$generalFieldArray = product_import_constants('GENERAL_FIELDS');
			$descriptionSectionArray = product_import_constants('DESCRIPTION_SECTION');
			$benefitSectionArray = product_import_constants('BENEFIT_SECTION');
			$faqSectionArray = product_import_constants('FAQ_SECTION');
			$advanceFieldArray = product_import_constants('ADVANCED_FIELDS');
			$seoSection = product_import_constants('SEO_SECTION');
			$discountSectionArray = product_import_constants('DISCOUNT_SECTION');
			$translationSectionArray = product_import_constants('TRANSLATION_SECTION');

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

			$excelImporter->processExcelImport(
				$request->file('upload_file'),
				$productFileFormatArray,
				'Product', /* Module name */
				'JOB_PRODUCT', /* Job name */
				'Import Products', /* Batch name */
				ImportProductJob::class
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