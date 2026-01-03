<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

use App\Repository\ExcelRepository;

use App\Models\Product;
use App\Models\Category;
use App\Models\ProductSupplier;
use App\Models\TransactionLog;

use App\Jobs\ImportProductSupplierJob;
use App\Services\ExcelImporterService;

class ProductSupplierController extends BaseController
{
	/**
	 * @OA\Get(
	 *     path="/api/product-suppliers",
	 *     operationId="getProductSuppliers",
	 *     tags={"Product Suppliers"},
	 *     summary="Get all product suppliers",
	 *     description="Returns a list of all product suppliers with pagination, search, and sorting",
	 *     @OA\Parameter(name="page", in="query", description="Page number for pagination", example=1, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="length", in="query", description="Number of records per page.", example=20, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="global", in="query", description="Global search for All field", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="product_id", in="query", description="Filter by product_id.", example="1",  @OA\Schema(type="integer")),
	 *     @OA\Parameter(name="sort_by", in="query", @OA\Schema(type="string", enum={"id", "product_name", "vendor_name", "vendor_sku", "list_price", "multiple", "cost_per_item", "surcharge", "additional_cost", "total_cost_per_item", "sale_price", "price", "margin", "created_at", "updated_at"})),
	 *     @OA\Parameter(name="sort_dir", in="query", description="Sort direction (asc or desc)", example="asc", @OA\Schema(type="string", enum={"asc", "desc"})),
	 *     @OA\Response(response=200, description="Suppliers retrieved successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function index(Request $request)
	{
		$searchableColumns = ['id', 'product_name', 'vendor_name', 'vendor_sku','product_sku'];
		$sortableColumns = array_merge(
			$searchableColumns,
			[
				'list_price', 'multiple', 'cost_per_item', 'surcharge',
				'additional_cost', 'total_cost_per_item', 'sale_price',
				'price', 'margin', 'created_at', 'updated_at'
			]
		);

		$sortBy = in_array($request->input('sort_by'), $sortableColumns)
		? $request->input('sort_by')
		: 'id';

		$sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

		$recordsQuery = ProductSupplier::query()
		->join('ec_products', 'product_suppliers.product_id', '=', 'ec_products.id')
		->join('vendors', 'product_suppliers.vendor_id', '=', 'vendors.id')
		->select(
			'product_suppliers.*',
			'ec_products.name as product_name',
			 'ec_products.sku as product_sku',
			'vendors.name as vendor_name'
		);

		if ($request->filled('product_id')) {
			$recordsQuery->where('product_id', $request->input('product_id'));
		}

		/* Global Search */
		if ($request->filled('global')) {
			$search = $request->input('global');

			$recordsQuery->where(function ($q) use ($search) {
				$q->orWhere('product_suppliers.id', 'like', "%$search%")
				->orWhere('product_suppliers.vendor_sku', 'like', "%$search%")
				  ->orWhere('ec_products.sku', 'like', "%$search%")
				->orWhere('ec_products.name', 'like', "%$search%")
				->orWhere('vendors.name', 'like', "%$search%");
			});
		}

		/* Sorting */
		if ($sortBy === 'product_name') {
			$recordsQuery->orderBy('ec_products.name', $sortDir);
		} elseif ($sortBy === 'vendor_name') {
			$recordsQuery->orderBy('vendors.name', $sortDir);
		}
		elseif ($sortBy === 'product_sku') {
        $recordsQuery->orderBy('ec_products.sku', $sortDir);
		}
		else {
				$recordsQuery->orderBy("product_suppliers.$sortBy", $sortDir);
			}

		/* Pagination */
		$length = (int) $request->input('length', 20);
		$page = max((int) $request->input('page', 1), 1);

		$totalRecords = (clone $recordsQuery)->count();
		$totalPages = (int) ceil($totalRecords / $length);

		if ($page > $totalPages && $totalPages > 0) {
			$page = 1;
		}

		$records = $recordsQuery
		->offset(($page - 1) * $length)
		->limit($length)
		->get();

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
	 *     path="/api/product-suppliers",
	 *     operationId="storeProductSupplier",
	 *     tags={"Product Suppliers"},
	 *     summary="Create a new product supplier",
	 *     description="Creates a new product supplier entry",
	 *     security={{"bearerAuth":{}}},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"product_id", "vendor_id", "vendor_sku", "price", "in_stock", "delivery_days", "return_policy"},
	 *             @OA\Property(property="product_id", type="integer", example=1),
	 *             @OA\Property(property="vendor_id", type="integer", example=2),
	 *             @OA\Property(property="vendor_sku", type="string", example="SKU-1234"),

	 *             @OA\Property(property="list_price", type="number", format="float", nullable=true, example=100.00),
	 *             @OA\Property(property="multiple", type="number", format="float", nullable=true, example=0.85),
	 *             @OA\Property(property="cost_per_item", type="number", format="float", nullable=true, example=85.00),

	 *             @OA\Property(property="surcharge", type="number", format="float", nullable=true, example=10),
	 *             @OA\Property(property="additional_cost", type="number", format="float", nullable=true, example=10),

	 *             @OA\Property(property="map", type="number", format="float", nullable=true, example=110.00),
	 *             @OA\Property(property="sale_price", type="number", format="float", nullable=true, example=115.00),
	 *             @OA\Property(property="price", type="number", format="float", example=120.00),

	 *             @OA\Property(property="inventory", type="integer", nullable=true, example=50),
	 *             @OA\Property(property="in_stock", type="string", enum={"Yes", "No"}, example="Yes"),
	 *             @OA\Property(property="min_quantity", type="integer", example=3),
	 *             @OA\Property(property="is_fixed", type="string", enum={"Yes", "No"}, example="Yes"),
	 *             @OA\Property(property="delivery_days", type="string", example="3-5 days"),
	 *             @OA\Property(property="return_policy", type="string", example="7 days"),
	 *             @OA\Property(property="free_shipping", type="string", nullable=true, enum={"Yes", "No"}, example="No"),
	 *             @OA\Property(property="warranty_information", type="string", nullable=true, example="6 months"),

	 *             @OA\Property(property="restocking_fees", type="number", format="float", nullable=true, example=15)
	 *         )
	 *     ),
	 *     @OA\Response(response=201, description="Created successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function store(Request $request)
	{
		$data = $request->validate([
			'product_id' => 'required|integer|exists:ec_products,id',
			'vendor_id' => 'required|integer|exists:vendors,id',
			'vendor_sku' => 'required|string',

			'list_price' => 'nullable|numeric|required_without:cost_per_item',
			'multiple' => 'nullable|numeric|required_without:cost_per_item',
			'cost_per_item' => 'nullable|numeric|required_without_all:list_price,multiple',

			'surcharge' => 'nullable|numeric',
			'additional_cost' => 'nullable|numeric',

			'map' => 'nullable|numeric',
			'sale_price' => 'nullable|numeric',
			'price' => 'required|numeric',

			'inventory' => 'nullable|integer',

			'in_stock' => ['required', Rule::in(app_constants('IN_STOCK_OPTIONS'))],
			'min_quantity' => 'required|integer',
			'is_fixed' => ['required', Rule::in(app_constants('IS_FIXED_OPTIONS'))],
			'delivery_days' => ['required', Rule::in(app_constants('DELIVERY_DAYS'))],
			'return_policy' => ['required', Rule::in(app_constants('RETURN_POLICY'))],
			'free_shipping' => ['nullable', Rule::in(app_constants('FREE_SHIPPING_OPTIONS'))],
			'shipping_charge' => 'nullable|numeric|required_if:free_shipping,No',
			'warranty_information' => ['nullable', Rule::in(app_constants('WARRANTY_OPTIONS'))],

			'restocking_fees' => 'nullable|numeric',
		]);

		/* Check if a record with the same sku and vendor_id already exists */
		$existingEntry = ProductSupplier::where('product_id', $data['product_id'])
		->where('vendor_id', $data['vendor_id'])
		->first();

		if ($existingEntry) {
			return response()->json([
				'success' => false,
				'message' => 'A product supplier with the Vendor ID already exists.',
			], 422);
		}

		$rowErrors = [];

		/* multiple must be between 0 and 1 */
		if (!empty($data['multiple']) && ($data['multiple'] <= 0 || $data['multiple'] >= 1)) {
			$rowErrors[] = "'Multiple' must be greater than 0 and less than 1.";
		}

		/* MAP logic */
		if (!empty($data['map']) && !empty($data['sale_price']) && (float)$data['map'] > (float)$data['sale_price']) {
			$rowErrors[] = 'Sale Price cannot be less than MAP.';
		}

		if (!empty($data['sale_price']) && !empty($data['price']) && (float)$data['price'] < (float)$data['sale_price']) {
			$rowErrors[] = 'Price cannot be less than sale price.';
		}

		if (!empty($data['map']) && !empty($data['price']) && (float)$data['price'] < (float)$data['map']) {
			$rowErrors[] = 'Price cannot be less than MAP.';
		}

		if (!empty($rowErrors)) {
			return response()->json([
				'success' => false,
				'message' => $rowErrors
			], 422);
		}

		/* Calculate cost_per_item */
		$data['cost_per_item'] = (!empty($data['list_price']) && !empty($data['multiple']))
		? (float)$data['list_price'] * (float)$data['multiple']
		: (float)($data['cost_per_item'] ?? 0);

		/* Calculate surcharge and additional_cost (as % of cost_per_item) */
		$data['surcharge'] = !empty($data['surcharge'])
		? $data['cost_per_item'] * ((float)$data['surcharge'] / 100)
		: 0;

		$data['additional_cost'] = !empty($data['additional_cost'])
		? $data['cost_per_item'] * ((float)$data['additional_cost'] / 100)
		: 0;

		/* Total cost per item */
		$data['total_cost_per_item'] = $data['cost_per_item'] + $data['surcharge'] + $data['additional_cost'];

		/* Price & Sale Price fallback */
		$data['sale_price'] = isset($data['sale_price']) ? (float)$data['sale_price'] : null;
		$data['price'] = isset($data['price']) ? (float)$data['price'] : 0;

		/* Margin calculation */
		if (!empty($data['sale_price']) && $data['sale_price'] > 0) {
			$data['margin'] = (($data['sale_price'] - $data['total_cost_per_item']) / $data['sale_price']) * 100;
		} elseif ($data['price'] > 0) {
			$data['margin'] = (($data['price'] - $data['total_cost_per_item']) / $data['price']) * 100;
		} else {
			$data['margin'] = null;
		}

		$data['in_stock'] = ($data['inventory'] > 0) ? 1 : (!empty($data['in_stock']) && strtolower($data['in_stock']) === 'yes' ? 1 : 0);
		$data['is_fixed'] = !empty($data['is_fixed']) && strtolower($data['is_fixed']) === 'yes' ? 1 : 0;
		$data['free_shipping'] = !empty($data['free_shipping']) && strtolower($data['free_shipping']) === 'yes' ? 1 : 0;
		$data['shipping_charge'] = $data['free_shipping'] == 1 ? 0 : $data['shipping_charge'];

		$data['created_by'] = auth()->id();

		$record = ProductSupplier::create($data);

		return response()->json([
			'success' => true,
			'message' => __("msg_create"),
			'data' => $record
		]);
	}

	/**
	 * @OA\Get(
	 *     path="/api/product-suppliers/{id}",
	 *     operationId="getProductSupplierById",
	 *     tags={"Product Suppliers"},
	 *     summary="Get a product supplier by ID",
	 *     description="Returns the details of a specific product supplier",
	 *     security={{"bearerAuth":{}}},
	 *     @OA\Parameter(name="id", in="path", required=true, description="Product Supplier ID", @OA\Schema(type="integer", example=1)),
	 *     @OA\Response(response=200, description="Details retrieved successfully", @OA\MediaType(mediaType="application/json"))
	 * )
	 */
	public function show($id)
	{
		$productSupplier = ProductSupplier::with(['product:id,name', 'vendor:id,name'])->find($id);

		if (!$productSupplier) {
			return response()->json([
				'success' => false,
				'message' => 'Supplier not found.'
			], 404);
		}

		return response()->json([
			'success' => true,
			'message' => 'Product supplier fetched successfully.',
			'data' => $productSupplier
		]);
	}

	/**
	 * @OA\Put(
	 *     path="/api/product-suppliers/{id}",
	 *     operationId="updateProductSupplier",
	 *     tags={"Product Suppliers"},
	 *     summary="Update an existing product supplier",
	 *     description="Updates the specified product supplier entry",
	 *     security={{"bearerAuth":{}}},
	 *     @OA\Parameter(name="id", in="path", description="Product Supplier ID", required=true, @OA\Schema(type="integer", example=1)),
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             @OA\Property(property="product_id", type="integer", example=1),
	 *             @OA\Property(property="vendor_id", type="integer", example=2),
	 *             @OA\Property(property="vendor_sku", type="string", example="SKU-5678"),
	 *             @OA\Property(property="list_price", type="number", format="float", nullable=true, example=100.00),
	 *             @OA\Property(property="multiple", type="number", format="float", nullable=true, example=0.85),
	 *             @OA\Property(property="cost_per_item", type="number", format="float", nullable=true, example=85.00),
	 *             @OA\Property(property="surcharge", type="number", format="float", nullable=true, example=10),
	 *             @OA\Property(property="additional_cost", type="number", format="float", nullable=true, example=10),
	 *             @OA\Property(property="map", type="number", format="float", nullable=true, example=110.00),
	 *             @OA\Property(property="sale_price", type="number", format="float", nullable=true, example=115.00),
	 *             @OA\Property(property="price", type="number", format="float", example=120.00),
	 *             @OA\Property(property="inventory", type="integer", nullable=true, example=50),
	 *             @OA\Property(property="in_stock", type="string", enum={"Yes", "No"}, example="Yes"),
	 *             @OA\Property(property="min_quantity", type="integer", example=3),
	 *             @OA\Property(property="is_fixed", type="string", enum={"Yes", "No"}, example="Yes"),
	 *             @OA\Property(property="delivery_days", type="string", example="3-5 days"),
	 *             @OA\Property(property="return_policy", type="string", example="7 days"),
	 *             @OA\Property(property="free_shipping", type="string", nullable=true, enum={"Yes", "No"}, example="No"),
	 *             @OA\Property(property="warranty_information", type="string", nullable=true, example="6 months"),
	 *             @OA\Property(property="restocking_fees", type="number", format="float", nullable=true, example=20)
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Updated successfully", @OA\MediaType(mediaType="application/json")),
	 * )
	 */
	public function update(Request $request, $id)
	{
		$supplier = ProductSupplier::find($id);

		if (!$supplier) {
			return response()->json([
				'success' => false,
				'message' => 'Supplier not found.'
			], 404);
		}

		$data = $request->validate([
			'product_id' => 'required|integer|exists:ec_products,id',
			'vendor_id' => 'required|integer|exists:vendors,id',
			'vendor_sku' => 'required|string',

			'list_price' => 'nullable|numeric|required_without:cost_per_item',
			'multiple' => 'nullable|numeric|min:0|max:1|required_without:cost_per_item',
			'cost_per_item' => 'nullable|numeric|required_without_all:list_price,multiple',

			'surcharge' => 'nullable|numeric',
			'additional_cost' => 'nullable|numeric',

			'map' => 'nullable|numeric',
			'sale_price' => 'nullable|numeric',
			'price' => 'required|numeric',

			'inventory' => 'nullable|integer',

			'in_stock' => ['required', Rule::in(app_constants('IN_STOCK_OPTIONS'))],
			'min_quantity' => 'required|integer',
			'is_fixed' => ['required', Rule::in(app_constants('IS_FIXED_OPTIONS'))],
			'delivery_days' => ['required', Rule::in(app_constants('DELIVERY_DAYS'))],
			'return_policy' => ['required', Rule::in(app_constants('RETURN_POLICY'))],
			'free_shipping' => ['nullable', Rule::in(app_constants('FREE_SHIPPING_OPTIONS'))],
			'shipping_charge' => 'nullable|numeric|required_if:free_shipping,No',
			'warranty_information' => ['nullable', Rule::in(app_constants('WARRANTY_OPTIONS'))],

			'restocking_fees' => 'nullable|numeric',
		]);

		/* Business rules */
		$rowErrors = [];

			$data['multiple'] = isset($data['multiple']) ? (float)$data['multiple'] : null;
			if ($data['multiple'] === 0.0) $data['multiple'] = null;


		if (!empty($data['map']) && !empty($data['sale_price']) && (float)$data['map'] > (float)$data['sale_price']) {
			$rowErrors[] = 'Sale Price cannot be less than MAP.';
		}

		if (!empty($data['sale_price']) && (float)$data['price'] < (float)$data['sale_price']) {
			$rowErrors[] = 'Price cannot be less than sale price.';
		}

		if (!empty($data['map']) && (float)$data['price'] < (float)$data['map']) {
			$rowErrors[] = 'Price cannot be less than MAP.';
		}

		if (!empty($rowErrors)) {
			return response()->json([
				'success' => false,
				'errors' => $rowErrors
			], 422);
		}

		/* Compute cost and margin */
		$data['cost_per_item'] = ($data['list_price'] !== null && $data['multiple'] !== null)
		? (float)$data['list_price'] * (float)$data['multiple']
		: (float)($data['cost_per_item'] ?? 0);

		$data['surcharge'] = !empty($data['surcharge'])
		? $data['cost_per_item'] * ((float)$data['surcharge'] / 100)
		: 0;

		$data['additional_cost'] = !empty($data['additional_cost'])
		? $data['cost_per_item'] * ((float)$data['additional_cost'] / 100)
		: 0;

		$data['total_cost_per_item'] = $data['cost_per_item'] + $data['surcharge'] + $data['additional_cost'];

		$data['sale_price'] = isset($data['sale_price']) ? (float)$data['sale_price'] : null;
		$data['price'] = isset($data['price']) ? (float)$data['price'] : 0;

		if (!empty($data['sale_price']) && $data['sale_price'] > 0) {
			$data['margin'] = (($data['sale_price'] - $data['total_cost_per_item']) / $data['sale_price']) * 100;
		} elseif ($data['price'] > 0) {
			$data['margin'] = (($data['price'] - $data['total_cost_per_item']) / $data['price']) * 100;
		} else {
			$data['margin'] = null;
		}

		$data['in_stock'] = ($data['inventory'] > 0) ? 1 : (!empty($data['in_stock']) && strtolower($data['in_stock']) === 'yes' ? 1 : 0);
		$data['is_fixed'] = !empty($data['is_fixed']) && strtolower($data['is_fixed']) === 'yes' ? 1 : 0;
		$data['free_shipping'] = !empty($data['free_shipping']) && strtolower($data['free_shipping']) === 'yes' ? 1 : 0;
		$data['shipping_charge'] = $data['free_shipping'] == 1 ? 0 : $data['shipping_charge'];

		$data['updated_by'] = auth()->id();
		$supplier->update($data);

		return response()->json([
			'success' => true,
			'message' => 'Product supplier updated successfully.',
			'data' => $supplier
		], 200);
	}

	/**
	 * @OA\Delete(
	 *     path="/api/product-suppliers/{id}",
	 *     operationId="deleteProductSupplier",
	 *     tags={"Product Suppliers"},
	 *     summary="Delete a product supplier",
	 *     description="Deletes a product supplier by its ID",
	 *     security={{"bearerAuth":{}}},
	 *     @OA\Parameter(name="id", in="path", required=true, description="Product Supplier ID", @OA\Schema(type="integer", example=1)),
	 *     @OA\Response(response=200, description="Deleted successfully", @OA\MediaType(mediaType="application/json"))
	 * )
	 */
	public function destroy($id)
	{
		$productSupplier = ProductSupplier::find($id);

		if (!$productSupplier) {
			return response()->json([
				'success' => false,
				'message' => 'Supplier not found.'
			], 404);
		}

		$productSupplier->delete();

		return response()->json([
			'success' => true,
			'message' => 'Product supplier deleted successfully.'
		]);
	}

	/**
	 * @OA\Post(
	 *     path="/api/product-suppliers/export",
	 *     summary="Export product supplier data to Excel",
	 *     tags={"Product Suppliers"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"type", "relational_id", "range_from", "range_to"},
	 *             @OA\Property(property="status", type="string", example="all", description="Status"),
	 *             @OA\Property(property="type", type="string", enum={"Brand","Vendor","Category"}, example="Category", description="Type should be either 'Brand' or 'Category'"),
	 *             @OA\Property(property="relational_id", type="integer", example=1, description="Relational ID"),
	 *             @OA\Property(property="range_from", type="integer", example=1, description="Starting range (must be >= 1)"),
	 *             @OA\Property(property="range_to", type="integer", example=50, description="Ending range (must be >= range_from and max 2000 more)")
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function export(Request $request, ExcelRepository $excelRepo)
	{
		if (!auth()->user()->can('export product suppliers')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}

		/* Validate request data */
		$request->validate([
			'status' => 'required|string|in:all,draft,published',
			'type' => 'required|string|in:Brand,Vendor,Category',
			'relational_id' => 'required|integer',
			'range_from' => 'integer|min:1',
			'range_to' => 'integer|gte:range_from|max:' . ((int)$request->input('range_from') + 2000),
		]);

		$deliveryTimeOptions = app_constants('DELIVERY_DAYS');
		$warrantyOptions = app_constants('WARRANTY_OPTIONS');
		$returnPolicies = app_constants('RETURN_POLICY');
		$inStockOptions = ['Yes', 'No'];
		$isFixedOptions = ['Yes', 'No'];
		$freeShippingOptions = ['Yes', 'No'];

		$query = ProductSupplier::with(['product']);

		/* Filter by product status if not "all" */
		if ($request->status !== 'all') {
			$query->whereHas('product', function ($q) use ($request) {
				$q->where('status', $request->status);
			});
		}

		/* Apply relational filters */
		if ($request->type === "Brand") {
			$query->whereHas('product', function ($q) use ($request) {
				$q->where('brand_id', $request->relational_id);
			});
		} elseif ($request->type === "Vendor") {
			$query->where('vendor_id', $request->relational_id);
		} elseif ($request->type === "Category") {
			$category = Category::find($request->relational_id);

			if ($category) {
				$leafCategoryIds = $category->getLeafCategories()->pluck('id')->toArray();

				$query->whereHas('product.categories', function ($q) use ($leafCategoryIds) {
					$q->whereIn('categories.id', $leafCategoryIds);
				});
			}
		}

		/* Pagination: offset and limit */
		$productSuppliers = $query->orderBy('id', 'asc')
		->offset($request->range_from - 1)
		->limit($request->range_to - $request->range_from + 1)
		->get();

		// if ($productSuppliers->isEmpty()) {
		// 	return response()->json([
		// 		'success' => false,
		// 		'message' => 'No product suppliers found for the given criteria.'
		// 	], 404);
		// }

		$supplierFormatArray = [
			'ID' => 'id',
			'Product ID' => 'product_id',
			'Product Name' => 'product_name',
			'Vendor ID' => 'vendor_id',
			'Vendor Name' => 'vendor_name',
			'Vendor SKU' => 'vendor_sku',
			'List Price' => 'list_price',
			'Multiple' => 'multiple',
			'Cost Per Item' => 'cost_per_item',
			'Surcharge(%)' => 'surcharge',
			'Additional Cost(%)' => 'additional_cost',
			'MAP' => 'map',
			'Sale Price' => 'sale_price',
			'Price' => 'price',
			'Inventory' => 'inventory',
			'In Stock' => 'in_stock',
			'Min Quantity' => 'min_quantity',
			'Is Fixed' => 'is_fixed',
			'Delivery Days' => 'delivery_days',
			'Return Policy' => 'return_policy',
			'Free Shipping' => 'free_shipping',
			'Shipping Charge' => 'shipping_charge',
			'Restocking Fees(%)' => 'restocking_fees',
			'Warranty Information' => 'warranty_information',
		];

		$header = array_keys($supplierFormatArray);

		/* Initialize spreadsheet */
		$spreadsheet = $excelRepo->newSpreadsheet();
		$spreadsheet->setActiveSheetIndex(0);
		$sheet = $spreadsheet->getActiveSheet();

		/* Set headers */
		$excelRepo->setHeader($sheet, $header);

		/* Populate data */
		$row = 2;
		foreach ($productSuppliers as $supplier) {
			$col = 'A';
			/* Extract existing values if present in their respective options, else set empty string */

			$selectedInStock = $supplier->in_stock == 1 ? 'Yes' : 'No';
			$selectedIsFixed = $supplier->is_fixed == 1 ? 'Yes' : 'No';
			$selectedFreeShipping = $supplier->free_shipping == 1 ? 'Yes' : 'No';

			$selectedDeliveryDays = in_array($supplier->delivery_days, $deliveryTimeOptions) ? $supplier->delivery_days : '';
			$selectedWarranty = in_array($supplier->warranty_information, $warrantyOptions) ? $supplier->warranty_information : '';
			$selectedReturnPolicy = in_array($supplier->return_policy, $returnPolicies) ? $supplier->return_policy : '';

			$sheet->setCellValue($col++ . $row, $supplier->id ?? '');
			$sheet->setCellValue($col++ . $row, $supplier->product_id ?? '');
			$sheet->setCellValue($col++ . $row, $supplier->product->name ?? '');
			$sheet->setCellValue($col++ . $row, $supplier->vendor_id ?? '');
			$sheet->setCellValue($col++ . $row, $supplier->vendor->name ?? '');
			$sheet->setCellValue($col++ . $row, $supplier->vendor_sku ?? '');
			$sheet->setCellValue($col++ . $row, $supplier->list_price ?? '');
			$sheet->setCellValue($col++ . $row, $supplier->multiple ?? '');
			$sheet->setCellValue($col++ . $row, $supplier->cost_per_item ?? '');
			$sheet->setCellValue($col++ . $row, $supplier->surcharge ?? '');
			$sheet->setCellValue($col++ . $row, $supplier->additional_cost ?? '');
			$sheet->setCellValue($col++ . $row, $supplier->map ?? '');
			$sheet->setCellValue($col++ . $row, $supplier->sale_price ?? '');
			$sheet->setCellValue($col++ . $row, $supplier->price ?? '');
			$sheet->setCellValue($col++ . $row, $supplier->inventory ?? '');

			$excelRepo->setDropdown($spreadsheet, $sheet, $col++ . $row, 'in_stock', $inStockOptions, $selectedInStock);
			$sheet->setCellValue($col++ . $row, $supplier->min_quantity ?? 1);
			$excelRepo->setDropdown($spreadsheet, $sheet, $col++ . $row, 'is_fixed', $isFixedOptions, $selectedIsFixed);
			$excelRepo->setDropdown($spreadsheet, $sheet, $col++ . $row, 'delivery_days', $deliveryTimeOptions, $selectedDeliveryDays);
			$excelRepo->setDropdown($spreadsheet, $sheet, $col++ . $row, 'return_policy', $returnPolicies, $selectedReturnPolicy);
			$excelRepo->setDropdown($spreadsheet, $sheet, $col++ . $row, 'free_shipping', $freeShippingOptions, $selectedFreeShipping);

			$sheet->setCellValue($col++ . $row, $supplier->shipping_charge ?? '');
			$sheet->setCellValue($col++ . $row, $supplier->restocking_fees ?? '');

			$excelRepo->setDropdown($spreadsheet, $sheet, $col++ . $row, 'warranty_information', $warrantyOptions, $selectedWarranty);
			$row++;
		}

		$fileName = 'products_suppliers_' . $request->range_from . '-' . $request->range_to . '_' . now()->format('Y-m-d_H-i-s') . '.xlsx';

		return $excelRepo->downloadFile($fileName, $spreadsheet);
	}

	/**
	 * @OA\Post(
	 *     path="/api/product-suppliers/import",
	 *     summary="Import product suppliers from an Excel file",
	 *     tags={"Product Suppliers"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="multipart/form-data",
	 *             @OA\Schema(
	 *                 required={"upload_file"},
	 *                 @OA\Property(property="upload_file", type="string", format="binary", description="Excel file (.xlsx) max 2MB")
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function import(Request $request, ExcelImporterService $excelImporter)
	{
		if (!auth()->user()->can('import product suppliers')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}

		/* Validate request data */
		$request->validate([
			'upload_file' => 'required|file|mimes:xlsx,xls|max:2048',
		]);

		try {
			$supplierFormatArray = [
				'ID' => 'id',
				'Product ID' => 'product_id',
				'Product Name' => 'product_name',
				'Vendor ID' => 'vendor_id',
				'Vendor Name' => 'vendor_name',
				'Vendor SKU' => 'vendor_sku',
				'List Price' => 'list_price',
				'Multiple' => 'multiple',
				'Cost Per Item' => 'cost_per_item',
				'Surcharge(%)' => 'surcharge',
				'Additional Cost(%)' => 'additional_cost',
				'MAP' => 'map',
				'Sale Price' => 'sale_price',
				'Price' => 'price',
				'Inventory' => 'inventory',
				'In Stock' => 'in_stock',
				'Min Quantity' => 'min_quantity',
				'Is Fixed' => 'is_fixed',
				'Delivery Days' => 'delivery_days',
				'Return Policy' => 'return_policy',
				'Free Shipping' => 'free_shipping',
				'Shipping Charge' => 'shipping_charge',
				'Restocking Fees(%)' => 'restocking_fees',
				'Warranty Information' => 'warranty_information',
			];

			$excelImporter->processExcelImport(
				$request->file('upload_file'),
				$supplierFormatArray,
				'Product Supplier', /* Module name */
				config('app.website') . '_PROD_SUPPLIER', /* Job name */
				'Import Product Suppliers', /* Batch name */
				ImportProductSupplierJob::class
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
	 *     path="/api/product-suppliers/template",
	 *     summary="Download import template",
	 *     description="Downloads an Excel template for product supplier imports",
	 *     tags={"Product Suppliers"},
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function downloadTemplate(ExcelRepository $excelRepo)
	{
		$supplierFormatArray = [
			'ID' => 'id',
			'Product ID' => 'product_id',
			'Product Name' => 'product_name',
			'Vendor ID' => 'vendor_id',
			'Vendor Name' => 'vendor_name',
			'Vendor SKU' => 'vendor_sku',
			'List Price' => 'list_price',
			'Multiple' => 'multiple',
			'Cost Per Item' => 'cost_per_item',
			'Surcharge(%)' => 'surcharge',
			'Additional Cost(%)' => 'additional_cost',
			'MAP' => 'map',
			'Sale Price' => 'sale_price',
			'Price' => 'price',
			'Inventory' => 'inventory',
			'In Stock' => 'in_stock',
			'Min Quantity' => 'min_quantity',
			'Is Fixed' => 'is_fixed',
			'Delivery Days' => 'delivery_days',
			'Return Policy' => 'return_policy',
			'Free Shipping' => 'free_shipping',
			'Shipping Charge' => 'shipping_charge',
			'Restocking Fees(%)' => 'restocking_fees',
			'Warranty Information' => 'warranty_information',
		];
		$deliveryTimeOptions = app_constants('DELIVERY_DAYS');
		$warrantyOptions = app_constants('WARRANTY_OPTIONS');
		$returnPolicies = app_constants('RETURN_POLICY');
		$inStockOptions = ['Yes', 'No'];
		$isFixedOptions = ['Yes', 'No'];
		$freeShippingOptions = ['Yes', 'No'];

		$header = array_keys($supplierFormatArray);

		/* Initialize spreadsheet */
		$spreadsheet = $excelRepo->newSpreadsheet();
		$spreadsheet->setActiveSheetIndex(0);
		$sheet = $spreadsheet->getActiveSheet();

		/* Set headers */
		$excelRepo->setHeader($sheet, $header);

		$row = 2;
		$col = 'A';

		/* Set product details */
		$sheet->setCellValue($col++ . $row, '');
		$sheet->setCellValue($col++ . $row, '');
		$sheet->setCellValue($col++ . $row, '');
		$sheet->setCellValue($col++ . $row, '');
		$sheet->setCellValue($col++ . $row, '');
		$sheet->setCellValue($col++ . $row, '');
		$sheet->setCellValue($col++ . $row, '');
		$sheet->setCellValue($col++ . $row, '');
		$sheet->setCellValue($col++ . $row, '');
		$sheet->setCellValue($col++ . $row, '');
		$sheet->setCellValue($col++ . $row, '');
		$sheet->setCellValue($col++ . $row, '');
		$sheet->setCellValue($col++ . $row, '');
		$sheet->setCellValue($col++ . $row, '');
		$sheet->setCellValue($col++ . $row, '');
		$excelRepo->setDropdown($spreadsheet, $sheet, $col++ . $row, 'in_stock', $inStockOptions, '');
		$sheet->setCellValue($col++ . $row, 1);
		$excelRepo->setDropdown($spreadsheet, $sheet, $col++ . $row, 'is_fixed', $isFixedOptions, '');
		$excelRepo->setDropdown($spreadsheet, $sheet, $col++ . $row, 'delivery_days', $deliveryTimeOptions, '');
		$excelRepo->setDropdown($spreadsheet, $sheet, $col++ . $row, 'return_policy', $returnPolicies, '');
		$excelRepo->setDropdown($spreadsheet, $sheet, $col++ . $row, 'free_shipping', $freeShippingOptions, '');
		$sheet->setCellValue($col++ . $row, '');
		$sheet->setCellValue($col++ . $row, '');
		$excelRepo->setDropdown($spreadsheet, $sheet, $col++ . $row, 'warranty_information', $warrantyOptions, '');

		$fileName = 'products_suppliers_import_template' . now()->format('Y-m-d_H-i-s') . '.xlsx';

		return $excelRepo->downloadFile($fileName, $spreadsheet);
	}

	 /**
     * @OA\Put(
     *     path="/api/product-supplier/{id}/update-price",
     *     summary="Update price, sale price, and total cost per item for a product supplier",
     *     tags={"Product Supplier"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the product supplier",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="price", type="number", format="float", example=150),
     *             @OA\Property(property="sale_price", type="number", format="float", example=120),
     *             @OA\Property(property="total_cost_per_item", type="number", format="float", example=100)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product supplier updated successfully."),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Supplier not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Supplier not found.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation errors",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *         )
     *     ),
	 *		 security={{"bearerAuth":{}}}
     * )
     */
    public function updatePrice(Request $request, $id)
    {
        $supplier = ProductSupplier::find($id);

        if (!$supplier) {
            return response()->json([
                'success' => false,
                'message' => 'Supplier not found.'
            ], 404);
        }

        $data = $request->validate([
            'price' => 'required|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'total_cost_per_item' => 'required|numeric|min:0',
        ]);

        // Business rules
        if (!empty($data['sale_price']) && $data['price'] < $data['sale_price']) {
            return response()->json([
                'success' => false,
                'errors' => ['Price cannot be less than sale price.']
            ], 422);
        }

        $supplier->update([
            'price' => $data['price'],
            'sale_price' => $data['sale_price'] ?? null,
            'total_cost_per_item' => $data['total_cost_per_item']
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product supplier updated successfully.',
            'data' => $supplier
        ], 200);
    }

	/**
	 * @OA\Put(
	 *     path="/api/product-supplier/update-price-by-sku/{sku}",
	 *     summary="Update price, sale price, and total cost per item for a product supplier using SKU",
	 *     tags={"Product Supplier"},
	 *     @OA\Parameter(
	 *         name="sku",
	 *         in="path",
	 *         description="SKU of the product",
	 *         required=true,
	 *         @OA\Schema(type="string")
	 *     ),
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             @OA\Property(property="price", type="number", format="float", example=150),
	 *             @OA\Property(property="sale_price", type="number", format="float", example=120)
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Successfully updated product supplier price by SKU",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="message", type="string", example="Product supplier updated successfully."),
	 *             @OA\Property(property="data", type="object")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=404,
	 *         description="Product or supplier not found",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=false),
	 *             @OA\Property(property="message", type="string", example="Product or supplier not found.")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=422,
	 *         description="Validation errors",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=false),
	 *             @OA\Property(property="errors", type="array", @OA\Items(type="string"))
	 *         )
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function updatePriceBySku(Request $request, $sku)
	{
		// Step 1: Find product by SKU
		$product = Product::where('sku', $sku)->first();

		if (!$product) {
			return response()->json([
				'success' => false,
				'message' => 'Product not found.'
			], 404);
		}

		// Step 2: Find supplier record linked to this product
		$supplier = ProductSupplier::where('product_id', $product->id)->first();

		if (!$supplier) {
			return response()->json([
				'success' => false,
				'message' => 'Supplier not found for this product.'
			], 404);
		}

		// Step 3: Validate request
		$data = $request->validate([
			'price' => 'required|numeric|min:0',
			'sale_price' => 'nullable|numeric|min:0'
		]);

		// Business rule
		if (!empty($data['sale_price']) && $data['price'] < $data['sale_price']) {
			return response()->json([
				'success' => false,
				'errors' => ['Price cannot be less than sale price.']
			], 422);
		}

		// Step 4: Update supplier record
		$supplier->update([
			'price' => $data['price'],
			'sale_price' => $data['sale_price'] ?? null
		]);

		return response()->json([
			'success' => true,
			'message' => 'Product supplier updated successfully.',
			'data' => $supplier
		], 200);
	}



}
