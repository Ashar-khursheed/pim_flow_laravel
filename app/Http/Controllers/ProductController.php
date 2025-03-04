<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Tax;
use App\Models\Currency;
use App\Models\Unit;
use App\Models\Store;
use App\Models\Brand;

class ProductController extends BaseController
{
	/**
	 * Display a listing of the resource.
	 */
	public function index()
	{
		//
	}

	/**
	 * Show the form for creating a new resource.
	 */
	public function create()
	{
	}

	/**
	 * Store a newly created resource in storage.
	 */
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
			'user' => $product
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
	 * Display the specified resource.
	 */
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
		$attributeGroups = [
			'General' => ['sku', 'barcode', 'warranty_information', 'refund'],
			'Inventory & Stock Management' => ['quantity', 'allow_checkout_when_out_of_stock', 'with_storehouse_management', 'stock_status', 'variant_inventory_tracker', 'variant_inventory_quantity', 'variant_inventory_policy', 'variant_fulfillment_service'],
			'Pricing & Sales' => ['price', 'sale_price', 'sale_type', 'cost_per_item', 'tax_id', 'currency_id', 'minimum_order_quantity', 'maximum_order_quantity', 'approved_by'],
			'Marketing' => ['name', 'content', 'description'],
			'Media' => ['images', 'image', 'video_url', 'video_path', 'documents'],
			'Shipping & Dimensions' => ['length', 'length_unit_id', 'width', 'height', 'depth', 'weight', 'weight_unit_id', 'shipping_weight_option', 'shipping_weight', 'shipping_dimension_option', 'shipping_width', 'shipping_depth', 'shipping_height', 'shipping_length', 'shipping_length_id'],
			'Product Variations' => ['is_variation', 'variant_grams', 'variant_requires_shipping', 'variant_barcode', 'variant_color_title', 'variant_color_value'],
			'Store & Vendor Information' => ['store_id', 'brand_id', 'created_by_id', 'created_by_type'],
			'Performance & Analytics' => ['views', 'units_sold', 'frequently_bought_together'],
			'Comparison & Bundling' => ['compare_type', 'compare_products'],
			'SEO' => ['google_shopping_category', 'google_shopping_mpn'],
			'Other' => ['order', 'box_quantity', 'delivery_days'],
			'All' => []
		];

		$attributeGroups['All'] = array_merge(...array_values(array_filter($attributeGroups, fn($key) => $key !== 'All', ARRAY_FILTER_USE_KEY)));

		$relations = [
			'General' => ['categories:id,name,parent_id'],
			'Pricing & Sales' => ['currency:id,title'],
			'Shipping & Dimensions' => ['lengthUnit:id,symbol', 'weightUnit:id,symbol', 'shippingLengthUnit:id,symbol'],
			'Store & Vendor Information' => ['store:id,name', 'brand:id,name', 'creator:id,name'],
			'SEO' => ['seoMetaData:id,reference_id,meta_value'],
			'All' => ['categories:id,name,parent_id', 'currency:id,title', 'lengthUnit:id,symbol', 'weightUnit:id,symbol', 'shippingLengthUnit:id,symbol', 'store:id,name', 'brand:id,name', 'creator:id,name', 'seoMetaData:id,reference_id,meta_value']
		];

		$attrType = $request->attr_type ?? 'All';
		$attributes = $attributeGroups[$attrType] ?? $attributeGroups['All'];
		$with = $relations[$attrType] ?? [];

		/* Fetch product with requested attributes and relations */
		$product = Product::with($with)->where('id', $productId)->first(array_merge(['id'], $attributes));

		/* Check if product exists */
		if (!$product) {
			return response()->json([
				'success' => false,
				'message' => 'Product does not exist.'
			]);
		}

		/* Decode images if stored as a JSON string */
		if (!empty($product->images) && is_string($product->images)) {
			$product->images = json_decode($product->images, true); // Ensure it's converted to an array
		}

		return response()->json([
			'success' => true,
			'message' => 'Product detail',
			'product' => $product
		]);
	}

	/**
	 * Show the form for editing the specified resource.
	 */
	public function edit(Product $product)
	{
		//
	}

	/**
	 * Update the specified resource in storage.
	 */
	/**
	 * @OA\Put(
	 *     path="/api/products/{product}",
	 *     summary="Update a product",
	 *     description="Updates an existing product based on the provided JSON payload.",
	 *     operationId="updateProduct",
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
	 *         @OA\JsonContent(
	 *             @OA\Property(property="sku", type="string", example="PROD-123"),
	 *             @OA\Property(property="barcode", type="string", example="9509297558375"),
	 *             @OA\Property(property="warranty_information", type="string", example="One Year Warranty"),
	 *             @OA\Property(property="refund", type="string", example="non-refundable"),
	 *             @OA\Property(property="quantity", type="integer", example=100),
	 *             @OA\Property(property="allow_checkout_when_out_of_stock", type="boolean", example=false),
	 *             @OA\Property(property="with_storehouse_management", type="boolean", example=true),
	 *             @OA\Property(property="stock_status", type="string", example="in_stock"),
	 *             @OA\Property(property="variant_inventory_tracker", type="string", example="shopify"),
	 *             @OA\Property(property="variant_inventory_quantity", type="integer", example=50),
	 *             @OA\Property(property="variant_inventory_policy", type="string", example="deny"),
	 *             @OA\Property(property="variant_fulfillment_service", type="string", example="manual"),
	 *             @OA\Property(property="price", type="number", format="float", example=199.99),
	 *             @OA\Property(property="sale_price", type="number", format="float", example=149.99),
	 *             @OA\Property(property="sale_type", type="string", example="percentage"),
	 *             @OA\Property(property="cost_per_item", type="number", format="float", example=50.00),
	 *             @OA\Property(property="tax_id", type="integer", example=3),
	 *             @OA\Property(property="currency_id", type="integer", example=1),
	 *             @OA\Property(property="minimum_order_quantity", type="integer", example=1),
	 *             @OA\Property(property="maximum_order_quantity", type="integer", example=10),
	 *             @OA\Property(property="name", type="string", example="Sample Product"),
	 *             @OA\Property(property="content", type="string", example="Detailed content about the product."),
	 *             @OA\Property(property="description", type="string", example="Short description."),
	 *             @OA\Property(property="images", type="array", @OA\Items(type="string", example="product1.jpg")),
	 *             @OA\Property(property="image", type="string", example="main_image.jpg"),
	 *             @OA\Property(property="video_url", type="string", example="https://www.youtube.com/watch?v=xyz"),
	 *             @OA\Property(property="video_path", type="string", example="videos/product.mp4"),
	 *             @OA\Property(property="documents", type="array", @OA\Items(type="string", example="manual.pdf")),
	 *             @OA\Property(property="length", type="number", format="float", example=10.5),
	 *             @OA\Property(property="length_unit_id", type="integer", example=2),
	 *             @OA\Property(property="width", type="number", format="float", example=5.0),
	 *             @OA\Property(property="height", type="number", format="float", example=3.0),
	 *             @OA\Property(property="depth", type="number", format="float", example=2.0),
	 *             @OA\Property(property="weight", type="number", format="float", example=1.5),
	 *             @OA\Property(property="weight_unit_id", type="integer", example=1),
	 *             @OA\Property(property="shipping_weight_option", type="string", example="lbs"),
	 *             @OA\Property(property="shipping_weight", type="number", format="float", example=2.0),
	 *             @OA\Property(property="shipping_dimension_option", type="string", example="inch"),
	 *             @OA\Property(property="shipping_width", type="number", format="float", example=6.0),
	 *             @OA\Property(property="shipping_depth", type="number", format="float", example=4.0),
	 *             @OA\Property(property="shipping_height", type="number", format="float", example=3.5),
	 *             @OA\Property(property="shipping_length", type="number", format="float", example=11.0),
	 *             @OA\Property(property="shipping_length_id", type="integer", example=2),
	 *             @OA\Property(property="is_variation", type="boolean", example=false),
	 *             @OA\Property(property="variant_grams", type="number", format="float", example=500),
	 *             @OA\Property(property="variant_requires_shipping", type="boolean", example=true),
	 *             @OA\Property(property="variant_barcode", type="string", example="123456789012"),
	 *             @OA\Property(property="variant_color_title", type="string", example="Red"),
	 *             @OA\Property(property="variant_color_value", type="string", example="#FF0000"),
	 *             @OA\Property(property="store_id", type="integer", example=10),
	 *             @OA\Property(property="brand_id", type="integer", example=3),
	 *             @OA\Property(property="views", type="integer", example=200),
	 *             @OA\Property(property="units_sold", type="integer", example=50),
	 *             @OA\Property(property="frequently_bought_together", type="array", @OA\Items(type="integer", example=101)),
	 *             @OA\Property(property="compare_type", type="string", example="similar"),
	 *             @OA\Property(property="compare_products", type="array", @OA\Items(type="integer", example=102)),
	 *             @OA\Property(property="google_shopping_category", type="string", example="Electronics"),
	 *             @OA\Property(property="google_shopping_mpn", type="string", example="123-ABC"),
	 *             @OA\Property(property="order", type="integer", example=1),
	 *             @OA\Property(property="box_quantity", type="integer", example=5),
	 *             @OA\Property(property="delivery_days", type="integer", example=3)
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
	public function update(Request $request, $productId)
	{
		$product = Product::find($productId);
		if (!$product) {
			return response()->json([
				'success' => false,
				'message' => 'Product does not exist.'
			]);
		}
		/* Retrieve all input fields from the request */
		$input = $request->all();

		/* List of valid fields allowed for updating */
		$validArray = [
			"sku", "barcode", "warranty_information", "refund", "quantity",
			"allow_checkout_when_out_of_stock", "with_storehouse_management",
			"stock_status", "variant_inventory_tracker", "variant_inventory_quantity",
			"variant_inventory_policy", "variant_fulfillment_service", "price",
			"sale_price", "sale_type", "cost_per_item", "tax_id", "currency_id",
			"minimum_order_quantity", "maximum_order_quantity", "name", "content",
			"description", "images", "image", "video_url", "video_path", "documents",
			"length", "length_unit_id", "width", "height", "depth", "weight",
			"weight_unit_id", "shipping_weight_option", "shipping_weight",
			"shipping_dimension_option", "shipping_width", "shipping_depth",
			"shipping_height", "shipping_length", "shipping_length_id", "is_variation",
			"variant_grams", "variant_requires_shipping", "variant_barcode",
			"variant_color_title", "variant_color_value", "store_id", "brand_id",
			"views", "units_sold", "frequently_bought_together", "compare_type",
			"compare_products", "google_shopping_category", "google_shopping_mpn",
			"order", "box_quantity", "delivery_days"
		];

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

		/* Stock status validation */
		$usStockStatusArray = [
			1 => "in_stock",
			2 => "out_of_stock",
			3 => "on_backorder"
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
		$taxArray = Tax::pluck("id")->all();
		if (isset($input['tax_id'])) {
			if (!is_numeric($input['tax_id']) || !in_array((int) $input['tax_id'], $taxArray)) {
				$rowError[] = "Invalid tax value.";
			} else {
				$product->tax_id = (int) $input['tax_id'];
				unset($input['tax_id']); /* Remove processed field */
			}
		}

		/* Currency ID validation */
		$currencyArray = Currency::pluck("id")->all();
		if (isset($input['currency_id'])) {
			if (!is_numeric($input['currency_id']) || !in_array((int) $input['currency_id'], $currencyArray)) {
				$rowError[] = "Invalid currency value.";
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
			if (!is_numeric($input['length_unit_id']) || !in_array((int) $input['length_unit_id'], $lengthUnitArray)) {
				$rowError[] = "Invalid length unit value.";
			} else {
				$product->length_unit_id = (int) $input['length_unit_id'];
				unset($input['length_unit_id']); /* Remove processed field */
			}
		}

		if (isset($input['weight_unit_id'])) {
			if (!is_numeric($input['weight_unit_id']) || !in_array((int) $input['weight_unit_id'], $weightUnitArray)) {
				$rowError[] = "Invalid weight unit value.";
			} else {
				$product->weight_unit_id = (int) $input['weight_unit_id'];
				unset($input['weight_unit_id']); /* Remove processed field */
			}
		}

		if (isset($input['shipping_length_id'])) {
			if (!is_numeric($input['shipping_length_id']) || !in_array((int) $input['shipping_length_id'], $lengthUnitArray)) {
				$rowError[] = "Invalid shipping length value.";
			} else {
				$product->shipping_length_id = (int) $input['shipping_length_id'];
				unset($input['shipping_length_id']); /* Remove processed field */
			}
		}

		/* Store ID validation */
		$storeArray = Store::pluck("id")->all();
		if (isset($input['store_id'])) {
			if (!is_numeric($input['store_id']) || !in_array((int) $input['store_id'], $storeArray)) {
				$rowError[] = "Invalid store value.";
			} else {
				$product->store_id = (int) $input['store_id'];
				unset($input['store_id']); /* Remove processed field */
			}
		}

		/* Brand ID validation */
		$brandArray = Brand::pluck("id")->all();
		if (isset($input['brand_id'])) {
			if (!is_numeric($input['brand_id']) || !in_array((int) $input['brand_id'], $brandArray)) {
				$rowError[] = "Invalid brand value.";
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

		/* Save the product */
		$product->save();

		/* Return success response */
		return response()->json([
			'success' => true,
			'message' => 'Product updated successfully.',
			'product' => $product->toArray()
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
			// "created_by_id",
			// "created_by_type",
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
	 * Remove the specified resource from storage.
	 */
	public function destroy(Product $product)
	{
		//
	}
}
