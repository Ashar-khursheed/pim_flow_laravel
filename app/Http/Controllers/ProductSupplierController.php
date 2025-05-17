<?php
// app/Http/Controllers/Api/ProductSupplierController.php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ProductSupplier;
use Illuminate\Http\Request;
use App\Models\TransactionLog;
use App\Jobs\ImportSupplierJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use League\Csv\Reader;
use League\Csv\Writer;
use SplTempFileObject;

class ProductSupplierController extends Controller
{
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
	 *             required={"sku", "vendor_id", "product_id"},
	 *             @OA\Property(property="sku", type="string"),
	 *             @OA\Property(property="vendor_sku", type="string"),
	 *             @OA\Property(property="vendor_id", type="integer"),
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
			'sku' => 'required|string',
			'vendor_sku' => 'required|string',
			'vendor_id' => 'required|integer',
			'product_id' => 'nullable|integer', // changed to nullable
			'warranty_information' => 'nullable|string',
			'refund' => 'nullable|string',
			'delivery_days' => 'nullable|string',
			'cost_per_item' => 'nullable|numeric',
			'sale_price' => 'nullable|numeric',
			'price' => 'nullable|numeric',
			'margin' => 'nullable|numeric',
			'inventory' => 'nullable|integer',
			'additional_cost' => 'nullable|numeric',
			'final_cost_price' => 'nullable|numeric',
		]);

		// Check if a record with the same sku and vendor_id already exists
		$existingEntry = ProductSupplier::where('sku', $data['sku'])
		->where('vendor_id', $data['vendor_id'])
		->first();

		if ($existingEntry) {
			return response()->json([
				'message' => 'A product supplier with the same SKU and Vendor ID already exists.',
			], 422);
		}

		// Automatically fetch product_id if not provided
		if (empty($data['product_id']) && !empty($data['sku'])) {
			$product = \DB::table('ec_products')->where('sku', $data['sku'])->first();

			if (!$product) {
				return response()->json([
					'message' => 'No product found with the given SKU.',
				], 422);
			}

			$data['product_id'] = $product->id;
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
   			'sku' => $supplier->sku,
   			'vendor_sku' => $supplier->vendor_sku,
   			'vendor_id' => $supplier->vendor_id,
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

		// Update the supplier with new data
		$supplier->update($request->all());

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
	 *     path="/api/product-suppliers/import",
	 *     summary="Import product suppliers from a CSV file",
	 *     tags={"Product Suppliers"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="multipart/form-data",
	 *             @OA\Schema(
	 *                 required={"file"},
	 *                 @OA\Property(
	 *                     property="file",
	 *                     type="string",
	 *                     format="binary",
	 *                     description="CSV file (.csv) max 10MB"
	 *                 ),
	 *                 @OA\Property(
	 *                     property="chunk_size",
	 *                     type="integer",
	 *                     description="Optional chunk size (default is 100)"
	 *                 )
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Success",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="message", type="string", example="The import process has been scheduled successfully. Please track it under import log.")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=422,
	 *         description="Validation error",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=false),
	 *             @OA\Property(property="message", type="string", example="The uploaded CSV file does not contain any records.")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=403,
	 *         description="Forbidden",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=false),
	 *             @OA\Property(property="message", type="string", example="You don't have permission to access this module.")
	 *         )
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function import(Request $request)
	{
		//  if (!auth()->user()->can('import supplier')) {
		//      return response()->json([
		//          'success' => false,
		//          'message' => "You don't have permission to access this module.",
		//      ]);
		//  }

		try {
			 // Validate request data
			$request->validate([
				'file' => 'required|file|mimes:csv,txt|max:10240',
				'chunk_size' => 'nullable|integer|min:1|max:1000',
			]);

			$mandatoryHeaders = ['ID', 'SKU', 'Vendor Name'];
			$chunkSize = $request->input('chunk_size', 100);
			$file = $request->file('file');

			 // Parse CSV
			$csv = Reader::createFromPath($file->getPathname(), 'r');
			$csv->setHeaderOffset(0);
			$header = $csv->getHeader();
			$records = iterator_to_array($csv->getRecords());
			$totalRows = count($records);

			 // Check mandatory headers
			$missingHeaders = array_diff($mandatoryHeaders, $header);
			if (!empty($missingHeaders)) {
				return response()->json([
					'success' => false,
					'message' => 'Missing mandatory columns: ' . implode(', ', $missingHeaders),
				]);
			}

			if ($totalRows == 0) {
				return response()->json([
					'success' => false,
					'message' => 'The uploaded CSV file does not contain any records. Please ensure the file has valid data and try again.',
				]);
			}

			$fileFormatArray = [
				'ID' => 'id',
				'SKU' => 'sku',
				'Vendor SKU' => 'vendor_sku',
				'Vendor Name' => 'vendor_name',
				'Warranty Information' => 'warranty_information',
				'Refund' => 'refund',
				'Delivery Days' => 'delivery_days',
				'Cost Per Item' => 'cost_per_item',
				'Sale Price' => 'sale_price',
				'Price' => 'price',
				'Margin' => 'margin',
				'Inventory' => 'inventory',
				'Additional Cost' => 'additional_cost',
				'Final Cost Price' => 'final_cost_price',
			];

			 // Prepare jobs
			$chunkedJobs = [];
			$chunks = array_chunk($records, $chunkSize);
			foreach ($chunks as $chunk) {
				$data = [
					'header' => $header,
					'chunk' => $chunk,
					'userId' => auth()->id(),
					'fileFormatArray' => $fileFormatArray,
					 // batch_id will be injected later from Batch
				];
				$chunkedJobs[] = new ImportSupplierJob($data);
			}

			 // Create and dispatch batch
			$batch = Bus::batch($chunkedJobs)
			->before(function (Batch $batch) use ($totalRows) {
				$descArray = [
					"Total Count" => $totalRows,
					"Success Count" => 0,
					"Failed Count" => 0,
					"Errors" => [],
				];

				dd("123");
				$log = new TransactionLog();
				$log->module = "Product Supplier";
				$log->action = "Import";
				$log->identifier = $batch->id;
				$log->status = 'In-progress';
				$log->description = json_encode($descArray, JSON_UNESCAPED_UNICODE);
				$log->created_by = auth()->id() ?? null;
				$log->created_at = now();
				$log->save();
			})
			->finally(function (Batch $batch) {
				$log = TransactionLog::where('identifier', $batch->id)->first();
				if ($log) {
					TransactionLog::where('id', $log->id)->update([
						'status' => 'Completed',
					]);
				}
			})
			->name('Import Suppliers')
			->onQueue('JOB6')
			->dispatch();

			return response()->json([
				'success' => true,
				'message' => 'The import process has been scheduled successfully. Please track it under import log.',
			]);

		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => $e->getMessage(),
			]);
		}
	}

	/**
	 * @OA\Get(
	 *     path="/api/product-suppliers/export",
	 *     operationId="exportProductSuppliers",
	 *     tags={"Product Suppliers"},
	 *     summary="Export product suppliers to CSV",
	 *     description="Exports all product suppliers to a CSV file",
	 *     @OA\Parameter(
	 *         name="vendor_id",
	 *         in="query",
	 *         required=false,
	 *         description="Filter by vendor ID",
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\Parameter(
	 *         name="product_id",
	 *         in="query",
	 *         required=false,
	 *         description="Filter by product ID",
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="CSV file download",
	 *         @OA\MediaType(mediaType="text/csv")
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function export(Request $request)
	{
		// Create query with filters
		$query = ProductSupplier::query();

		if ($request->has('vendor_id')) {
			$query->where('vendor_id', $request->vendor_id);
		}

		if ($request->has('product_id')) {
			$query->where('product_id', $request->product_id);
		}

		// Load relationships
		$suppliers = $query->with('vendor')->get();

		// Create CSV
		$csv = Writer::createFromFileObject(new SplTempFileObject());

		// Add headers
		$csv->insertOne([
			'ID',
			'SKU',
			'Vendor SKU',
			'Vendor ID',
			'Vendor Name',
			'Product ID',
			'Warranty Information',
			'Refund',
			'Delivery Days',
			'Cost Per Item',
			'Sale Price',
			'Price',
			'Margin',
			'Inventory',
			'Additional Cost',
			'Final Cost Price'
		]);

		// Add rows
		foreach ($suppliers as $supplier) {
			$csv->insertOne([
				$supplier->id,
				$supplier->sku,
				$supplier->vendor_sku,
				$supplier->vendor_id,
				$supplier->vendor ? $supplier->vendor->name : '',
				$supplier->product_id,
				$supplier->warranty_information,
				$supplier->refund,
				$supplier->delivery_days,
				$supplier->cost_per_item,
				$supplier->sale_price,
				$supplier->price,
				$supplier->margin,
				$supplier->inventory,
				$supplier->additional_cost,
				$supplier->final_cost_price
			]);
		}

		// Generate filename with date
		$filename = 'product_suppliers_' . date('Y-m-d') . '.csv';

		// Set headers for download
		$headers = [
			'Content-Type' => 'text/csv',
			'Content-Disposition' => 'attachment; filename="' . $filename . '"',
			'Cache-Control' => 'no-cache, no-store, must-revalidate',
			'Pragma' => 'no-cache',
			'Expires' => '0'
		];

		// Return the CSV file as a download
		return response($csv->getContent(), 200, $headers);
	}

	/**
	 * @OA\Get(
	 *     path="/api/product-suppliers/template",
	 *     operationId="downloadSupplierTemplate",
	 *     tags={"Product Suppliers"},
	 *     summary="Download import template",
	 *     description="Downloads a CSV template for product supplier imports",
	 *     @OA\Response(
	 *         response=200,
	 *         description="CSV template download",
	 *         @OA\MediaType(mediaType="text/csv")
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function downloadTemplate()
	{
		// Create CSV
		$csv = Writer::createFromFileObject(new SplTempFileObject());

		// Add headers
		$csv->insertOne([
			'ID',
			'SKU',
			'Vendor SKU',
			'Vendor ID',
			'Warranty Information',
			'Refund',
			'Delivery Days',
			'Cost Per Item',
			'Sale Price',
			'Price',
			'Margin',
			'Inventory',
			'Additional Cost',
			'Final Cost Price'
		]);

		// Add sample row
		$csv->insertOne([
			'', // Leave ID blank for new entries
			'PROD001',
			'V-001',
			'1',
			'12 months warranty',
			'30 days refund policy',
			'3-5',
			'10.50',
			'15.75',
			'19.99',
			'25',
			'100',
			'1.50',
			'12.00'
		]);

		// Generate filename
		$filename = 'supplier_import_template.csv';

		// Set headers for download
		$headers = [
			'Content-Type' => 'text/csv',
			'Content-Disposition' => 'attachment; filename="' . $filename . '"',
			'Cache-Control' => 'no-cache, no-store, must-revalidate',
			'Pragma' => 'no-cache',
			'Expires' => '0'
		];

		// Return the CSV file as a download
		return response($csv->getContent(), 200, $headers);
	}
}
