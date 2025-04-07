<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Bus;
use Illuminate\Bus\Batch;

use App\Models\SeoManagement;
use App\Models\TransactionLog;

use App\Jobs\ImportSeoDetailJob;

class SeoManagementController extends Controller
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
		//
	}

	/**
	 * Store a newly created resource in storage.
	 */
	public function store(Request $request)
	{
		//
	}

	/**
	 * Display the specified resource.
	 */
	public function show(SeoManagement $seoManagement)
	{
		//
	}

	/**
	 * Show the form for editing the specified resource.
	 */
	public function edit(SeoManagement $seoManagement)
	{
		//
	}

	/**
	 * Update the specified resource in storage.
	 */
	public function update(Request $request, SeoManagement $seoManagement)
	{
		//
	}

	/**
	 * Remove the specified resource from storage.
	 */
	public function destroy(SeoManagement $seoManagement)
	{
		//
	}


	/**
	 * @OA\Post(
	 *     path="/api/seo-management/import",
	 *     summary="Import seo details from an Excel file",
	 *     tags={"SEO Management"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="multipart/form-data",
	 *             @OA\Schema(
	 *                 required={"upload_file"},
	 *                 @OA\Property(property="upload_file", type="string", format="binary", description="CSV file (.csv) max 5MB")
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function import(Request $request)
	{
		try {
			/* Validate request data */
			$request->validate([
				'upload_file' => 'required|file|mimes:csv,txt|max:5120',
			]);

			$file = $request->file('upload_file');

			$seoFileFormatArray = [
				'Relational Name' => 'relational_name',
				'Relational ID' => 'relational_id',
				'Relational Type' => 'relational_type',
				'URL' => 'url',
				'Primary Keyword' => 'primary_keyword',
				'Primary Monthly Search Volume' => 'primary_monthly_search_volume',
				'Secondary Keyword' => 'secondary_keyword',
				'Secondary Monthly Search Volume' => 'secondary_monthly_search_volume',
				'Title Tag' => 'title_tag',
				'Meta Title' => 'meta_title',
				'Meta Description' => 'meta_description',
				'Internal Links(Separated By |)' => 'internal_links',
				'Indexing' => 'indexing',
				'Og Title' => 'og_title',
				'Og Description' => 'og_description',
				'Og Image URL' => 'og_image_url',
				'Og Image Alt Text' => 'og_image_alt_text',
				'Og Image Name' => 'og_image_name',
				'Tags(Separated By |)' => 'tags',
			];

			$requiredRowCount = count($seoFileFormatArray);

			$data = [];
			/* Open the CSV file and read its content */
			$rowIndex = 1;
			if (($handle = fopen($file, "r")) !== false) {
				while (($row = fgetcsv($handle, 0, ",", '"', "\\")) !== false) {
					/* Fix unquoted fields and escape special characters */
					$row = array_map(function ($value) {
						/* Add quotes around multiline fields */
						if (strpos($value, "\n") !== false || strpos($value, "\r") !== false) {
							$value = '"' . str_replace('"', '""', $value) . '"';
						}

						/* Check if the value is UTF-8 encoded */
						if (!mb_check_encoding($value, 'UTF-8')) {
							/* Attempt to convert to UTF-8, fallback to ISO-8859-1 if detection fails */
							$value = @mb_convert_encoding($value, 'UTF-8', 'auto') ?: utf8_encode($value);
						}

						/* Remove invalid characters and trim spaces */
						$value = preg_replace('/[^\x20-\x7E\xA0-\xFF]/u', '', $value);
						return trim($value);
					}, $row);

					/* Skip blank rows */
					if (array_filter($row)) {
						if (count($row) != $requiredRowCount) {
							$message = "The data in row $rowIndex is not compatible for import.";

							session()->put('error', $message);
							return back();
						}
						$data[] = $row;
					}
					$rowIndex++;
				}
				fclose($handle);
			}

			/* Remove the header row */
			$header = array_shift($data);

			$requiredHeaderArray = array_keys($seoFileFormatArray);

			if ($missingColumns = array_diff($requiredHeaderArray, $header)) {
				$columns = implode(', ', array_values($missingColumns));
				$missingCount = count($missingColumns);
				return response()->json([
					'success' => true,
					'message' => $missingCount > 1 ? "The uploaded file has an incorrect header. $columns columns are missing." : "The uploaded file has an incorrect header. $columns column is missing."
				]);
			}

			/* Get the total record count */
			$totalRecords = count($data);
			if ($totalRecords == 0) {
				return response()->json([
					'success' => true,
					'message' => "The uploaded CSV file does not contain any records. Please ensure the file has valid data and try again."
				]);
			}

			/* Chunk the data into manageable portions (e.g., 100 rows per chunk) */
			$chunkSize = 100;
			$chunks = array_chunk($data, $chunkSize);

			/* Start import process */
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
				$log->module = "Product";
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
			->name("Product Import")
			->dispatch();

			/* Add jobs to the batch for processing chunks */
			foreach ($chunks as $chunk) {
				$data = [
					'seoFileFormatArray' => $seoFileFormatArray,
					'header' => $header,
					'chunk' => $chunk,
					'userId' => auth()->id()
				];
				$batch->add(new ImportSeoDetailJob($data));
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

	/**
	 * @OA\Post(
	 *     path="/api/seo-management/export",
	 *     summary="Export SEO data to CSV",
	 *     tags={"SEO Management"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"relational_type", "range_from", "range_to"},
	 *             @OA\Property(property="relational_type", type="string", enum={"Product", "Category", "Brand", "Blog"}, example="Product", description="Type of relational entity"),
	 *             @OA\Property(property="relational_id", type="integer", nullable=true, example=5, description="ID of the related entity (optional)"),
	 *             @OA\Property(property="range_from", type="integer", minimum=1, example=1, description="Starting range (must be >= 1)"),
	 *             @OA\Property(property="range_to", type="integer", example=50, description="Ending range (must be >= range_from and at most 2000 more)")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Success",
	 *         @OA\MediaType(mediaType="application/json")
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function export(Request $request)
	{
		/* Validate request data */
		$request->validate([
			'relational_type' => 'required|in:Product,Category,Brand,Blog',
			'relational_id' => 'nullable|integer|exists:ec_product_categories,id',
			'range_from' => 'required|integer|min:1',
			'range_to' => 'required|integer|gte:range_from|max:' . ($request->range_from + 2000),
		]);

		if ($request->relational_type == 'Category') {
			$parentCategory = Category::findOrFail($request->parent_category_id);

			/* Get leaf categories */
			$leafCategories = Category::getLeafCategories($parentCategory);
		}

		/* Fetch unique attributes */
		$uniqueAttributes = $leafCategories
		->flatMap->categoryAllAttributes()
		->unique('id')
		->sortBy('id')
		->reject(fn($attribute) => $attribute->type === 'multiselect') // Exclude multiselect
		->mapWithKeys(fn($attribute) => [
			$attribute->id => [
				'name' => $attribute->name,
				'type' => $attribute->type,
				'attribute_value' => $attribute->type === 'toggle'
				? ['Yes', 'No']
				: $attribute->attributeValues->pluck('attribute_value')->toArray(),
			]
		])
		->toArray();

		if (empty($uniqueAttributes)) {
			return response()->json([
				'success' => false,
				'message' => 'No attributes exist in the associated leaf categories.'
			]);
		}

		/* Prepare headers */
		$attributeNames = array_column($uniqueAttributes, 'name');
		$header = array_merge(['ID', 'SKU', 'Name'], $attributeNames);

		/* Initialize spreadsheet */
		$spreadsheet = $this->excel->newSpreadsheet();
		$spreadsheet->setActiveSheetIndex(0);
		$sheet = $spreadsheet->getActiveSheet();

		/* Set headers */
		$this->excel->setHeader($sheet, $header);

		/* Fetch products within range */
		$products = Product::whereHas('categories', fn($query) => $query->whereIn('category_id', $leafCategoryIds))
		->offset($request->range_from - 1)
		->limit($request->range_to - $request->range_from + 1)
		->orderBy('id', 'asc')
		->get(['id', 'sku', 'name']);

		if ($products->isEmpty()) {
			return response()->json([
				'success' => false,
				'message' => 'No products exist in the associated leaf categories.'
			]);
		}

		/* Populate data */
		$row = 2;
		foreach ($products as $product) {
			$existingAttributes = $product->productAttributes->pluck('attribute_value', 'attribute_id')->toArray();
			$col = 'A';

			/* Set product details */
			$sheet->setCellValue($col++ . $row, $product->id);
			$sheet->setCellValue($col++ . $row, $product->sku);
			$sheet->setCellValue($col++ . $row, $product->name);

			foreach ($uniqueAttributes as $attributeId => $attributeDetail) {
				$existingVal = $existingAttributes[$attributeId] ?? '';
				$cell = $col++ . $row;

				if (!empty($attributeDetail['attribute_value']) && in_array($attributeDetail['type'], ['select', 'toggle'])) {
					$this->excel->setDropdown($spreadsheet, $sheet, $cell, $attributeDetail['name'], $attributeDetail['attribute_value'], $existingVal);
				} else {
					$sheet->setCellValue($cell, $existingVal);
				}
			}
			$row++;
		}

		/* Generate response */
		$response = new StreamedResponse(function () use ($spreadsheet) {
			$writer = new Xlsx($spreadsheet);
			$writer->save('php://output');
		});

		$fileName = strtolower(str_replace(' ', '_', trim("{$parentCategory->name}_products_{$request->range_from}-{$request->range_to}.xlsx")));

		$response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		$response->headers->set('Content-Disposition', $response->headers->makeDisposition(
			ResponseHeaderBag::DISPOSITION_ATTACHMENT, $fileName
		));

		return $response;
	}
}
