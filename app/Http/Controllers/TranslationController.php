<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;

use App\Models\Language;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\ProductAttribute;

use App\Repository\ExcelRepository;

use App\Jobs\ImportTranslationJob;
use App\Services\ExcelImporterService;

class TranslationController extends BaseController
{
	/**
	 * @OA\Post(
	 *     path="/api/translations/export",
	 *     summary="Export translation data to Excel",
	 *     tags={"Translations"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             @OA\Property(property="type", type="string", enum={"attribute", "attribute_value", "product_attribute"}, example="attribute"),
	 *             @OA\Property(property="range_from", type="integer", example=1, description="Starting range (must be >=1)"),
	 *             @OA\Property(property="range_to", type="integer", example=50, description="Ending range (must be >= range_from and max 5000 more)")
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function export(Request $request, ExcelRepository $excelRepo)
	{
		/* Validate the request data */
		$request->validate([
			'type' => 'required|string|in:attribute,attribute_value,product_attribute',
			'range_from' => 'required|integer|min:1',
			'range_to' => 'required|integer|gte:range_from|max:' . ($request->range_from + 5000),
		]);

		/* Get available language codes */
		$langCodeArray = Language::pluck('code')->toArray();

		/* Prepare header row */
		$localizedTitleHeaders = array_map(function ($code) {
			return strtoupper($code) . '_Title';
		}, $langCodeArray);

		$excelHeaders = array_merge(['ID', 'Name'], $localizedTitleHeaders);

		$model = match ($request->type) {
			'attribute' => Attribute::class,
			'attribute_value' => AttributeValue::class,
			'product_attribute' => ProductAttribute::class,
		};

		/* Fetch and format records */
		$records = $model::with(['translations' => function ($query) use ($langCodeArray) {
			$query->whereIn('locale', $langCodeArray);
		}])
		->offset($request->range_from - 1)
		->limit($request->range_to - $request->range_from + 1)
		->orderBy('id', 'asc')
		->get();
		if ($request->type == 'attribute') {
			$records = $records
			->map(function ($table) use ($langCodeArray) {
				$translations = $table->translations->keyBy('locale');
				$row = [
					$table->id,
					$table->name,
				];

				foreach ($langCodeArray as $code) {
					$row[] = $translations[$code]->title ?? '';
				}
				return $row;
			});
		} elseif (in_array($request->type, ['attribute_value', 'product_attribute'])) {
			$records = $records
			->map(function ($table) use ($langCodeArray) {
				$translations = $table->translations->keyBy('locale');
				$row = [
					$table->id,
					$table->attribute_value,
				];

				foreach ($langCodeArray as $code) {
					$row[] = $translations[$code]->title ?? '';
				}
				return $row;
			});
		}

		/* Prepare spreadsheet */
		$spreadsheet = $excelRepo->newSpreadsheet();
		$sheet = $spreadsheet->getActiveSheet();

		$sheetTitle = "{$request->type}_{$request->range_from}_{$request->range_to}.xlsx";
		$sheet->setTitle($sheetTitle);

		/* Set headers */
		$excelRepo->setHeader($sheet, $excelHeaders);

		/* Fill data rows */
		$rowIndex = 2;
		foreach ($records as $recordRow) {
			$excelRepo->writeRow($sheet, $recordRow, $rowIndex++);
		}

		$fileName = "{$request->type}_{$request->range_from}_{$request->range_to}_" . now()->format('Y-m-d_H-i-s') . ".xlsx";

		return $excelRepo->downloadFile($fileName, $spreadsheet);
	}

	/**
	 * @OA\Post(
	 *     path="/api/translations/import",
	 *     summary="Import translation from an excel file",
	 *     tags={"Translations"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="multipart/form-data",
	 *             @OA\Schema(
	 *                 required={"upload_file"},
	 *                 @OA\Property(property="type", type="string", enum={"attribute", "attribute_value", "product_attribute"}, example="attribute"),
	 *                 @OA\Property(property="upload_file", type="string", format="binary", description="xlsx file (.xlsx) max 2MB")
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
			'type' => 'required|string|in:attribute,attribute_value,product_attribute',
			'upload_file' => 'required|file|mimes:xlsx,xls|max:2048',
		]);

		try {
			$langCodeArray = Language::pluck('code')->toArray();

			$keywordFileFormatArray = [
				'ID'   => 'id',
				'Name' => 'name',
			];

			/* Append language-specific title mappings */
			foreach ($langCodeArray as $code) {
				$upperCode = strtoupper($code);
				$keywordFileFormatArray["{$upperCode}_Title"] = "{$code}_title";
			}

			$module = match ($request->type) {
				'attribute' => 'Attribute Translation',
				'attribute_value' => 'Attribute Value Translation',
				'product_attribute' => 'Product Attribute Translation',
			};

			$excelImporter->processExcelImport(
				$request->file('upload_file'),
				$keywordFileFormatArray,
				$module, /* Module name */
				config('app.website') . '_TRANS', /* Job name */
				'Import Keywords', /* Batch name */
				ImportTranslationJob::class
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
	 * Display a listing of the resource.
	 */
	public function index()
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
	public function show(AppKeyword $appKeyword)
	{
		//
	}

	/**
	 * Update the specified resource in storage.
	 */
	public function update(Request $request, AppKeyword $appKeyword)
	{
		//
	}

	/**
	 * Remove the specified resource from storage.
	 */
	public function destroy(AppKeyword $appKeyword)
	{
		//
	}
}
