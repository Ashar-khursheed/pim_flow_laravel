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
use App\Models\ProductSupplier;
use App\Models\TransactionLog;
use App\Models\Faq;
use App\Models\Attribute;
use Illuminate\Support\Facades\DB;
use App\Jobs\ImportProductJob;
use App\Services\ExcelImporterService;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Facades\Validator;

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
	 *     @OA\Parameter(name="from_date", in="query", @OA\Schema(type="string", format="date")),
	 *     @OA\Parameter(name="to_date", in="query", @OA\Schema(type="string", format="date")),
	 *     @OA\Parameter(name="updated_from_date", in="query", @OA\Schema(type="string", format="date")),
	 *     @OA\Parameter(name="updated_to_date", in="query", @OA\Schema(type="string", format="date")),
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
	 *				description="Filter products by status (e.g., draft, published)",
	 *				required=false,
	 *				@OA\Schema(type="string", example="published")
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
		if ($request->filled('from_date') && $request->filled('to_date')) {
			$from = $request->from_date . ' 00:00:00';
			$to = $request->to_date . ' 23:59:59';

			$records = Product::whereBetween('created_at', [$from, $to])->where('status', 'published')->pluck('id');
			return response()->json([
				'success' => true,
				'message' => __('msg_rec_list'),
				'data' => $records,
			]);
		}

		if ($request->filled('updated_from_date') && $request->filled('updated_to_date')) {
			$from = $request->updated_from_date . ' 00:00:00';
			$to = $request->updated_to_date . ' 23:59:59';

			// Get products updated within range OR whose suppliers were updated in range
			$records = Product::where('status', 'published')
			->where(function ($query) use ($from, $to) {
				$query->whereBetween('updated_at', [$from, $to])
				->orWhereHas('productSuppliers', function ($supplierQuery) use ($from, $to) {
					$supplierQuery->whereBetween('updated_at', [$from, $to]);
				});
			})
			->pluck('id');

			return response()->json([
				'success' => true,
				'message' => __('msg_rec_list'),
				'data' => $records,
			]);
		}


		$perPage = $request->input('per_page', 50);
		$search = $request->input('search');
		$status = $request->input('status');
		$approved = $request->input('approved');
		$ar_approved = $request->input('ar_approved');
		$sortBy = $request->input('sort_by', 'id');
		$sortDirection = $request->input('sort_direction', 'desc');

		// Validate sort columns to prevent SQL injection
		$allowedSortColumns = ['id', 'name', 'sku', 'brand_id', 'status', 'gen_type', 'approved' , 'ar_approved'];
		if (!in_array($sortBy, $allowedSortColumns)) {
			$sortBy = 'id'; // Default to id if invalid column
		}

		// Validate sort direction
		if (!in_array(strtolower($sortDirection), ['asc', 'desc'])) {
			$sortDirection = 'desc'; // Default to descending if invalid direction
		}

		$query = Product::with([
			'brand:id,name',
			'categories:id,name',
			'slug:id,key,reference_id',
			'productSuppliers.vendor:id,name', // Updated to include vendor relationship
			'vendors:id,name' // Make sure to select the name field
		])
		->select(['id', 'name', 'sku', 'images', 'brand_id', 'status', 'gen_type', 'approved' , 'ar_approved']);

		/* Apply search if provided */

		// Apply status filter
		if ($status !== null) {
			$query->where('status', $status);
		}
		if ($approved !== null) {
			$query->where('approved', $approved);
		}
		if ($ar_approved !== null) {
			$query->where('ar_approved', $ar_approved);
		}

		if ($search) {
			$query->where(function ($q) use ($search) {
				$q->where('name', 'like', "%{$search}%")
				->orWhere('sku', 'like', "%{$search}%")
				->orWhereHas('brand', function ($brandQuery) use ($search) {
					$brandQuery->where('name', 'like', "%{$search}%");
				})
				->orWhereHas('categories', function ($categoryQuery) use ($search) {
					$categoryQuery->where('name', 'like', "%{$search}%");
				});
			});
		}

		$products = $query->orderBy($sortBy, $sortDirection)
		->paginate($perPage);

		/* Formatting response */
		$formattedProducts = $products->map(function ($product) {
			$firstSupplier = $product->productSuppliers->first();

			if (!$firstSupplier) {
				return [
					'id' => $product->id,
					'name' => $product->name,
					'gen_type' => $product->gen_type,
					'approved' => $product->approved,
					'ar_approved' => $product->ar_approved,
					'sku' => $product->sku,
					'image' => ($imageUrls = json_decode($product->images, true)) && isset($imageUrls[0]) ? $imageUrls[0] : null,
					'brand' => optional($product->brand)->name,
					'status' => $product->status,
					'quote_available' => $product->quote_available,
					'price' => null,
					'sale_price' => null,
					'margin' => null,
					'margin_percent' => null,
					'min_quantity' => null,
					'is_fixed' => null,
					'shipping_charge' => null,
					'vendor_name' => null, // Added vendor_name field
					'product_family' => $product->categories->pluck('name')->toArray(),
					'taxonomy_path' => optional($product->slug)->key ?? '',
				];
			}

			$margin = $firstSupplier->sale_price - $firstSupplier->price;
			$marginPercent = $firstSupplier->sale_price > 0
			? ($margin / $firstSupplier->sale_price) * 100
			: 0;

			return [
				'id' => $product->id,
				'name' => $product->name,
				'gen_type' => $product->gen_type,
				'approved' => $product->approved,
				'ar_approved' => $product->ar_approved,
				'sku' => $product->sku,
				'image' => ($imageUrls = json_decode($product->images, true)) && isset($imageUrls[0]) ? $imageUrls[0] : null,
				'brand' => optional($product->brand)->name,
				'status' => $product->status,
				'quote_available' => $product->quote_available,
				'price' => $firstSupplier->price,
				'sale_price' => $firstSupplier->sale_price,
				'min_quantity' => $firstSupplier->min_quantity,
				'is_fixed' => $firstSupplier->is_fixed,
				'shipping_charge' => $firstSupplier->shipping_charge,
				'vendor_id' => $firstSupplier->vendor_id,
				'vendor_name' => $product->vendors->pluck('name')->first(), // Get first vendor name from vendors relationship
				'margin' => $margin,
				'margin_percent' => round($marginPercent, 2),
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
			'websites' => "required|array",
		]);

		$product = new Product();
		$product->name = $request->name;
		$product->sku = $request->sku;
		$product->website_ids = implode(',', $request->websites);
		$product->status = 'draft';

		$product->quote_available = $request->quote_available;

		$product->created_at = now();
		$product->updated_at = now();
		$product->created_by = auth()->id();
		$product->save();

		/* Create English translation */
		if (in_array(config('app.website'), ['UAE', 'UAE_T', 'SA'])) {
			$product->translateOrNew('en')->name_tr = $request->name;
		}
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
			$product->productAttributes()->delete();
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
	 *             enum={"General", "Inventory & Stock Management", "Pricing & Sales", "Marketing", "Media", "Shipping & Dimensions", "Product Variations", "Store & Vendor Information", "Performance & Analytics", "Comparison & Bundling", "Other", "All"},
	 *             example="General"
	 *         )
	 *     ),
	 *     @OA\Parameter(
	 *         name="locale",
	 *         in="query",
	 *         required=true,
	 *         @OA\Schema(
	 *             type="string",
	 *             enum={"ar", "en"},
	 *             example="ar"
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
	// public function show($productId, Request $request)
	// {
	// 	$locale = in_array($request->locale ?? 'en', ['ar', 'en']) ? ($request->locale ?? 'en') : 'en';
	// 	$attributeGroup = [
	// 		'General' => ['sku', 'barcode', 'status', 'approved' , 'ar_approved'],

	// 		'Inventory & Stock Management' => ['stock_status'],
	// 		'Pricing & Sales' => ['tax_id', 'currency_id', 'approved_by'],
	// 		'Marketing' => ['name', 'description', 'gen_type' , 'quote_available'],
	// 		'Media' => ['images', 'video_path', 'documents', 'benefits_features'],
	// 		'Store & Vendor Information' => ['brand_id'],
	// 		'Performance & Analytics' => ['views', 'units_sold'],
	// 		'Other' => ['order', 'website_ids'],
	// 		'All' => []
	// 	];

	// 	$attributeGroup['All'] = array_merge(...array_values(array_filter($attributeGroup, fn($key) => $key !== 'All', ARRAY_FILTER_USE_KEY)));

	// 	$relations = [
	// 		'General' => ['categories:id,name,parent_id'],
	// 		'Pricing & Sales' => ['currency:id,title'],
	// 		'Shipping & Dimensions' => [],
	// 		'Store & Vendor Information' => ['brand:id,name', 'creator:id,name'],
	// 		'Pricing' => ['vendors:id,name,price,sale_price,delivery_days,inventory,in_stock,dropshipping'],
	// 		'All' => ['categories:id,name,parent_id', 'currency:id,title', 'brand:id,name', 'creator:id,name']
	// 	];

	// 	$attrType = $request->attr_type ?? 'All';

	// 	$attributes = $attributeGroup[$attrType] ?? $attributeGroup['All'];
	// 	// $with = array_merge($relations[$attrType] ?? [], ['categories:id,name,parent_id']);
	// 	$with = array_merge($relations[$attrType] ?? [], [
	// 		'categories:id,name,parent_id',
	// 		'categories.parent:id,name,parent_id',
	// 		'categories.parent.parent:id,name,parent_id',
	// 		'categories.children:id,name,parent_id',
	// 		'vendors',
	// 		'productAttributes.attributeDetails',
	// 		'productAttributes.measurementUnit'
	// 	]);

	// 	$product = Product::with($with)->where('id', $productId)->first(array_merge(['id'], $attributes));

	// 	if (!$product) {
	// 		return response()->json([
	// 			'success' => false,
	// 			'message' => 'Product does not exist.'
	// 		]);
	// 	}

	// 	// Extract first vendor's price and sale_price
	// 	$firstVendor = $product->vendors->first();
	// 	$firstSupplier = $product->productSuppliers->first();

	// 	$formattedProduct = []; // initialize

	// 	$formattedProduct['categories'] = $formattedCategories;
	// 	$formattedProduct['price'] = $firstSupplier->price ?? null;
	// 	$formattedProduct['sale_price'] = $firstSupplier->sale_price ?? null;
	// 	$formattedProduct['delivery_days'] = $firstSupplier->delivery_days ?? null;
	// 	$formattedProduct['inventory'] = $firstSupplier->inventory ?? null;
	// 	$formattedProduct['in_stock'] = $firstSupplier->in_stock ?? null;
	// 	$formattedProduct['product_attributes'] = [];


	// 	foreach ($product->categories as $category) {
	// 		$chain = [];

	// 		// Step 1: Build the chain from current to root
	// 		$current = $category;
	// 		while ($current) {
	// 			$chain[] = $current;
	// 			$current = $current->parent;
	// 		}

	// 		// Step 2: Reverse to go from root to child
	// 		$chain = array_reverse($chain);

	// 		// Step 3: Build nested structure, merging by ID
	// 		$ref = &$formattedCategories;

	// 		foreach ($chain as $cat) {
	// 			// Check if this category already exists at this level
	// 			$found = false;
	// 			foreach ($ref as &$item) {
	// 				if ($item['id'] == $cat->id) {
	// 					$ref = &$item['children'];
	// 					$found = true;
	// 					break;
	// 				}
	// 			}

	// 			if (!$found) {
	// 				$new = [
	// 					'id' => $cat->id,
	// 					'name' => $cat->name,
	// 					'children' => []
	// 				];
	// 				$ref[] = $new;
	// 				$ref = &$ref[array_key_last($ref)]['children'];
	// 			}
	// 		}

	// 		unset($ref); // Clear reference
	// 	}

	// 	/* Fetch reviews where customer_id is null */
	// 	$adminReviews = Review::where('product_id', $productId)
	// 	->whereNull('customer_id')
	// 	->get();

	// 	/* Fetch FAQs using the FAQ model */
	// 	$faqs = FAQ::where('product_id', $productId)->get()->each(function ($faq) use ($locale) {
	// 		$translation = $faq->translations->firstWhere('locale', $locale);

	// 		$faq->question = $translation?->question_tr ?? $faq->question;
	// 		$faq->answer = $translation?->answer_tr ?? $faq->answer;

	// 		unset($faq->translations, $faq->question_tr, $faq->answer_tr);
	// 	});

	// 	if (!empty($product->video_path) && is_string($product->video_path)) {
	// 		$product->video_path = json_decode($product->video_path, true) ?? [];
	// 	}

	// 	if (!empty($product->documents) && is_string($product->documents)) {
	// 		$product->documents = json_decode($product->documents, true) ?? [];
	// 	}

	// 	/* Normalize the documents field */
	// 	if (!empty($product->documents) && is_array($product->documents)) {
	// 		$product->documents = array_map(function ($item) {
	// 			/* Check if the item is an array with 'title' and 'path', or just a string */
	// 			if (is_array($item) && isset($item['path'])) {
	// 				return [
	// 					'title' => $item['title'],
	// 					'path' => $item['path']
	// 				];
	// 			} elseif (is_string($item)) {
	// 				return [
	// 					'title' => basename($item),
	// 					'path' => $item
	// 				];
	// 			}
	// 			return null;
	// 		}, $product->documents);
	// 	}
	// 	$formattedProduct = [];

	// 	$formattedProduct['categories'] = $formattedCategories;

	// 	$formattedProduct['price'] = $productPrice;
	// 	$formattedProduct['sale_price'] = $productSalePrice;
	// 	$formattedProduct['delivery_days'] = $productDelivery_days;
	// 	$formattedProduct['inventory'] = $productInventory;
	// 	$formattedProduct['in_stock'] = $productInStock;
	// 	$formattedProduct['product_attributes'] = [];

	// 	foreach ($product->productAttributes as $attr) {
	// 		$formattedProduct['product_attributes'][] = [
	// 			'attribute_id' => $attr->attribute_id,
	// 			'attribute_name' => $attr->attributeDetails->name ?? null,
	// 			'attribute_value' => $attr->attribute_value,
	// 			'measurement_unit_id' => $attr->measurement_unit_id,
	// 			'measurement_unit_name' => $attr->measurementUnit->name ?? null,
	// 		];
	// 	}

	// 	$formattedProduct['product_suppliers'] = $product->productSuppliers->map(function ($productSupplier) {
	// 		return [
	// 			'id' => $productSupplier->id,
	// 			'product_id' => $productSupplier->product_id,
	// 			'vendor_id' => $productSupplier->vendor_id,
	// 			'vendor_sku' => $productSupplier->vendor_sku,
	// 			'total_cost_per_item' => $productSupplier->total_cost_per_item,
	// 			'inventory' => $productSupplier->inventory,
	// 			'in_stock' => $productSupplier->in_stock,
	// 			'vendor_name' => $productSupplier->vendor->name,
	// 			'dropshipping' => $productSupplier->vendor->dropshipping ?? null, // 👈 added line

	// 		];
	// 	});
	// 	$translation = $product->translations->firstWhere('locale', $locale);

	// 	foreach ($attributes as $attribute) {
	// 		$value = $product->$attribute ?? null;

	// 		switch ($attribute) {

	// 			case 'name':
	// 			$field = $attribute . '_tr';
	// 			$value = $translation ? $translation->$field : $value;
	// 			$formattedProduct[$attribute] = $value;
	// 			break;

	// 			case 'benefits_features':
	// 			case 'description':
	// 			case 'images':
	// 			$field = $attribute . '_tr';
	// 			$value = $translation ? $translation->$field : $value;
	// 			$formattedProduct[$attribute] = is_array($value) ? $value : json_decode($value, true);
	// 			break;

	// 			case 'refund':
	// 			$formattedProduct[$attribute] = [['value' => $value]];
	// 			break;

	// 			case 'stock_status':
	// 			$stockStatusMappings = [
	// 				'in_stock' => 'In Stock',
	// 				'out_of_stock' => 'Out of Stock',
	// 				'on_backorder' => 'Pre Order'
	// 			];

	// 			/* Map selected value to frontend readable text */
	// 			$selectedStockStatus = $stockStatusMappings[$value] ?? $value;

	// 			$formattedProduct['stock_status'] = [
	// 				'selected' => $selectedStockStatus, /* This will now show 'In Stock', 'Out of Stock', etc. */
	// 				'values' => $stockStatusMappings /* Values remain the same */
	// 			];
	// 			break;

	// 			case 'tax_id':
	// 			$tax = Tax::find($value);
	// 			if ($tax) {
	// 				$formattedProduct['tax'] = [['title' => $tax->title, 'rate' => $tax->percentage]];
	// 			} else {
	// 				$formattedProduct['tax'] = [['title' => null, 'rate' => null]];
	// 			}
	// 			break;

	// 			case 'currency_id':
	// 			$formattedProduct['currency'] = $product->currency ? [
	// 				[
	// 					'id' => $product->currency->id,
	// 					'title' => $product->currency->title
	// 				]
	// 			] : null;
	// 			break;

	// 			case 'brand_id':
	// 			$formattedProduct['brand'] = $product->brand ? [
	// 				[
	// 					'id' => $product->brand->id,
	// 					'name' => $product->brand->name
	// 				]
	// 			] : null;
	// 			break;

	// 			case 'categories':
	// 			$formattedProduct['categories'] = $product->categories ? $product->categories->map(function ($category) {
	// 				return [
	// 					'id' => $category->id,
	// 					'name' => $category->name,
	// 					'parent_id' => $category->parent_id
	// 				];
	// 			}) : [];
	// 			break;

	// 			case 'video_path':
	// 			case 'documents':
	// 			$formattedProduct[$attribute] = is_array($value) ? $value : [];
	// 			break;

	// 			case 'status':
	// 			$formattedProduct[$attribute] = [['value' => $value]];
	// 			break;

	// 			default:
	// 			$formattedProduct[$attribute] = $value;
	// 			break;
	// 		}
	// 	}

	// 	$contentAllowedRoles = [
	// 		'Super Admin',
	// 		'Admin',
	// 		'Content Writing Manager',
	// 		'Content Writer',
	// 		'Ecommerce Specialist',
	// 	];

	// 	$userRole = auth()->user() ? auth()->user()->getRoleNames()->first() : null;
	// 	$isContentEnabled = $userRole && in_array($userRole, $contentAllowedRoles);

	// 	return response()->json([
	// 		'success' => true,
	// 		'message' => 'Product detail',
	// 		'product' => $formattedProduct,
	// 		'categories_hierarchy' => $formattedCategories,
	// 		'admin_reviews' => $adminReviews,
	// 		'faq' => $faqs ?? [],
	// 		'is_content_enabled' => $isContentEnabled,
	// 	]);
	// }
	public function show($productId, Request $request)
{
    $locale = in_array($request->locale ?? 'en', ['ar', 'en']) ? ($request->locale ?? 'en') : 'en';

    $attributeGroup = [
        'General' => ['sku', 'barcode', 'status', 'approved', 'ar_approved'],
        'Inventory & Stock Management' => ['stock_status'],
        'Pricing & Sales' => ['tax_id', 'currency_id', 'approved_by'],
        'Marketing' => ['name', 'description', 'gen_type', 'quote_available'],
        'Media' => ['images', 'video_path', 'documents', 'benefits_features'],
        'Store & Vendor Information' => ['brand_id'],
        'Performance & Analytics' => ['views', 'units_sold'],
        'Other' => ['order', 'website_ids'],
        'All' => []
    ];

    $attributeGroup['All'] = array_merge(...array_values(array_filter($attributeGroup, fn($key) => $key !== 'All', ARRAY_FILTER_USE_KEY)));

    $relations = [
        'General' => ['categories:id,name,parent_id'],
        'Pricing & Sales' => ['currency:id,title'],
        'Shipping & Dimensions' => [],
        'Store & Vendor Information' => ['brand:id,name', 'creator:id,name'],
        'Pricing' => ['vendors:id,name'],
        'All' => ['categories:id,name,parent_id', 'currency:id,title', 'brand:id,name', 'creator:id,name']
    ];

    $attrType = $request->attr_type ?? 'All';
    $attributes = $attributeGroup[$attrType] ?? $attributeGroup['All'];

    $with = array_merge($relations[$attrType] ?? [], [
        'categories:id,name,parent_id',
        'categories.parent:id,name,parent_id',
        'categories.parent.parent:id,name,parent_id',
        'categories.children:id,name,parent_id',
        'vendors',
        'productAttributes.attributeDetails',
        'productAttributes.measurementUnit',
        'productSuppliers.vendor'
    ]);

    $product = Product::with($with)->where('id', $productId)->first(array_merge(['id'], $attributes));

    if (!$product) {
        return response()->json([
            'success' => false,
            'message' => 'Product does not exist.'
        ]);
    }

    // Use first supplier for main product fields
    $firstSupplier = $product->productSuppliers->first();

    $formattedProduct = [];
    $formattedProduct['categories'] = [];

    // Build nested category structure
    foreach ($product->categories as $category) {
        $chain = [];
        $current = $category;
        while ($current) {
            $chain[] = $current;
            $current = $current->parent;
        }
        $chain = array_reverse($chain);

        $ref = &$formattedProduct['categories'];
        foreach ($chain as $cat) {
            $found = false;
            foreach ($ref as &$item) {
                if ($item['id'] == $cat->id) {
                    $ref = &$item['children'];
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $new = ['id' => $cat->id, 'name' => $cat->name, 'children' => []];
                $ref[] = $new;
                $ref = &$ref[array_key_last($ref)]['children'];
            }
        }
        unset($ref);
    }

    // Price & inventory fields from first supplier
    $formattedProduct['price'] = $firstSupplier->price ?? null;
    $formattedProduct['sale_price'] = $firstSupplier->sale_price ?? null;
    $formattedProduct['delivery_days'] = $firstSupplier->delivery_days ?? null;
    $formattedProduct['inventory'] = $firstSupplier->inventory ?? null;
    $formattedProduct['in_stock'] = $firstSupplier->in_stock ?? null;

    // Product attributes
    $formattedProduct['product_attributes'] = $product->productAttributes->map(function ($attr) {
        return [
            'attribute_id' => $attr->attribute_id,
            'attribute_name' => $attr->attributeDetails->name ?? null,
            'attribute_value' => $attr->attribute_value,
            'measurement_unit_id' => $attr->measurement_unit_id,
            'measurement_unit_name' => $attr->measurementUnit->name ?? null,
        ];
    });

    // Product suppliers
    $formattedProduct['product_suppliers'] = $product->productSuppliers->map(function ($ps) {
        return [
            'id' => $ps->id,
            'product_id' => $ps->product_id,
            'vendor_id' => $ps->vendor_id,
            'vendor_sku' => $ps->vendor_sku,
            'total_cost_per_item' => $ps->total_cost_per_item,
            'inventory' => $ps->inventory,
            'in_stock' => $ps->in_stock,
            'delivery_days' => $ps->delivery_days,
            'vendor_name' => $ps->vendor->name ?? null,
            'dropshipping' => $ps->vendor->dropshipping ?? null,
        ];
    });

    // Translation fields
    $translation = $product->translations->firstWhere('locale', $locale);
    // foreach ($attributes as $attribute) {
    //     $value = $product->$attribute ?? null;

    //     switch ($attribute) {
    //         case 'name':
    //         case 'description':
    //         case 'benefits_features':
    //         case 'images':
    //             $field = $attribute . '_tr';
    //             $value = $translation ? $translation->$field ?? $value : $value;
    //             $formattedProduct[$attribute] = is_array($value) ? $value : json_decode($value, true);
    //             break;

    //         case 'stock_status':
    //             $stockStatusMappings = [
    //                 'in_stock' => 'In Stock',
    //                 'out_of_stock' => 'Out of Stock',
    //                 'on_backorder' => 'Pre Order'
    //             ];
    //             $selectedStockStatus = $stockStatusMappings[$value] ?? $value;
    //             $formattedProduct['stock_status'] = [
    //                 'selected' => $selectedStockStatus,
    //                 'values' => $stockStatusMappings
    //             ];
    //             break;

    //         case 'tax_id':
    //             $tax = Tax::find($value);
    //             $formattedProduct['tax'] = $tax ? [['title' => $tax->title, 'rate' => $tax->percentage]] : [['title' => null, 'rate' => null]];
    //             break;

    //         case 'currency_id':
    //             $formattedProduct['currency'] = $product->currency ? [['id' => $product->currency->id, 'title' => $product->currency->title]] : null;
    //             break;

    //         case 'brand_id':
    //             $formattedProduct['brand'] = $product->brand ? [['id' => $product->brand->id, 'name' => $product->brand->name]] : null;
    //             break;

    //         default:
    //             if (!in_array($attribute, ['categories', 'productAttributes'])) {
    //                 $formattedProduct[$attribute] = $value;
    //             }
    //             break;
    //     }
    // }
	foreach ($attributes as $attribute) {
			$value = $product->$attribute ?? null;

			switch ($attribute) {

				case 'name':
				$field = $attribute . '_tr';
				$value = $translation ? $translation->$field : $value;
				$formattedProduct[$attribute] = $value;
				break;

				case 'benefits_features':
				case 'description':
				case 'images':
				$field = $attribute . '_tr';
				$value = $translation ? $translation->$field : $value;
				$formattedProduct[$attribute] = is_array($value) ? $value : json_decode($value, true);
				break;

				case 'refund':
				$formattedProduct[$attribute] = [['value' => $value]];
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
				$formattedProduct['currency'] = $product->currency ? [
					[
						'id' => $product->currency->id,
						'title' => $product->currency->title
					]
				] : null;
				break;

				case 'brand_id':
				$formattedProduct['brand'] = $product->brand ? [
					[
						'id' => $product->brand->id,
						'name' => $product->brand->name
					]
				] : null;
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

				case 'video_path':
				case 'documents':
				$formattedProduct[$attribute] = is_array($value) ? $value : [];
				break;

				case 'status':
				$formattedProduct[$attribute] = [['value' => $value]];
				break;

				default:
				$formattedProduct[$attribute] = $value;
				break;
			}
		}
    // Admin reviews
    $adminReviews = Review::where('product_id', $productId)->whereNull('customer_id')->get();

    // FAQs
    $faqs = FAQ::where('product_id', $productId)->get()->each(function ($faq) use ($locale) {
        $translation = $faq->translations->firstWhere('locale', $locale);
        $faq->question = $translation?->question_tr ?? $faq->question;
        $faq->answer = $translation?->answer_tr ?? $faq->answer;
        unset($faq->translations, $faq->question_tr, $faq->answer_tr);
    });

    // Content enabled check
    $contentAllowedRoles = ['Super Admin', 'Admin', 'Content Writing Manager', 'Content Writer', 'Ecommerce Specialist'];
    $userRole = auth()->user() ? auth()->user()->getRoleNames()->first() : null;
    $isContentEnabled = $userRole && in_array($userRole, $contentAllowedRoles);

    return response()->json([
        'success' => true,
        'message' => 'Product detail',
        'product' => $formattedProduct,
        'categories_hierarchy' => $formattedProduct['categories'],
        'admin_reviews' => $adminReviews,
        'faq' => $faqs ?? [],
        'is_content_enabled' => $isContentEnabled,
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
	 *                 @OA\Property(property="quote_available", type="boolean", example=true),
	 *                 @OA\Property(property="vendor_id", type="integer", example=7),
	 *                 @OA\Property(property="brand_id", type="integer", example=13),
	 *                 @OA\Property(property="views", type="integer", example=200),
	 *                 @OA\Property(property="units_sold", type="integer", example=50),
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
	public function update(Request $request, $productId)
	{

		/* Handle FAQs with content writer permission check */
		$locale = $request->locale ?? 'en';
		$imagePath = 'production/products';
		$videoPath = 'production/videos';
		$documentPath = 'production/documents';

		/* Log the incoming request for debugging */
		$product = Product::find($productId);

		if (!$product) {
			return response()->json([
				'success' => false,
				'message' => 'Product does not exist.'
			]);
		}

		$user = auth()->user();
		$userRole = $user ? $user->getRoleNames()->first() : null;

		// Restriction for approved products
		// if ($product->approved == 1 && !in_array($userRole, ['Super Admin', 'Admin'])) {
		// 	return response()->json([
		// 		'success' => false,
		// 		'message' => 'This product is approved and can only be updated by Super Admin or Admin.'
		// 	], 403);
		// }

		// if ($product->ar_approved == 1 && !in_array($userRole, ['Super Admin', 'Admin'])) {
		// 	return response()->json([
		// 		'success' => false,
		// 		'message' => 'This product is approved and can only be updated by.'
		// 	], 403);
		// }
				// Restriction for approved products based on locale
		if ($locale === 'en') {
			// English content update
			if ($product->approved == 1 && !in_array($userRole, ['Super Admin', 'Admin'])) {
				return response()->json([
					'success' => false,
					'message' => 'This product (English version) is approved and can only be updated by Super Admin or Admin.'
				], 403);
			}
		} elseif ($locale === 'ar') {
			// Arabic content update
			if ($product->ar_approved == 1 && !in_array($userRole, ['Super Admin', 'Admin'])) {
				return response()->json([
					'success' => false,
					'message' => 'This product (Arabic version) is approved and can only be updated by Super Admin or Admin.'
				], 403);
			}
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
			'Content Writer',
			'Ecommerce Specialist',
		];
		$canModifyContent = $userRole && in_array($userRole, $contentAllowedRoles);

		/* Handle categories - IMPROVED VERSION */
		if ($request->has('categories')) {

			$categories = $request->input('categories');

			// Handle cases where categories might be sent as a JSON string
			if (
				is_string($categories) && (
					strpos($categories, '[') === 0 ||
					strpos($categories, '{') === 0
				)
			) {
				$categories = json_decode($categories, true);
			}
			// Handle comma-separated string format
			else if (is_string($categories) && strpos($categories, ',') !== false) {
				$categories = array_map('trim', explode(',', $categories));
			}
			// Handle single value
			else if (is_string($categories) && is_numeric($categories)) {
				$categories = [(int) $categories];
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

		
		/* Handle multilingual product attributes with sync */
		if ($request->has('product_attributes') && !empty($request->input('product_attributes')) ) {
			$productAttributes = $request->input('product_attributes', []);

			/* Decode JSON if string */
			if (is_string($productAttributes)) {
				$decoded = json_decode($productAttributes, true);
				if (json_last_error() !== JSON_ERROR_NONE) {
					return response()->json([
						'success' => false,
						'message' => 'Invalid JSON format for product attributes.'
					], 400);
				}
				$productAttributes = is_array($decoded) ? $decoded : [];
			}

			if (!is_array($productAttributes)) {
				return response()->json([
					'success' => false,
					'message' => 'No valid product attributes provided.'
				], 400);
			}

			/* Filter out null/empty values at the root level */
			$productAttributes = array_filter($productAttributes, function ($value) {
				if (is_array($value)) {
					return !empty($value['value']) || !empty($value['measurement_id']);
				}
				return !is_null($value) && $value !== '';
			});

			$updatedAttributeIds = [];

			foreach ($productAttributes as $attributeId => $attributeValue) {
				/* Validate attribute exists */
				$existingAttribute = Attribute::find($attributeId);
				if (!$existingAttribute) {
					return response()->json([
						'success' => false,
						'message' => "Attribute ID: $attributeId does not exist."
					], 400);
				}

				$productAttribute = null;
				$value = null;
				$measurementUnitID = null;

				/* Handle measurement type attributes */
				if ($existingAttribute->type == 'measurement' && is_array($attributeValue)) {
					$value = $attributeValue['value'] ?? null;
					$measurementUnitID = $attributeValue['measurement_id'] ?? null;

					/* Validation: Either both should be present, or both should be empty */
					if (($value && !$measurementUnitID) || (!$value && $measurementUnitID)) {
						$messages = [];
						if (empty($value)) {
							$messages[] = "Value not defined for attribute: {$existingAttribute->name}";
						}
						if (empty($measurementUnitID)) {
							$messages[] = "Measurement Unit not defined or invalid for attribute: {$existingAttribute->name}";
						}
						return response()->json([
							'success' => false,
							'message' => implode(' | ', $messages)
						], 400);
					}

					/* Skip if both are empty (will be deleted later in sync) */
					if (empty($value) && empty($measurementUnitID)) {
						continue;
					}
				} else {
					/* Handle regular attributes */
					$value = $attributeValue;

					/* Skip if empty (will be deleted later in sync) */
					if (empty($value)) {
						continue;
					}
				}

				/* Find existing product attribute */
				$productAttribute = $product->productAttributes()
				->where('attribute_id', $attributeId)
				->first();

				/* Create new product attribute if not found */
				if (!$productAttribute) {
					$productAttribute = $product->productAttributes()->create([
						'attribute_id' => $attributeId,
						'attribute_value' => $locale === 'en' ? $value : 'NA',
						'measurement_unit_id' => $measurementUnitID,
					]);
				} else {
					/* Update base table fields only if locale is en */
					if ($locale === 'en') {
						$productAttribute->update([
							'attribute_value' => $value,
							'measurement_unit_id' => $measurementUnitID,
						]);
					}
				}

				/* Update translation for current locale */
				if (in_array(config('app.website'), ['UAE', 'UAE_T', 'SA'])) {
					$productAttribute->translateOrNew('en')->attribute_value_tr = $value;
				}

				$productAttribute->save();
				$updatedAttributeIds[] = $productAttribute->attribute_id;

				/* Handle select type - auto-create new attribute values with translations */
				if ($existingAttribute->type === 'select') {
					$attributeValue = $existingAttribute->attributeValues()->firstOrCreate([
						'attribute_value' => $value
					]);

					if (in_array(config('app.website'), ['UAE', 'UAE_T', 'SA'])) {
						$attributeValue->translateOrNew('en')->attribute_value_tr = $value;
						$attributeValue->save();
					}
				}
			}

			/* Delete any existing product attributes not in updatedAttributeIds */
			$product->productAttributes()
			->whereNotIn('attribute_id', $updatedAttributeIds)
			->get()
			->each(function ($productAttribute) {
				$productAttribute->translations()->delete();
				$productAttribute->delete();
			});
		}

		if ($canModifyContent) {
			/* Handle multilingual product description */
			if ($request->has('name')) {
				$updatedName = $request->input('name', '');
				/* use translated table */
				$existingName = optional($product->translate($locale))->name ?? [];

				/* Only save if changed */
				if ($updatedName !== $existingName) {
					if ($locale === 'en') {
						$product->name = $updatedName;
					}

					if (in_array(config('app.website'), ['UAE', 'UAE_T', 'SA'])) {
						$product->translateOrNew($locale)->name_tr = $updatedName;
					}
					$product->save();
				}
			}

			/* Handle multilingual product description */
			if ($request->has('description') && !empty($request->input('description'))) {
				$descriptions = $request->input('description', []);
				$updatedDescriptions = [];

				/* Decode if JSON string provided */
				if (is_string($descriptions)) {
					$decoded = json_decode($descriptions, true);
					if (json_last_error() !== JSON_ERROR_NONE) {
						return response()->json([
							'success' => false,
							'message' => 'Invalid JSON format for description.'
						], 400);
					}
					$updatedDescriptions = $decoded;
				} elseif (is_array($descriptions)) {
					$updatedDescriptions = $descriptions;
				} else {
					return response()->json([
						'success' => false,
						'message' => 'Invalid description format. Must be JSON string or array.'
					], 400);
				}

				/* use translated table */
				$existingDescriptions = json_decode(optional($product->translate($locale))->description, true) ?? [];

				/* Only save if changed */
				if ($updatedDescriptions !== $existingDescriptions) {
					$jsonEncoded = json_encode($updatedDescriptions);

					if ($locale === 'en') {
						$product->description = $jsonEncoded;
					}

					if (in_array(config('app.website'), ['UAE', 'UAE_T', 'SA'])) {
						$product->translateOrNew($locale)->description_tr = $jsonEncoded;
					}

					$product->save();
				}
			}

			/* Handle multilingual benefits & features */
			if ($request->has('benefits_features') && !empty($request->input('benefits_features'))) {
				$benefitsFeatures = $request->input('benefits_features', []);
				$updatedBenefitsFeatures = [];

				/* Decode if JSON string provided */
				if (is_string($benefitsFeatures)) {
					$decoded = json_decode($benefitsFeatures, true);
					if (json_last_error() !== JSON_ERROR_NONE) {
						return response()->json([
							'success' => false,
							'message' => 'Invalid JSON format for benefits_features.'
						], 400);
					}
					$updatedBenefitsFeatures = $decoded;
				} elseif (is_array($benefitsFeatures)) {
					$updatedBenefitsFeatures = $benefitsFeatures;
				} else {
					return response()->json([
						'success' => false,
						'message' => 'Invalid benefits_features format. Must be JSON string or array.'
					], 400);
				}

				/* use translated table */
				$existingBenefitsFeatures = json_decode(optional($product->translate($locale))->benefits_features, true) ?? [];

				/* Only save if changed */
				if ($updatedBenefitsFeatures !== $existingBenefitsFeatures) {
					$jsonEncoded = json_encode($updatedBenefitsFeatures);

					if ($locale === 'en') {
						$product->benefits_features = $jsonEncoded;
					}

					if (in_array(config('app.website'), ['UAE', 'UAE_T', 'SA'])) {
						$product->translateOrNew($locale)->benefits_features_tr = $jsonEncoded;
					}
					$product->save();
				}
			}
			/* Handle multilingual FAQs with sync */
			if ($request->has('faqs')) {
				$faqs = $request->input('faqs', []);

				/* Decode JSON if string */
				if (is_string($faqs)) {
					$decoded = json_decode($faqs, true);
					if (json_last_error() !== JSON_ERROR_NONE) {
						return response()->json([
							'success' => false,
							'message' => 'Invalid JSON format for FAQs.'
						], 400);
					}
					$faqs = is_array($decoded) && isset($decoded[0])
					? $decoded
					: ($decoded['faqs'] ?? []);
				}

				if (!is_array($faqs)) {
					return response()->json([
						'success' => false,
						'message' => 'No valid FAQs provided.'
					], 400);
				}

				$updatedFaqIds = [];

				foreach ($faqs as $faqData) {
					if (empty($faqData['question']) || empty($faqData['answer'])) {
						continue; // skip invalid
					}

					$faq = null;

					/* Update existing if id provided */
					if (!empty($faqData['id'])) {
						$faq = $product->faqs()->where('id', $faqData['id'])->first();
					}

					/* Create new FAQ if not found */
					if (!$faq) {
						$faq = $product->faqs()->create([
							'question' => $locale === 'en' ? $faqData['question'] : 'NA',
							'answer' => $locale === 'en' ? $faqData['answer'] : 'NA',
							'category_id' => $faqData['category_id'] ?? null,
							'status' => 'published',
						]);
					} else {
						/* Update base table fields only if locale is en */
						if ($locale === 'en') {
							$faq->update([
								'question' => $faqData['question'],
								'answer' => $faqData['answer'],
								'category_id' => $faqData['category_id'] ?? $faq->category_id,
								'status' => 'published',
							]);
						}
					}

					/* Update translation for current locale */
					if (in_array(config('app.website'), ['UAE', 'UAE_T', 'SA'])) {
						$faq->translateOrNew($locale)->question_tr = $faqData['question'];
						$faq->translateOrNew($locale)->answer_tr = $faqData['answer'];
					}
					$faq->save();

					$updatedFaqIds[] = $faq->id;
				}

				/* Delete any existing FAQ not in updatedFaqIds */
				$product->faqs()->whereNotIn('id', $updatedFaqIds)->get()->each(
					function ($faq) {
						$faq->delete();
					}
				);
			}
		}

		if ($canModifyImages) {
			if ($request->has('images') && !empty(array_filter($request->images))) {
			 
				$updatedImages = [];
				$manager = new ImageManager(new Driver()); // ✅ Initialize once

				foreach ($request->images as $key => $image) {
					if (is_string($image) && filter_var($image, FILTER_VALIDATE_URL)) {
						$updatedImages[] = $image;
					} elseif ($request->hasFile("images.$key")) {
						$file = $request->file("images.$key");

						$img = $manager->read($file->getRealPath())->scale(width: 1000); /* keep aspect ratio, max width 1000px */

						/* Dynamically adjust quality to keep under 100 KB */
						$quality = 90;
						do {
							$encoded = $img->toWebp($quality);
							$size = strlen($encoded);
							$quality -= 5;
						} while ($size > 102400 && $quality > 10);

						$tempPath = sys_get_temp_dir() . '/' . uniqid('', true) . '.webp';
						file_put_contents($tempPath, $encoded);

						$path = Storage::disk('s3')->putFile($imagePath, new \Illuminate\Http\File($tempPath));
						$updatedImages[] = Storage::disk('s3')->url($path);

						@unlink($tempPath);
					}
				}

				/* use translated table */
				$existingImages = json_decode(optional($product->translate($locale))->images, true) ?? [];

				/* Only save if changed */
				if ($updatedImages !== $existingImages) {
					$jsonEncoded = json_encode($updatedImages);

					if ($locale === 'en') {
						$product->images = $jsonEncoded;
					}

					if (in_array(config('app.website'), ['UAE', 'UAE_T', 'SA'])) {
						$product->translateOrNew($locale)->images_tr = $jsonEncoded;
					}
					$product->save();
				}
			}
		}

		/* Get all input data except '_method' */
		$input = $request->except('_method', 'status');
		/* Remove 'faqs' from the input before validation */

		$fieldsToUnset = ['categories', 'name', 'benefits_features', 'description', 'faqs', 'images'];

		foreach ($fieldsToUnset as $field) {
			unset($input[$field]);
		}

		// Handle videos with role-based permission - CORRECTED VERSION
		if ($request->has('video_path')) {
			if ($canModifyImages) {
				$finalVideos = [];
				$videoPaths = is_array($request->video_path) ? $request->video_path : [$request->video_path];
				foreach ($videoPaths as $key => $video) {
					if (is_string($video) && filter_var($video, FILTER_VALIDATE_URL)) {
						// It's a URL, keep as is
						$finalVideos[] = $video;
					} elseif ($request->hasFile("video_path.$key")) {
						// It's an uploaded file, upload to S3
						$file = $request->file("video_path.$key");
						// ✅ Check file size (max 1 MB = 1024 KB = 1048576 bytes)
						if ($file->getSize() > 20971520) { // 20 MB = 20 * 1024 * 1024
							return response()->json([
								'success' => false,
								'message' => 'Video size must not exceed 20 MB.'
							], 422);
						}

						$path = $file->store($videoPath, 's3');
						$finalVideos[] = Storage::disk('s3')->url($path);
					}
					// ignore invalid inputs
				}

				$input['video_path'] = json_encode($finalVideos);
			} else {
				// User tried to modify videos but doesn't have permission - check if they're uploading files
				$hasNewVideoFiles = false;
				$videoPaths = is_array($request->video_path) ? $request->video_path : [$request->video_path];
				foreach ($videoPaths as $key => $video) {
					if ($request->hasFile("video_path.$key")) {
						$hasNewVideoFiles = true;
						break;
					}
				}

				if ($hasNewVideoFiles) {
					return response()->json([
						'success' => false,
						'message' => 'You do not have permission to modify product videos.'
					], 403);
				}

				// Remove from input to prevent overwriting existing videos
				unset($input['video_path']);
			}
		} else {
			// ✅ CRITICAL: If videos not in request, preserve existing videos
			unset($input['video_path']);
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
		$input['documents'] = json_encode($input['documents']);

		/* List of valid fields allowed for updating */
		$validArray = ["sku", "status", "barcode", "tax_id", "currency_id", "name", "description", "video_path", "documents", "brand_id", "views", "units_sold", "order", "benefits_features", "gen_type", "approved" , "ar_approved"];

		unset($input['product_attributes']);
		unset($input['vendor_id']);

		$input = array_intersect_key($input, array_flip($validArray));

		/* Initialize an error array to store validation errors */
		$rowError = [];

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

		$product->quote_available = $request->quote_available;
		/* Save the product */
		$product->save();

		if (isset($request->status)) {
			$validStatuses = ['draft', 'published', 'pending', 'awaiting Price', 'temporary out of stock'];

			if (!in_array($request->status, $validStatuses)) {
				return response()->json([
					'success' => false,
					'message' => "Invalid status value. Allowed values: draft, published, pending."
				]);
			}

			if ($request->status === 'published') {

				// Reload full product with required relationships
				$product = Product::with(['productAttributes', 'sellingUnitAttribute', 'productSuppliers'])->find($product->id);
				$rowError = [];

				/* Validate images */
				$images = is_array($product->images) ? $product->images : json_decode($product->images, true);
				if (empty($images) || count($images) === 0) {
					$rowError[] = "At least one product image is required to publish.";
				}

				/* Validate benefits */
				$benefits = is_array($product->benefits_features) ? $product->benefits_features : json_decode($product->benefits_features, true);
				if (empty($benefits) || count($benefits) < 5) {
					$rowError[] = "At least 5 benefits & features are required to publish.";
				}

				/* Validate attributes */
				if ($product->productAttributes->count() < 5) {
					$rowError[] = "At least 5 product attributes are required to publish.";
				}

				/* Validate selling unit */
				if (!$product->sellingUnitAttribute) {
					$rowError[] = "The 'Selling Unit' attribute is required to publish.";
				}

				/* Validate product suppliers */
				if ($product->productSuppliers->isEmpty()) {
					$rowError[] = "At least one vendor price detail is required to publish.";
				}

				if ($product->productSuppliers->contains(fn($supplier) => $supplier->in_stock !== 1)) {
					$rowError[] = "All vendor price entries must have 'in_stock' set to Yes.";
				}

				if (!empty($rowError)) {
					return response()->json([
						'success' => false,
						'message' => implode(', ', $rowError)
					]);
				}
			}

			/* Passed all validations, now update the status */
			$product->status = $request->status;
			$product->save();
		}

		$product = Product::find($product->id);

		/* Return success response */
		return response()->json([
			'success' => true,
			'message' => 'Product updated successfully.',
			'product' => $product->load('productAttributes:id,product_id,attribute_id,attribute_value'),
			'faq' => $faqs ?? null,
		]);
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
			// $discountSectionArray = product_import_constants('DISCOUNT_SECTION');

			$userRole = auth()->user()->getRoleNames()->first() ?? null;

			if (empty($userRole) || in_array($userRole, ['Super Admin', 'Admin'])) {
				$productFileFormatArray = array_merge(
					$idArray,
					$urlArray,
					$generalFieldArray,
					$descriptionSectionArray,
					$benefitSectionArray,
					$faqSectionArray,
					$advanceFieldArray,
					// $discountSectionArray,
				);
			} elseif (in_array($userRole, ['Ecommerce Manager', 'Ecommerce Specialist'])) {
				$productFileFormatArray = array_merge(
					$idArray,
					$generalFieldArray,
					$advanceFieldArray
				);

			} elseif (in_array($userRole, ['Content Writing Manager', 'Content Writer'])) {
				$productFileFormatArray = array_merge(
					$idArray,
					$generalFieldArray,
					$descriptionSectionArray,
					$benefitSectionArray,
					$faqSectionArray,
				);
			}

			$excelImporter->processExcelImport(
				$request->file('upload_file'),
				$productFileFormatArray,
				'Product', /* Module name */
				config('app.website') . '_PRODUCT', /* Job name */
				'Import Products', /* Batch name */
				ImportProductJob::class
			);

			return response()->json([
				'success' => true,
				'message' => 'The import process has been scheduled successfully. Please track it under import log.'
			]);
		} catch (\Exception $exception) {
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
		$formattedProducts = $products->map(function ($product) use ($category_id) {
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

	/**
	 * @OA\Post(
	 *     path="/api/products/duplicate",
	 *     summary="Create a product duplicate",
	 *     description="Creates a new product with the required details.",
	 *     tags={"Products"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"product", "sku"},
	 *             @OA\Property(property="product", type="string", example="12345"),
	 *             @OA\Property(property="sku", type="string", example="SKU-NEW-001")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=201,
	 *         description="Success",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="message", type="string", example="Product duplicated successfully"),
	 *             @OA\Property(property="new_product_id", type="integer", example=6789)
	 *         )
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function productDuplicate(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'product' => 'required|exists:ec_products,id',
			'sku' => "required",
		]);

		if ($validator->fails()) {
			return response()->json([
				'success' => false,
				'message' => 'Validation failed',
				'errors' => $validator->errors()
			], 422);
		}

		$locale = $request->locale ?? 'en';
		try {
			$mainProduct = Product::findOrFail(trim($request->input('product')));
			if (!empty($mainProduct)) {
				$checkSku = Product::where('sku', $request->input('sku'))->count();

				if (!$checkSku) {
					$product = new Product();
					$product->name = $mainProduct->name;
					$product->description = $mainProduct->description;
					$product->benefits_features = $mainProduct->benefits_features;
					$product->images = $mainProduct->images;

					if (in_array(config('app.website'), ['UAE', 'UAE_T', 'SA'])) {
						$product->translateOrNew($locale)->name_tr = $mainProduct->name;
						$product->translateOrNew($locale)->description_tr = $mainProduct->description;
						$product->translateOrNew($locale)->benefits_features_tr = $mainProduct->benefits_features;
						$product->translateOrNew($locale)->images_tr = $mainProduct->images;
					}

					$product->sku = $request->input('sku');
					$product->website_ids = $mainProduct->website_ids;
					$product->gen_type = $mainProduct->gen_type;
					$product->order = '0';
					$product->is_featured = $mainProduct->is_featured;
					$product->brand_id = $mainProduct->brand_id;
					$product->quote_available = $mainProduct->quote_available;
					$product->tax_id = $mainProduct->tax_id;
					$product->views = '0';
					$product->stock_status = $mainProduct->stock_status;
					$product->barcode = $mainProduct->barcode;
					$product->approved_by = $mainProduct->approved_by;
					$product->documents = $mainProduct->documents;
					$product->video_path = $mainProduct->video_path;
					$product->units_sold = $mainProduct->units_sold;
					$product->frequently_bought_together = $mainProduct->frequently_bought_together;
					$product->currency_id = $mainProduct->currency_id;
					$product->status = 'draft';
					$product->created_at = now();
					$product->updated_at = now();
					$product->save();
					if (!empty($mainProduct->categories)) {
						$categoryId = $mainProduct->categories->pluck('id')->toArray();
						$this->saveProductCategory($product, $categoryId[0]);
					}

					if ($mainProduct->productAttributes) {

						$productAttributes = $mainProduct->productAttributes->pluck('attribute_value', 'attribute_id')->toArray();
						if (is_array($productAttributes) && count($productAttributes) > 0) {
							$productAttributes = array_filter($productAttributes, function ($value) {
								return !is_null($value) && $value !== '';
							});

							$existingProductAttributes = $mainProduct->productAttributes->pluck('attribute_value', 'attribute_id')->toArray();

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

									/* Validation: Either both should be present, or both should be empty (for delete) */
									if (($value && !$measurementUnitID) || (!$value && $measurementUnitID)) {
										$messages = [];

										if (empty($value)) {
											$messages[] = "Value not defined for attribute: {$existingAttribute->name}";
										}
										if (empty($measurementUnitID)) {
											$messages[] = "Measurement Unit not defined or invalid for attribute: {$existingAttribute->name}";
										}

										return response()->json([
											'success' => false,
											'message' => implode(' | ', $messages)
										], 400);
									}

									if (!$value && !$measurementUnitID) {
										/* Both missing = delete the existing attribute */
										$product->productAttributes()
										->where('attribute_id', $attributeId)
										->delete();
									} else {
										/* Both exist = update or create attribute */
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

					if ($mainProduct->faqs) {
						$faqs = $mainProduct->faqs;
						// Fetch existing FAQs for comparison
						$existingFaqs = Faq::where('product_id', $mainProduct->id)->get()->keyBy('id');

						if (!empty($faqs) && !empty($existingFaqs)) {
							foreach ($faqs as $faqData) {
								if (!empty($faqData['question']) && !empty($faqData['answer'])) {

									Faq::create([
										'product_id' => $product->id,
										'question' => $faqData['question'],
										'answer' => $faqData['answer'],
										'category_id' => $faqData['category_id'] ?? null,
										'status' => 'published',
									]);
								}
							}
						}
					}

					$firstSupplier = $mainProduct->productSuppliers->first();

					if (!empty($firstSupplier)) {

						/* Check if a record with the same SKU and vendor_id already exists */
						$existingEntry = ProductSupplier::where('product_id', $product->id)
						->where('vendor_id', $firstSupplier->vendor_id)
						->first();

						if (!$existingEntry) {

							$data = [];
							$rowErrors = [];


							$data['product_id'] = $product->id;
							$data['vendor_id'] = $firstSupplier->vendor_id;
							$data['vendor_sku'] = $product->sku;
							$data['list_price'] = $firstSupplier->list_price;
							$data['cost_per_item'] = $firstSupplier->cost_per_item ?? 0;

							$data['multiple'] = $firstSupplier->multiple ?? null;
							$data['surcharge'] = $firstSupplier->surcharge ?? 0;
							$data['additional_cost'] = $firstSupplier->additional_cost ?? 0;
							$data['map'] = $firstSupplier->map ?? null;
							$data['sale_price'] = $firstSupplier->sale_price ?? null;
							$data['price'] = $firstSupplier->price ?? 0;
							$data['inventory'] = $firstSupplier->inventory ?? 0;

							$data['in_stock'] = $firstSupplier->in_stock ?? 'Yes';
							$data['min_quantity'] = $firstSupplier->min_quantity ?? 1;
							$data['is_fixed'] = $firstSupplier->is_fixed ?? 'Yes';

							$data['delivery_days'] = $firstSupplier->delivery_days ?? '';
							$data['return_policy'] = $firstSupplier->return_policy ?? '';
							$data['free_shipping'] = $firstSupplier->free_shipping ?? '0';
							$data['shipping_charge'] = $firstSupplier->shipping_charge ?? 0;
							$data['warranty_information'] = $firstSupplier->warranty_information ?? null;
							$data['restocking_fees'] = $firstSupplier->restocking_fees ?? 0;

							/* --- Calculate cost_per_item --- */
							if (!empty($data['list_price']) && !empty($data['multiple'])) {
								$data['cost_per_item'] = (float) $data['list_price'] * (float) $data['multiple'];
							}

							$data['surcharge'] = !empty($data['surcharge'])
							? $data['cost_per_item'] * ((float) $data['surcharge'] / 100)
							: 0;

							$data['additional_cost'] = !empty($data['additional_cost'])
							? $data['cost_per_item'] * ((float) $data['additional_cost'] / 100)
							: 0;

							$data['total_cost_per_item'] = $data['cost_per_item'] + $data['surcharge'] + $data['additional_cost'];

							/* --- Price & Sale Price fallback --- */
							$data['sale_price'] = !empty($data['sale_price']) ? (float) $data['sale_price'] : null;
							$data['price'] = !empty($data['price']) ? (float) $data['price'] : 0;

							/* --- Margin calculation --- */
							if (!empty($data['sale_price']) && $data['sale_price'] > 0) {
								$data['margin'] = (($data['sale_price'] - $data['total_cost_per_item']) / $data['sale_price']) * 100;
							} elseif ($data['price'] > 0) {
								$data['margin'] = (($data['price'] - $data['total_cost_per_item']) / $data['price']) * 100;
							} else {
								$data['margin'] = null;
							}

							/* --- Stock flags --- */
							$data['in_stock'] = ($data['inventory'] > 0 || strtolower($data['in_stock']) === 'yes') ? 1 : 0;
							$data['is_fixed'] = strtolower($data['is_fixed']) === 'yes' ? 1 : 0;
							$data['free_shipping'] = strtolower($data['free_shipping']) === 'yes' ? 1 : 0;
							$data['shipping_charge'] = $data['free_shipping'] == 1 ? 0 : $data['shipping_charge'];

							$data['created_by'] = auth()->id();


							$record = ProductSupplier::create($data);
						}

					}








					return response()->json([
						'success' => true,
						'message' => 'Product created successfully',
						'product' => $product
					]);

				} else {

					return response()->json([
						'success' => false,
						'message' => 'This SKU already exists',
						'product' => []
					]);
				}

			} else {

				return response()->json([
					'success' => false,
					'message' => 'Product id Not found',
					'product' => []
				]);
			}

		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => $e->getMessage(),
				'product' => []
			]);
		}
	}

	/**
	 * @OA\Post(
	 *     path="/api/products/delete-product-document",
	 *     summary="Delete a product document",
	 *     description="Deletes a document file related to the given product.",
	 *     tags={"Products"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             type="object",
	 *             @OA\Property(property="product_id", type="string", example="123"),
	 *             @OA\Property(property="document_path", type="string", example="https://horecastore-s3-storage.s3.us-west-1.amazonaws.com/production/documents/manual.pdf")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Document deleted successfully",
	 *         @OA\JsonContent(
	 *             type="object",
	 *             @OA\Property(property="status", type="string", example="success"),
	 *             @OA\Property(property="message", type="string", example="Document deleted")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=404,
	 *         description="Document not found"
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function deleteProductDocument(Request $request)
	{
		$request->validate([
			'product_id' => 'required|string',
			'document_path' => 'required|string'
		]);

		try {
			$productId = $request->input('product_id');
			$documentPath = $request->input('document_path');
			$product = Product::find($productId);

			if (!$product) {
				return response()->json([
					'success' => false,
					'error' => 'Product not found'
				], 404);
			}

			$currentDocuments = $product->documents ? json_decode($product->documents, true) : [];

			if (empty($currentDocuments)) {
				return response()->json([
					'success' => false,
					'error' => 'No documents found'
				], 404);
			}


			$found = false;
			foreach ($currentDocuments as $index => $document) {

				if ($document['path'] === $documentPath) {
					$found = true;

					// --- Delete from S3 ---
					// Example: if $documentPath is a full URL, extract the relative path
					// $s3Path = ltrim(parse_url($documentPath, PHP_URL_PATH), '/');
					// //$s3Path = $documentPath;
					// if (Storage::disk('s3')->exists($s3Path)) {
					// 	Storage::disk('s3')->delete($s3Path);
					// }
					// Remove from array
					unset($currentDocuments[$index]);
					break;
				}
			}

			if (!$found) {
				return response()->json([
					'success' => false,
					'error' => 'Document not found'
				], 404);
			}

			//Reindex array
			if (!empty($currentDocuments)) {
				$currentDocuments = array_values($currentDocuments);
				$product->documents = json_encode($currentDocuments);
			} else {
				$product->documents = "";
			}
			$product->save();

			return response()->json([
				'success' => true,
				'message' => 'Document deleted successfully',
				'documents' => $currentDocuments ? $currentDocuments : '',
			]);

		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'error' => $e->getMessage()
			], 500);
		}
	}



  /**
 * @OA\Post(
 *     path="/api/product/full-url",
 *     summary="Get full product URL (parent/category/product)",
 *     description="Returns the full SEO-friendly product URL using parent category, child category, and product slug based on APP_WEBSITE (UAE/US).",
 *     operationId="getStoreUrl",
 *     tags={"Products"},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"product_id"},
 *             @OA\Property(property="product_id", type="integer", example=1683)
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Full product URL generated successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="product_id", type="integer", example=1683),
 *             @OA\Property(property="store_url", type="string", example="https://www.thehorecastore.com/parent-category/sub-category/product-name")
 *         )
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Product not found",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="string", example="error"),
 *             @OA\Property(property="message", type="string", example="Product not found.")
 *         )
 *     ),
 *  security={{"bearerAuth":{}}}
 * )
 */
  public function getStoreUrl(Request $request)
  {
  	$validator = Validator::make($request->all(), [
  		'product_id' => 'required|integer|exists:ec_products,id',
  	]);

  	if ($validator->fails()) {
  		return response()->json([
  			'status' => 'error',
  			'message' => $validator->errors()->first(),
  		], 400);
  	}

	// Load only your required relations
		$product = Product::find($request->product_id); // no with()

		if (!$product) {
			return response()->json([
				'status' => 'error',
				'message' => 'Product not found',
			], 404);
		}

		// now you can safely call your methods
		$parentCategory = ltrim($product->parent_category_url() ?? '', '/');
		$childCategory  = ltrim($product->category_url() ?? '', '/');
		$productSlug    = ltrim(optional($product->seoProductUrl)->url ?? '', '/');


		if (!$product) {
			return response()->json([
				'status' => 'error',
				'message' => 'Product not found',
			], 404);
		}

	// Determine base domain from .env
		$appWebsite = env('APP_WEBSITE');
		$baseDomain = str_contains(strtoupper($appWebsite), 'UAE')
		? 'https://www.horecastore.ae'
		: 'https://www.thehorecastore.com';

	// Get each part of the URL
		$parentCategory = ltrim($product->parent_category_url() ?? '', '/');
		$childCategory  = ltrim($product->category_url() ?? '', '/');
		$productSlug    = ltrim(optional($product->seoProductUrl)->url ?? '', '/');

	// Build the full path
		$pathParts = array_filter([$parentCategory, $childCategory, $productSlug]);
		$fullPath = implode('/', $pathParts);

		$fullUrl = rtrim($baseDomain, '/') . '/' . $fullPath;

		return response()->json([
			'status' => 'success',
			'product_id' => $product->id,
			'store_url' => $fullUrl,
		]);
	}








}
