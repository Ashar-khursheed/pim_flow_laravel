<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
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
	 *     @OA\Parameter(
	 *         name="search",
	 *         in="query",
	 *         description="Search term for global search",
	 *         required=false,
	 *         @OA\Schema(type="string")
	 *     ),
	 *     @OA\Parameter(
	 *         name="sort_by",
	 *         in="query",
	 *         description="Column to sort by",
	 *         required=false,
	 *         @OA\Schema(type="string")
	 *     ),
	 *     @OA\Parameter(
	 *         name="sort_direction",
	 *         in="query",
	 *         description="Sort direction (asc or desc)",
	 *         required=false,
	 *         @OA\Schema(type="string", enum={"asc", "desc"})
	 *     ),
	 *     @OA\Parameter(
	 *         name="per_page",
	 *         in="query",
	 *         description="Number of items per page",
	 *         required=false,
	 *         @OA\Schema(type="integer", default=15)
	 *     ),
	 *     @OA\Parameter(
	 *         name="page",
	 *         in="query",
	 *         description="Page number",
	 *         required=false,
	 *         @OA\Schema(type="integer", default=1)
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Success",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="data", type="array", @OA\Items(
	 *                 @OA\Property(property="id", type="integer"),
	 *                 @OA\Property(property="product_id", type="integer"),
	 *                 @OA\Property(property="vendor_id", type="integer"),
	 *                 @OA\Property(property="vendor_sku", type="string"),
	 *                 @OA\Property(property="cost_per_item", type="string"),
	 *                 @OA\Property(property="additional_cost", type="string"),
	 *                 @OA\Property(property="price", type="string"),
	 *                 @OA\Property(property="sale_price", type="string"),
	 *                 @OA\Property(property="inventory", type="integer"),
	 *                 @OA\Property(property="in_stock", type="integer"),
	 *                 @OA\Property(property="delivery_days", type="string"),
	 *                 @OA\Property(property="warranty_information", type="string"),
	 *                 @OA\Property(property="refund", type="string"),
	 *                 @OA\Property(property="final_cost_price", type="string"),
	 *                 @OA\Property(property="margin", type="string"),
	 *                 @OA\Property(property="created_at", type="string", format="date-time"),
	 *                 @OA\Property(property="updated_at", type="string", format="date-time"),
	 *                 @OA\Property(property="product_sku", type="string"),
	 *                 @OA\Property(property="vendor_name", type="string")
	 *             )),
	 *             @OA\Property(property="meta", type="object")
	 *         )
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function index(Request $request)
	{
		// Start with a query builder to allow for filtering, sorting and pagination
		$query = ProductSupplier::query()
		->join('ec_products', 'product_suppliers.product_id', '=', 'ec_products.id')
		->join('vendors', 'product_suppliers.vendor_id', '=', 'vendors.id')
		->select('product_suppliers.*', 'ec_products.sku as product_sku', 'vendors.name as vendor_name');

		// Apply global search if provided
		if ($request->has('search') && !empty($request->search)) {
			$searchTerm = $request->search;
			$query->where(function($q) use ($searchTerm) {
				$q->where('product_suppliers.vendor_sku', 'like', "%{$searchTerm}%")
				->orWhere('ec_products.sku', 'like', "%{$searchTerm}%")
				->orWhere('vendors.name', 'like', "%{$searchTerm}%")
				->orWhere('product_suppliers.delivery_days', 'like', "%{$searchTerm}%")
				->orWhere('product_suppliers.warranty_information', 'like', "%{$searchTerm}%")
				->orWhere('product_suppliers.refund', 'like', "%{$searchTerm}%");
			});
		}

		// Apply sorting if provided
		$sortBy = $request->input('sort_by');
		$sortDirection = $request->input('sort_direction', 'desc');

		// Set a default sort column if none is provided or if it's empty
		if (empty($sortBy)) {
			$sortBy = 'created_at';
		}

		// Handle table prefixing for sort column
		if (in_array($sortBy, ['id', 'product_id', 'vendor_id', 'vendor_sku', 'cost_per_item',
			'additional_cost', 'price', 'sale_price', 'inventory', 'in_stock',
			'delivery_days', 'warranty_information', 'refund', 'final_cost_price',
			'margin', 'created_at', 'updated_at'])) {
			$sortBy = "product_suppliers.{$sortBy}";
	} elseif ($sortBy === 'product_sku') {
		$sortBy = "ec_products.sku";
	} elseif ($sortBy === 'vendor_name') {
		$sortBy = "vendors.name";
	} else {
			// Default to a safe column if the provided sort column is invalid
		$sortBy = "product_suppliers.created_at";
	}

		// Validate sort direction
	if (!in_array(strtolower($sortDirection), ['asc', 'desc'])) {
		$sortDirection = 'desc';
	}

	$query->orderBy($sortBy, $sortDirection);

		// Apply pagination
	$perPage = $request->input('per_page', 15);

		// Get paginated results
	$productSuppliers = $query->paginate($perPage);

	return response()->json($productSuppliers);
}
	/**
	 * @OA\Post(
	 *     path="/api/product-suppliers",
	 *     operationId="storeProductSupplier",
	 *     tags={"Product Suppliers"},
	 *     summary="Create a new product supplier",
	 *     description="Creates a new product supplier entry",
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"vendor_id", "product_id"},
	 *             @OA\Property(property="product_id", type="integer"),
	 *             @OA\Property(property="vendor_id", type="integer"),
	 *             @OA\Property(property="vendor_sku", type="string"),
	 *             @OA\Property(property="warranty_information", type="string"),
	 *             @OA\Property(property="refund", type="string"),
	 *             @OA\Property(property="delivery_days", type="string"),
	 *             @OA\Property(property="cost_per_item", type="number", format="float"),
	 *             @OA\Property(property="sale_price", type="number", format="float"),
	 *             @OA\Property(property="price", type="number", format="float"),
	 *             @OA\Property(property="margin", type="number", format="float"),
	 *             @OA\Property(property="additional_cost", type="number", format="float"),
	 *             @OA\Property(property="final_cost_price", type="number", format="float"),
	 *             @OA\Property(property="in_stock", type="integer"),
	 *             @OA\Property(property="inventory", type="integer")
	 *         )
	 *     ),
	 *     @OA\Response(response=201, description="Created successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function store(Request $request)
	{
		$data = $request->validate([
			'product_id' => 'required|integer',
			'vendor_id' => 'required|integer',
			'vendor_sku' => 'required|string',
			'cost_per_item' => 'nullable|numeric',
			'sale_price' => 'nullable|numeric',
			'price' => 'nullable|numeric',
			'margin' => 'nullable|numeric',
			'additional_cost' => 'nullable|numeric',
			'final_cost_price' => 'nullable|numeric',
			'in_stock' => 'nullable|integer',
			'inventory' => 'nullable|integer',
			'delivery_days' => ['nullable', Rule::in(app_constants('DELIVERY_DAYS'))],
			'warranty_information' => ['nullable', Rule::in(app_constants('WARRANTY_OPTIONS'))],
			'refund' => ['nullable', Rule::in(app_constants('RETURN_POLICY'))],
		]);

		// Check if a record with the same sku and vendor_id already exists
		$existingEntry = ProductSupplier::where('product_id', $data['product_id'])
		->where('vendor_id', $data['vendor_id'])
		->first();

		if ($existingEntry) {
			return response()->json([
				'message' => 'A product supplier with the Vendor ID already exists.',
			], 422);
		}

		// Validate price logic
		if (
			isset($data['price'], $data['sale_price']) &&
			$data['price'] < $data['sale_price']
		) {
			return response()->json([
				'message' => 'Price cannot be less than sale price.',
			], 422);
		}

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
	 *     path="/api/product-suppliers/{product_id}",
	 *     operationId="getProductSupplierByProductId",
	 *     tags={"Product Suppliers"},
	 *     summary="Get product suppliers by Product ID",
	 *     description="Returns all product suppliers associated with a specific product ID",
	 *     @OA\Parameter(
	 *         name="product_id",
	 *         in="path",
	 *         required=true,
	 *         description="Product ID",
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function show($product_id)
	{
		// Fetch all suppliers associated with the given product_id
		$suppliers = ProductSupplier::with('vendor')->where('product_id', $product_id)->get();

		if ($suppliers->isEmpty()) {
			return response()->json(['message' => 'No product suppliers found for the given product ID'], 404);
		}

		// Format the response for all suppliers
		$response = $suppliers->map(function ($supplier) {
			return [
				'id' => $supplier->id,
				'product_id' => $supplier->product_id,
				'vendor_id' => $supplier->vendor_id,
				'vendor_sku' => $supplier->vendor_sku,
				'vendor_name' => $supplier->vendor ? $supplier->vendor->name : null,
				'warranty_information' => $supplier->warranty_information,
				'refund' => $supplier->refund,
				'delivery_days' => $supplier->delivery_days,
				'cost_per_item' => $supplier->cost_per_item,
				'sale_price' => $supplier->sale_price,
				'price' => $supplier->price,
				'margin' => $supplier->margin,
				'inventory' => $supplier->inventory,
				'additional_cost' => $supplier->additional_cost,
				'final_cost_price' => $supplier->final_cost_price,
				'created_at' => $supplier->created_at,
				'updated_at' => $supplier->updated_at,
			];
		});

		return response()->json([
			'success' => true,
			'message' => __("msg_rec_dtl"),
			'data' => $response
		]);
	}

	/**
	 * @OA\Get(
	 *     path="/api/product-suppliers/{product_id}/{vendor_id}",
	 *     operationId="getProductSupplier",
	 *     tags={"Product Suppliers"},
	 *     summary="Get a product supplier by product_id and vendor_id",
	 *     description="Fetch the details of a product supplier using product_id and vendor_id",
	 *     @OA\Parameter(
	 *         name="product_id",
	 *         in="path",
	 *         required=true,
	 *         description="Product ID associated with the supplier",
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\Parameter(
	 *         name="vendor_id",
	 *         in="path",
	 *         required=true,
	 *         description="Vendor ID associated with the supplier",
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function getproductvendor($product_id, $vendor_id)
	{
		$supplier = ProductSupplier::where('product_id', $product_id)
		->where('vendor_id', $vendor_id)
		->first();

		if (!$supplier) {
			return response()->json(['message' => 'Product supplier not found'], 404);
		}

		return response()->json($supplier, 200);
	}

	/**
	 * @OA\Put(
	 *     path="/api/product-suppliers/{product_id}/{vendor_id}",
	 *     operationId="updateProductSupplier",
	 *     tags={"Product Suppliers"},
	 *     summary="Update a product supplier",
	 *     description="Updates the details of a product supplier based on product_id and vendor_id",
	 *     @OA\Parameter(
	 *         name="product_id",
	 *         in="path",
	 *         required=true,
	 *         description="Product ID associated with the supplier",
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\Parameter(
	 *         name="vendor_id",
	 *         in="path",
	 *         required=true,
	 *         description="Vendor ID associated with the supplier",
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             @OA\Property(property="vendor_sku", type="string"),
	 *             @OA\Property(property="warranty_information", type="string"),
	 *             @OA\Property(property="refund", type="string"),
	 *             @OA\Property(property="delivery_days", type="string"),
	 *             @OA\Property(property="cost_per_item", type="number", format="float"),
	 *             @OA\Property(property="sale_price", type="number", format="float"),
	 *             @OA\Property(property="price", type="number", format="float"),
	 *             @OA\Property(property="margin", type="number", format="float"),
	 *             @OA\Property(property="additional_cost", type="number", format="float"),
	 *             @OA\Property(property="final_cost_price", type="number", format="float"),
	 *             @OA\Property(property="in_stock", type="integer"),
	 *             @OA\Property(property="inventory", type="integer")
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Updated successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function update(Request $request, $product_id, $vendor_id)
	{
		// Find the product supplier using product_id and vendor_id combination
		$supplier = ProductSupplier::where('product_id', $product_id)
		->where('vendor_id', $vendor_id)
		->first();

		if (!$supplier) {
			return response()->json(['message' => 'Product supplier not found'], 404);
		}

		$data = $request->validate([
			'vendor_sku' => 'required|string',
			'cost_per_item' => 'nullable|numeric',
			'sale_price' => 'nullable|numeric',
			'price' => 'nullable|numeric',
			'margin' => 'nullable|numeric',
			'additional_cost' => 'nullable|numeric',
			'final_cost_price' => 'nullable|numeric',
			'in_stock' => 'nullable|integer',
			'inventory' => 'nullable|integer',
			'delivery_days' => ['nullable', Rule::in(app_constants('DELIVERY_DAYS'))],
			'warranty_information' => ['nullable', Rule::in(app_constants('WARRANTY_OPTIONS'))],
			'refund' => ['nullable', Rule::in(app_constants('RETURN_POLICY'))],
		]);

		// Validate price logic
		if (
			isset($data['price'], $data['sale_price']) &&
			$data['price'] < $data['sale_price']
		) {
			return response()->json([
				'message' => 'Price cannot be less than sale price.',
			], 422);
		}

		$data['updated_by'] = auth()->id();

		// Update the supplier with new data
		$supplier->update($data);


		return response()->json([
			'success' => true,
			'message' => __("msg_update"),
			'data' => $supplier
		]);
	}

	/**
	 * @OA\Delete(
	 *     path="/api/product-suppliers/{product_id}/{vendor_id}",
	 *     operationId="deleteProductSupplierByProductAndVendor",
	 *     tags={"Product Suppliers"},
	 *     summary="Delete a product supplier by product and vendor",
	 *     description="Deletes a product supplier using product_id and vendor_id",
	 *     @OA\Parameter(
	 *         name="product_id",
	 *         in="path",
	 *         required=true,
	 *         description="Product ID",
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\Parameter(
	 *         name="vendor_id",
	 *         in="path",
	 *         required=true,
	 *         description="Vendor ID",
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\Response(response=200, description="Deleted successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function destroy($product_id, $vendor_id)
	{
		$supplier = ProductSupplier::where('product_id', $product_id)
		->where('vendor_id', $vendor_id)
		->first();

		if (!$supplier) {
			return response()->json(['message' => 'Product supplier not found'], 404);
		}

		$supplier->delete();

		return response()->json([
			'success' => true,
			'message' => __("msg_dlt")
		], 200);
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
	 *             @OA\Property(property="type", type="string", example="Category", description="Type should be either 'Brand' or 'Category'"),
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
				$leafCategories = Category::getLeafCategories($category);
				$leafCategoryIds = $leafCategories->pluck('id')->toArray();

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
			'Delivery Days' => 'delivery_days',
			'Return Policy' => 'return_policy',
			'Free Shipping' => 'free_shipping',
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
			$excelRepo->setDropdown($spreadsheet, $sheet, $col++ . $row, 'delivery_days', $deliveryTimeOptions, $selectedDeliveryDays);
			$excelRepo->setDropdown($spreadsheet, $sheet, $col++ . $row, 'return_policy', $returnPolicies, $selectedReturnPolicy);
			$excelRepo->setDropdown($spreadsheet, $sheet, $col++ . $row, 'free_shipping', $freeShippingOptions, $selectedFreeShipping);

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
				'Delivery Days' => 'delivery_days',
				'Return Policy' => 'return_policy',
				'Free Shipping' => 'free_shipping',
				'Restocking Fees(%)' => 'restocking_fees',
				'Warranty Information' => 'warranty_information',
			];

			$excelImporter->processExcelImport(
				$request->file('upload_file'),
				$supplierFormatArray,
				'Product Supplier', /* Module name */
				'JOB_PROD_SUPPLIER', /* Job name */
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
			'Delivery Days' => 'delivery_days',
			'Return Policy' => 'return_policy',
			'Free Shipping' => 'free_shipping',
			'Restocking Fees(%)' => 'restocking_fees',
			'Warranty Information' => 'warranty_information',
		];
		$deliveryTimeOptions = app_constants('DELIVERY_DAYS');
		$warrantyOptions = app_constants('WARRANTY_OPTIONS');
		$returnPolicies = app_constants('RETURN_POLICY');
		$inStockOptions = ['Yes', 'No'];
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
		$excelRepo->setDropdown($spreadsheet, $sheet, $col++ . $row, 'delivery_days', $deliveryTimeOptions, '');
		$excelRepo->setDropdown($spreadsheet, $sheet, $col++ . $row, 'return_policy', $returnPolicies, '');
		$excelRepo->setDropdown($spreadsheet, $sheet, $col++ . $row, 'free_shipping', $freeShippingOptions, '');
		$sheet->setCellValue($col++ . $row, '');
		$excelRepo->setDropdown($spreadsheet, $sheet, $col++ . $row, 'warranty_information', $warrantyOptions, '');

		$fileName = 'products_suppliers_import_template' . now()->format('Y-m-d_H-i-s') . '.xlsx';

		return $excelRepo->downloadFile($fileName, $spreadsheet);
	}
}
