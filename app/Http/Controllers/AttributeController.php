<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Botble\Base\Supports\Breadcrumb;
use Illuminate\Support\Facades\Bus;
use Illuminate\Bus\Batch;

use App\Models\Attribute;
use App\Models\Category;
use App\Models\Product;
use App\Models\TransactionLog;

use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

use App\Repository\ExcelRepository;

use App\Jobs\ImportProductAttributeJob;

class AttributeController extends BaseController
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
	 * Display a listing of the resource.
	 */
	/**
	 * @OA\Get(
	 *     path="/api/attributes",
	 *     summary="Get Attribute List",
	 *     description="Fetches a list of attributes.",
	 *     tags={"Attributes"},
	 *     @OA\Parameter(
	 *         name="page",
	 *         in="query",
	 *         description="Page number for pagination. Starts from 1.",
	 *         required=true,
	 *         example=1,
	 *         @OA\Schema(
	 *             type="integer",
	 *             minimum=1
	 *         )
	 *     ),
	 *     @OA\Parameter(
	 *         name="length",
	 *         in="query",
	 *         description="Number of records per page.",
	 *         required=true,
	 *         example=20,
	 *         @OA\Schema(
	 *             type="integer",
	 *             minimum=1
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function index(Request $request)
	{
		$records = Attribute::query();

		if($request->filled('page') && $request->filled('length')){
			$page = $request->input('page');
			$length = $request->input('length');
			$records = $records->offset(($page - 1)*$length)->limit($length);
		}

		$records = $records->get();

		return response()->json([
			'success' => true,
			'message' => 'Attribute List',
			'data' => $records
		]);
	}

	/**
	 * Store a newly created resource in storage.
	 */
	/**
	 * @OA\Post(
	 *     path="/api/attributes",
	 *     summary="Create a new attribute",
	 *     description="Creates a new attribute with name, code, and type.",
	 *     tags={"Attributes"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"name", "code", "type"},
	 *             @OA\Property(property="name", type="string", example="Color"),
	 *             @OA\Property(property="code", type="string", example="color"),
	 *             @OA\Property(property="type", type="string", example="text")
	 *         )
	 *     ),
	 *     @OA\Response(response=201, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function store(Request $request)
	{
		/* Validate request data */
		$request->validate([
			'name' => "required|unique:attributes,name",
			'code' => "required|unique:attributes,code",
			'type' => "required"
		]);

		$attribute = new Attribute();
		$attribute->name = $request->name;
		$attribute->code = $request->code;
		$attribute->type = $request->type;
		$attribute->created_at = now();
		$attribute->updated_at = now();
		$attribute->save();

		return response()->json([
			'success' => true,
			'message' => 'Attribute created successfully',
			'data' => $attribute
		]);
	}

	/**
	 * Display the specified resource.
	 */
	/**
	 * @OA\Get(
	 *     path="/api/attributes/{attribute_id}",
	 *     summary="Get attribute details",
	 *     description="Fetches attribute details based on the given attribute ID.",
	 *     tags={"Attributes"},
	 *     @OA\Parameter(
	 *         name="attribute_id",
	 *         in="path",
	 *         required=true,
	 *         description="ID of the attribute",
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function show($attributeId)
	{
		$attribute = Attribute::find($attributeId);
		if (!$attribute) {
			return response()->json([
				'success' => false,
				'message' => 'Attribute does not exist.'
			]);
		}

		$attribute->validations = json_decode($attribute->validations);

		return response()->json([
			'success' => true,
			'message' => 'Attribute detail',
			'data' => $attribute
		]);
	}

	/**
	 * Update the specified resource in storage.
	 */
	/**
	 * @OA\Put(
	 *     path="/api/attributes/{id}",
	 *     summary="Update an existing attribute",
	 *     description="Updates an attribute's details, including name, code, type, required status, and validations.",
	 *     tags={"Attributes"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         required=true,
	 *         description="ID of the attribute to update",
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"name", "code", "type", "is_required", "validations"},
	 *             @OA\Property(property="name", type="string", example="Size"),
	 *             @OA\Property(property="code", type="string", example="size"),
	 *             @OA\Property(property="type", type="string", example="dropdown"),
	 *             @OA\Property(property="is_required", type="boolean", example=true),
	 *             @OA\Property(
	 *                 property="validations",
	 *                 type="object",
	 *                 @OA\Property(property="min", type="integer", example=250),
	 *                 @OA\Property(property="max", type="integer", example=260),
	 *                 @OA\Property(property="required", type="boolean", example=true)
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function update(Request $request, $attributeId)
	{
		$attribute = Attribute::find($attributeId);
		if (!$attribute) {
			return response()->json([
				'success' => false,
				'message' => 'Attribute does not exist.'
			]);
		}

		/* Validate request data */
		$request->validate([
			'name' => "required|unique:attributes,name,".$attributeId,
			'code' => "required|unique:attributes,code,".$attributeId,
			'type' => "required"
		]);

		$input = $request->all();

		if ($input['validations']) {
			$attribute->validations = json_encode($input['validations']);
			unset($input['validations']); /* Remove processed field */
		}

		/* Assign remaining valid fields to the attribute */
		foreach ($input as $key => $value) {
			$attribute->$key = $value;
		}

		/* Save the attribute */
		$attribute->save();

		/* Return success response */
		return response()->json([
			'success' => true,
			'message' => 'Attribute updated successfully.',
			'data' => $attribute->toArray()
		]);
	}

	/**
	 * Remove the specified resource from storage.
	 */
	/**
	 * @OA\Delete(
	 *     path="/api/attributes/{id}",
	 *     summary="Delete an attribute",
	 *     description="Deletes an attribute.",
	 *     operationId="deleteAttribute",
	 *     tags={"Attributes"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         description="ID of the attribute to delete",
	 *         required=true,
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function destroy($id)
	{
		$attribute = Attribute::find($id);

		if (!$attribute) {
			return response()->json([
				'success' => false,
				'message' => 'Record does not exist with given ID.'
			], 404);
		}

		/* Check if attribute is attached to any attribute group */
		if ($attribute->attributeGroups()->exists()) {
			return response()->json([
				'success' => false,
				'message' => 'Attribute is associated with an attribute group and cannot be deleted.'
			], 400);
		}

		/* Proceed with deletion */
		$attribute->delete();

		return response()->json([
			'success' => true,
			'message' => 'Attribute deleted successfully'
		], 200);
	}

	/**
	 * @OA\Post(
	 *     path="/api/attributes/export",
	 *     summary="Export product attributes data to Excel",
	 *     tags={"Attributes"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"parent_category_id", "range_from", "range_to"},
	 *             @OA\Property(property="parent_category_id", type="integer", example=1, description="Parent category ID"),
	 *             @OA\Property(property="range_from", type="integer", example=10, description="Starting range"),
	 *             @OA\Property(property="range_to", type="integer", example=50, description="Ending range")
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Success",
	 *         @OA\MediaType(
	 *             mediaType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
	 *         )
	 * 	   ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function export(Request $request)
	{
		/* Validate request data */
		$request->validate([
			'parent_category_id' => 'required|integer',
			'range_from' => 'required|integer',
			'range_to' => 'required|integer',
		]);

		$parentCategory = Category::find($request->parent_category_id);

		if (!$parentCategory) {
			return response()->json([
				'success' => false,
				'message' => 'Parent category does not exist.'
			]);
		}

		$leafCategories = Category::getLeafCategories($parentCategory);
		$leafCategoryIds = $leafCategories ? $leafCategories->pluck('id')->toArray() : [];

		/* Fetch products with range */
		$products = Product::whereHas('categories', fn($query) => $query->whereIn('category_id', $leafCategoryIds))
		->offset($request->range_from - 1)
		->limit($request->range_to - $request->range_from + 1)
		->orderBy('id', 'asc')
		->get(['id', 'sku', 'name']);

		/* Fetch category specifications and transform */
		$catSpecs = Category::with('categoryAttributes:id,name,type')
		->whereIn('id', $leafCategoryIds)
		->get(['id']);

		/* Flatten attributes and remove duplicates by 'id' */
		$uniqueAttributes = collect($catSpecs->pluck('categoryAttributes')->flatten())
		->unique('id')
		->map(fn($attr) => [
			'attribute_id' => $attr['id'],
			'name' => $attr['name'],
			'type' => $attr['type'],
			'value' => $attr->attributeValues->pluck('attribute_value')->toArray() ?? [],
		])
		->sortBy('attribute_id')
		->keyBy('attribute_id') // Set attribute_id as the key
		->map(fn($attr) => [
			'name' => $attr['name'],
			'type' => $attr['type'],
			'value' => $attr['value'],
		])
		->toArray();

		/* Prepare spreadsheet */
		$attributeNames = array_column($uniqueAttributes, 'name');
		$header = array_merge(['ID', 'SKU', 'Name'], $attributeNames);

		$spreadsheet = $this->excel->newSpreadsheet();
		$spreadsheet->setActiveSheetIndex(0);
		$sheet = $spreadsheet->getActiveSheet();

		/* Set headers */
		$this->excel->setHeader($sheet, $header);

		/* Populate data */
		$row = 2;
		foreach ($products as $product) {

			$existingAttributes = $product->productAttributes->pluck('value', 'attribute_id')->toArray();
			$col = 'A';

			/* Set basic product details */
			$sheet->setCellValue($col++ . $row, $product->id);
			$sheet->setCellValue($col++ . $row, $product->sku);
			$sheet->setCellValue($col++ . $row, $product->name);

			foreach ($uniqueAttributes as $attributeId => $attributeDetail) {
				$existingVal = $existingAttributes[$attributeId] ?? '';

				$cell = $col++ . $row;
				if (!empty($attributeDetail['value']) && $attributeDetail['type'] == 'select') {
					$this->excel->setDropdown($spreadsheet, $sheet, $cell, $attributeDetail['name'], $attributeDetail['value'], $existingVal);
				} else {
					$sheet->setCellValue($cell, $existingVal);
				}
			}
			$row++;
		}

		// Create response
		$response = new StreamedResponse(function () use ($spreadsheet) {
			$writer = new Xlsx($spreadsheet);
			$writer->save('php://output');
		});

		$fileName = "$parentCategory->name Products $request->range_from-$request->range_to.xlsx";
		$fileName = strtolower(str_replace(' ', '_', trim($fileName)));

		$response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		$response->headers->set('Content-Disposition', $response->headers->makeDisposition(
			ResponseHeaderBag::DISPOSITION_ATTACHMENT, $fileName
		));

		return $response;
	}

	/**
	 * @OA\Post(
	 *     path="/api/attributes/import",
	 *     summary="Import product attributes from an Excel file",
	 *     tags={"Attributes"},
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
	 *     @OA\Response(response=200, description="Success",
	 *         @OA\MediaType(
	 *             mediaType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
	 *         )
	 * 	   ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function import(Request $request)
	{
		try {
			/* Validate request data */
			$request->validate([
				'upload_file' => 'required|file|mimes:xlsx|max:5120',
			]);

			$mandatoryHeaders = ['ID', 'SKU', 'Name'];

			$file = $request->file('upload_file');
			$spreadsheet = $this->excel->loadFile($file->getRealPath());
			$sheet = $spreadsheet->getActiveSheet();
			$data = $sheet->toArray();
			$header = array_shift($data);

			/* Check required header */
			$missingHeaders = array_diff($mandatoryHeaders, $header);
			if (!empty($missingHeaders)) {
				return response()->json([
					'success' => false,
					'message' => 'Missing mandatory columns: ' . implode(', ', $missingHeaders)
				]);
			}

			// dd($data[0][0]);
			$product = Product::find($data[0][0]);
			dd($product->productCategoryAttributes()->toArray());

			$totalRecords = count($data);
			if ($totalRecords == 0) {
				return response()->json([
					'success' => false,
					'message' => 'The uploaded Excel file does not contain any records. Please ensure the file has valid data and try again.'
				]);
			}

			/* Create batch */
			$batch = Bus::batch([])
			->before(function (Batch $batch) use ($totalRecords) {
				$descArray = [
					"Total Count" => $totalRecords,
					"Success Count" => 0,
					"Failed Count" => 0,
					"Errors" => []
				];
				/* Save transaction log */
				$log = new TransactionLog();
				$log->module = "Product Specification";
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
				TransactionLog::where('id', $log->id)->update([
					'status' => 'Completed',
				]);
			})
			->name("Import Product Attributes")
			->dispatch();

			/* Chunk the data into manageable portions (e.g., 100 rows per chunk) */
			$chunkSize = 100;
			$chunks = array_chunk($data, $chunkSize);

			foreach ($chunks as $chunk) {
				$data = [
					'header' => $header,
					'chunk' => $chunk
				];
				$batch->add(new ImportProductAttributeJob($data));
			}
			return response()->json([
				'success' => true,
				'message' => 'The import process has been scheduled successfully. Please track it under import log.'
			]);
		} catch(\Exception $exception) {
			return response()->json([
				'success' => false,
				'message' => $exception->getMessage()
			]);
		}
	}
}
