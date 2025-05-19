<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Bus\Batch;

use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use App\Repository\ExcelRepository;

use App\Models\ProductSupplier;
use App\Models\TransactionLog;

use App\Jobs\ImportProductSupplierJob;

class ProductSupplierController extends BaseController
{
	/**
	 * The excel repository instance.
	 */
	protected $excel;

	/**
	 * Create a new job instance.
	 */
	public function __construct(ExcelRepository $excel)
	{
		$this->excel = $excel;
	}

	/**
	 * @OA\Get(
	 *     path="/api/product-suppliers",
	 *     operationId="getProductSuppliers",
	 *     tags={"Product Suppliers"},
	 *     summary="Get all product suppliers",
	 *     description="Returns a list of all product suppliers",
	 *     @OA\Response(
	 *         response=200,
	 *         description="Successful operation",
	 *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/ProductSupplier"))
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function index()
	{
		return ProductSupplier::all();
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
	 *     @OA\Response(
	 *         response=201,
	 *         description="Created successfully",
	 *         @OA\JsonContent(ref="#/components/schemas/ProductSupplier")
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function store(Request $request)
	{
		$data = $request->validate([
			'product_id' => 'required|integer',
			'vendor_id' => 'required|integer',
			'vendor_sku' => 'required|string',
			'warranty_information' => 'nullable|string',
			'refund' => 'nullable|string',
			'delivery_days' => 'nullable|string',
			'cost_per_item' => 'nullable|numeric',
			'sale_price' => 'nullable|numeric',
			'price' => 'nullable|numeric',
			'margin' => 'nullable|numeric',
			'additional_cost' => 'nullable|numeric',
			'final_cost_price' => 'nullable|numeric',
			'in_stock' => 'nullable|integer',
			'inventory' => 'nullable|integer',
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

		return ProductSupplier::create($data);
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
	 *     @OA\Response(
	 *         response=200,
	 *         description="Successful operation",
	 *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/ProductSupplier"))
	 *     ),
	 *     @OA\Response(
	 *         response=404,
	 *         description="Product supplier not found"
	 *     ),
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

   	return response()->json($response);
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
	 *     @OA\Response(
	 *         response=200,
	 *         description="Successful retrieval",
	 *         @OA\JsonContent(ref="#/components/schemas/ProductSupplier")
	 *     ),
	 *     @OA\Response(
	 *         response=404,
	 *         description="Product supplier not found"
	 *     ),
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
	 *             @OA\Property(property="sku", type="string"),
	 *             @OA\Property(property="vendor_sku", type="string"),
	 *             @OA\Property(property="vendor_id", type="integer"),
	 *             @OA\Property(property="product_id", type="integer"),
	 *             @OA\Property(property="warranty_information", type="string"),
	 *             @OA\Property(property="refund", type="string"),
	 *             @OA\Property(property="delivery_days", type="string"),
	 *             @OA\Property(property="cost_per_item", type="number", format="float"),
	 *             @OA\Property(property="sale_price", type="number", format="float"),
	 *             @OA\Property(property="price", type="number", format="float"),
	 *             @OA\Property(property="margin", type="number", format="float"),
	 *             @OA\Property(property="inventory", type="integer"),
	 *             @OA\Property(property="additional_cost", type="number", format="float"),
	 *             @OA\Property(property="final_cost_price", type="number", format="float")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Updated successfully",
	 *         @OA\JsonContent(ref="#/components/schemas/ProductSupplier")
	 *     ),
	 *     @OA\Response(
	 *         response=404,
	 *         description="Product supplier not found"
	 *     ),
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
			'warranty_information' => 'nullable|string',
			'refund' => 'nullable|string',
			'delivery_days' => 'nullable|string',
			'cost_per_item' => 'nullable|numeric',
			'sale_price' => 'nullable|numeric',
			'price' => 'nullable|numeric',
			'margin' => 'nullable|numeric',
			'additional_cost' => 'nullable|numeric',
			'final_cost_price' => 'nullable|numeric',
			'in_stock' => 'nullable|integer',
			'inventory' => 'nullable|integer',
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

		return response()->json($supplier);
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
	 *     @OA\Response(
	 *         response=200,
	 *         description="Deleted successfully",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="message", type="string", example="Deleted successfully")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=404,
	 *         description="Product supplier not found"
	 *     ),
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

		return response()->json(['message' => 'Deleted successfully']);
	}

	/**
	 * @OA\Post(
	 *     path="/api/product-suppliers/export",
	 *     summary="Export product suppliers data to Excel",
	 *     tags={"Product Suppliers"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"range_from", "range_to"},
	 *             @OA\Property(property="product_id", type="integer", example=1, description="Filter by product ID"),
	 *             @OA\Property(property="range_from", type="integer", example=1, description="Starting range (must be >=1)"),
	 *             @OA\Property(property="range_to", type="integer", example=50, description="Ending range (must be >= range_from and max 2000 more)")
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function export(Request $request)
	{
		if (!auth()->user()->can('export product suppliers')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}
		/* Validate request data */
		$request->validate([
			'range_from' => 'integer|min:1',
			'range_to' => 'integer|gte:range_from|max:' . ($request->range_from + 2000),
		]);

		$query = ProductSupplier::with(['product:id,name,sku', 'vendor:id,name']);

		if ($request->has('product_id')) {
			$query->where('product_id', $request->product_id);
		}

		$suppliers = $query->get();

		if ($suppliers->isEmpty()) {
			return response()->json([
				'success' => false,
				'message' => 'No supplier exist for product.'
			]);
		}

		$header = [
			'ID',
			'Product name',
			'SKU',
			'Vendor Name',
			'Vendor SKU',
			'Cost Per Item',
			'Selling Type',
			'Additional Cost',
			'Price',
			'Sale Price',
			'Inventory',
			'In Stock',
			'Delivery Days',
			'Warranty Information',
			'Refund',
			'Final Cost Price'
			'Margin',
		];

		/* Initialize spreadsheet */
		$spreadsheet = $this->excel->newSpreadsheet();
		$spreadsheet->setActiveSheetIndex(0);
		$sheet = $spreadsheet->getActiveSheet();

		/* Set headers */
		$this->excel->setHeader($sheet, $header);

		/* Populate data */
		$row = 2;
		foreach ($suppliers as $supplier) {
			$col = 'A';

			/* Set product details */
			$sheet->setCellValue($col++ . $row, $supplier->id ?? '');
			$sheet->setCellValue($col++ . $row, $supplier->product->name ?? '');
			$sheet->setCellValue($col++ . $row, $supplier->product->sku ?? '');
			$sheet->setCellValue($col++ . $row, $supplier->vendor->name ?? '');
			$sheet->setCellValue($col++ . $row, $supplier->vendor_sku ?? '');
			$sheet->setCellValue($col++ . $row, $supplier->warranty_information ?? '');
			$sheet->setCellValue($col++ . $row, $supplier->refund ?? '');
			$sheet->setCellValue($col++ . $row, $supplier->delivery_days ?? '');
			$sheet->setCellValue($col++ . $row, $supplier->cost_per_item ?? '');
			$sheet->setCellValue($col++ . $row, $supplier->additional_cost ?? '');
			$sheet->setCellValue($col++ . $row, $supplier->price ?? '');
			$sheet->setCellValue($col++ . $row, $supplier->sale_price ?? '');
			$sheet->setCellValue($col++ . $row, $supplier->final_cost_price ?? '');
			$sheet->setCellValue($col++ . $row, $supplier->margin ?? '');
			$sheet->setCellValue($col++ . $row, $supplier->in_stock === null ? '' : ($supplier->in_stock == 1 ? 'Yes' : 'No'));
			$sheet->setCellValue($col++ . $row, $supplier->inventory ?? '');
			$row++;
		}

		/* Generate response */
		$response = new StreamedResponse(function () use ($spreadsheet) {
			$writer = new Xlsx($spreadsheet);
			$writer->save('php://output');
		});

		$fileName = strtolower(str_replace(' ', '_', trim("products_suppliers_{$request->range_from}-{$request->range_to} ".date('Y-m-d').".xlsx")));

		$response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		$response->headers->set('Content-Disposition', $response->headers->makeDisposition(
			ResponseHeaderBag::DISPOSITION_ATTACHMENT, $fileName
		));

		return $response;
	}

	// /**
	//  * @OA\Post(
	//  *     path="/api/product-suppliers/import",
	//  *     summary="Import product suppliers from an Excel file",
	//  *     tags={"Product Suppliers"},
	//  *     @OA\RequestBody(
	//  *         required=true,
	//  *         @OA\MediaType(
	//  *             mediaType="multipart/form-data",
	//  *             @OA\Schema(
	//  *                 required={"upload_file"},
	//  *                 @OA\Property(property="upload_file", type="string", format="binary", description="Excel file (.xlsx) max 2MB")
	//  *             )
	//  *         )
	//  *     ),
	//  *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	//  *     security={{"bearerAuth":{}}}
	//  * )
	//  */
	// public function import(Request $request)
	// {
	// 	if (!auth()->user()->can('import product suppliers')) {
	// 		return response()->json([
	// 			'success' => false,
	// 			'message' => "You don't have permission to access this module.",
	// 		]);
	// 	}
	// 	try {
	// 		/* Validate request data */
	// 		$request->validate([
	// 			'upload_file' => 'required|file|mimes:xlsx|max:2048',
	// 		]);

	// 		$requiredHeader = [
	// 			'ID',
	// 			'Product name',
	// 			'SKU',
	// 			'Vendor Name',
	// 			'Vendor SKU',
	// 			'Warranty Information',
	// 			'Refund',
	// 			'Delivery Days',
	// 			'Cost Per Item',
	// 			'Additional Cost',
	// 			'Price',
	// 			'Sale Price',
	// 			'Final Cost Price'
	// 			'Margin',
	// 			'In Stock',
	// 			'Inventory',
	// 		];

	// 		$file = $request->file('upload_file');
	// 		$spreadsheet = $this->excel->loadFile($file->getRealPath());
	// 		$sheet = $spreadsheet->getActiveSheet();
	// 		$data = $sheet->toArray();
	// 		$header = array_shift($data);

	// 		/* Check required header */
	// 		$missingHeaders = array_diff($requiredHeader, $header);
	// 		if (!empty($missingHeaders)) {
	// 			return response()->json([
	// 				'success' => false,
	// 				'message' => 'Missing mandatory columns: ' . implode(', ', $missingHeaders)
	// 			]);
	// 		}

	// 		$totalRecords = count($data);
	// 		if ($totalRecords == 0) {
	// 			return response()->json([
	// 				'success' => false,
	// 				'message' => 'The uploaded Excel file does not contain any records. Please ensure the file has valid data and try again.'
	// 			]);
	// 		}

	// 		/* Create batch */
	// 		$batch = Bus::batch([])
	// 		->before(function (Batch $batch) use ($totalRecords) {
	// 			$descArray = [
	// 				"Total Count" => $totalRecords,
	// 				"Success Count" => 0,
	// 				"Failed Count" => 0,
	// 				"Errors" => []
	// 			];
	// 			/* Save transaction log */
	// 			$log = new TransactionLog();
	// 			$log->module = "Product Supplier";
	// 			$log->action = "Import";
	// 			$log->identifier = $batch->id;
	// 			$log->status = 'In-progress';
	// 			$log->description = json_encode($descArray, JSON_UNESCAPED_UNICODE);
	// 			$log->created_by = auth()->id() ?? null;
	// 			$log->created_at = now();
	// 			$log->save();
	// 		})
	// 		->finally(function (Batch $batch) {
	// 			$log = TransactionLog::where('identifier', $batch->id)->first();
	// 			TransactionLog::where('id', $log->id)->update([
	// 				'status' => 'Completed',
	// 			]);
	// 		})
	// 		->name("Import Product Supplier")
	// 		->dispatch();

	// 		/* Chunk the data into manageable portions (e.g., 100 rows per chunk) */
	// 		$chunkSize = 50;
	// 		$chunks = array_chunk($data, $chunkSize);

	// 		foreach ($chunks as $chunk) {
	// 			$data = [
	// 				'header' => $header,
	// 				'chunk' => $chunk
	// 			];
	// 			$batch->options['queue'] = 'JOB_SUPPLIERS';
	// 			$batch->add(new ImportProductSupplierJob($data));
	// 		}
	// 		return response()->json([
	// 			'success' => true,
	// 			'message' => 'The import process has been scheduled successfully. Please track it under import log.'
	// 		]);
	// 	} catch(\Exception $exception) {
	// 		return response()->json([
	// 			'success' => false,
	// 			'message' => $exception->getMessage()
	// 		]);
	// 	}
	// }

	// /**
	//  * @OA\Get(
	//  *     path="/api/product-suppliers/template",
	//  *     summary="Download import template",
	//  *     description="Downloads an Excel template for product supplier imports",
	//  *     tags={"Product Suppliers"},
	//  *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	//  *     security={{"bearerAuth":{}}}
	//  * )
	//  */
	// public function downloadTemplate()
	// {
	// 	/* Initialize spreadsheet */
	// 	$spreadsheet = $this->excel->newSpreadsheet();
	// 	$spreadsheet->setActiveSheetIndex(0);
	// 	$sheet = $spreadsheet->getActiveSheet();

	// 	/* Set headers */
	// 	$this->excel->setHeader($sheet, $header);

	// 	/* Populate data */
	// 	$row = 2;
	// 	foreach ($suppliers as $supplier) {
	// 		$col = 'A';

	// 		/* Set product details */
	// 		$sheet->setCellValue($col++ . $row, $supplier->id ?? '');
	// 		$sheet->setCellValue($col++ . $row, $supplier->product->name ?? '');
	// 		$sheet->setCellValue($col++ . $row, $supplier->product->sku ?? '');
	// 		$sheet->setCellValue($col++ . $row, $supplier->vendor->name ?? '');
	// 		$sheet->setCellValue($col++ . $row, $supplier->vendor_sku ?? '');
	// 		$sheet->setCellValue($col++ . $row, $supplier->warranty_information ?? '');
	// 		$sheet->setCellValue($col++ . $row, $supplier->refund ?? '');
	// 		$sheet->setCellValue($col++ . $row, $supplier->delivery_days ?? '');
	// 		$sheet->setCellValue($col++ . $row, $supplier->cost_per_item ?? '');
	// 		$sheet->setCellValue($col++ . $row, $supplier->additional_cost ?? '');
	// 		$sheet->setCellValue($col++ . $row, $supplier->price ?? '');
	// 		$sheet->setCellValue($col++ . $row, $supplier->sale_price ?? '');
	// 		$sheet->setCellValue($col++ . $row, $supplier->final_cost_price ?? '');
	// 		$sheet->setCellValue($col++ . $row, $supplier->margin ?? '');
	// 		$sheet->setCellValue($col++ . $row, $supplier->in_stock === null ? '' : ($supplier->in_stock == 1 ? 'Yes' : 'No'));
	// 		$sheet->setCellValue($col++ . $row, $supplier->inventory ?? '');
	// 		$row++;
	// 	}

	// 	/* Generate response */
	// 	$response = new StreamedResponse(function () use ($spreadsheet) {
	// 		$writer = new Xlsx($spreadsheet);
	// 		$writer->save('php://output');
	// 	});

	// 	$fileName = strtolower(str_replace(' ', '_', trim("products_suppliers_{$request->range_from}-{$request->range_to} ".date('Y-m-d').".xlsx")));

	// 	$response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	// 	$response->headers->set('Content-Disposition', $response->headers->makeDisposition(
	// 		ResponseHeaderBag::DISPOSITION_ATTACHMENT, $fileName
	// 	));

	// 	return $response;
	// 	// Create CSV
	// 	$csv = Writer::createFromFileObject(new SplTempFileObject());

	// 	// Add headers
	// 	$csv->insertOne([
	// 		'ID',
	// 		'SKU',
	// 		'Vendor SKU',
	// 		'Vendor ID',
	// 		'Warranty Information',
	// 		'Refund',
	// 		'Delivery Days',
	// 		'Cost Per Item',
	// 		'Sale Price',
	// 		'Price',
	// 		'Margin',
	// 		'Inventory',
	// 		'Additional Cost',
	// 		'Final Cost Price'
	// 	]);

	// 	// Add sample row
	// 	$csv->insertOne([
	// 		'', // Leave ID blank for new entries
	// 		'PROD001',
	// 		'V-001',
	// 		'1',
	// 		'12 months warranty',
	// 		'30 days refund policy',
	// 		'3-5',
	// 		'10.50',
	// 		'15.75',
	// 		'19.99',
	// 		'25',
	// 		'100',
	// 		'1.50',
	// 		'12.00'
	// 	]);

	// 	// Generate filename
	// 	$filename = 'supplier_import_template.csv';

	// 	// Set headers for download
	// 	$headers = [
	// 		'Content-Type' => 'text/csv',
	// 		'Content-Disposition' => 'attachment; filename="' . $filename . '"',
	// 		'Cache-Control' => 'no-cache, no-store, must-revalidate',
	// 		'Pragma' => 'no-cache',
	// 		'Expires' => '0'
	// 	];

	// 	// Return the CSV file as a download
	// 	return response($csv->getContent(), 200, $headers);
	// }
}
