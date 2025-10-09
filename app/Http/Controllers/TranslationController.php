<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;

use App\Models\Language;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\ProductAttribute;
use App\Models\Product;

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
	 *             @OA\Property(property="type", type="string", enum={"attribute", "attribute_value", "product_attribute", "product"}, example="attribute"),
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
		$maxRange = ($request->type === 'product') ? $request->range_from + 1000 : $request->range_from + 2000;

		$request->validate([
			'type' => 'required|string|in:attribute,attribute_value,product_attribute,product',
			'range_from' => 'required|integer|min:1',
			'range_to' => "required|integer|gte:range_from|max:$maxRange",
		]);

		/* Get available language codes */
		$langCodeArray = Language::pluck('code')->toArray();

		/* Prepare header row */
		$localizedTitleHeaders = [];
		if ($request->type === 'product') {
			$localizedTitleHeaders = collect($langCodeArray)->flatMap(function ($code) {
				$prefix = strtoupper($code);
				return [
					"{$prefix}_Name",
					"{$prefix}_Description",
					"{$prefix}_BenefitsFeatures",
					"{$prefix}_Images",
				];
			})->toArray();
		} else {
			$localizedTitleHeaders = array_map(function ($code) {
				return strtoupper($code) . '_Title';
			}, $langCodeArray);
		}

		if ($request->type === 'product') {
			$baseArray = ['ID', 'Name', 'Description', 'Benefits Features', 'Images'];
		} else {
			$baseArray = ['ID', 'Name'];
		}

		$excelHeaders = array_merge($baseArray, $localizedTitleHeaders);

		$model = match ($request->type) {
			'attribute' => Attribute::class,
			'attribute_value' => AttributeValue::class,
			'product_attribute' => ProductAttribute::class,
			'product' => Product::class,
		};

		/* Fetch and format records */
		$records = $model::with(['translations' => function ($query) use ($langCodeArray) {
			$query->whereIn('locale', $langCodeArray);
		}])
		->offset($request->range_from - 1)
		->limit($request->range_to - $request->range_from + 1)
		->orderBy('id', 'asc')
		->get();

		$records = $records->map(function ($table) use ($langCodeArray, $request) {
			$translations = $table->translations->keyBy('locale');

			if ($request->type === 'attribute') {
				$row = [
					$table->id,
					$table->name,
				];
			} elseif (in_array($request->type, ['attribute_value', 'product_attribute'])) {
				$row = [
					$table->id,
					$table->attribute_value,
				];
			} elseif ($request->type === 'product') {
				$row = [
					$table->id,
					$table->name,
					$table->description,
					$table->benefits_features,
					$table->images,
				];
			}

			foreach ($langCodeArray as $code) {
				$row[] = optional($translations->get($code))->title ?? '';
			}

			return $row;
		});

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
	 *                 @OA\Property(property="type", type="string", enum={"attribute", "attribute_value", "product_attribute", "product"}, example="attribute"),
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
			'type' => 'required|string|in:attribute,attribute_value,product_attribute,product',
			'upload_file' => 'required|file|mimes:xlsx,xls|max:2048',
		]);

		try {
			$langCodeArray = Language::pluck('code')->toArray();

			if ($request->type === 'product') {
				$keywordFileFormatArray = [
					'ID'   => 'id',
					'Name' => 'name',
					'Description' => 'description',
					'Benefits Features' => 'benefits_features',
					'Images' => 'images',
				];
			} else {
				$keywordFileFormatArray = [
					'ID'   => 'id',
					'Name' => 'name',
				];
			}

			foreach ($langCodeArray as $code) {
				$upperCode = strtoupper($code);
				if ($request->type === 'product') {
					$keywordFileFormatArray["{$upperCode}_Name"]             = "{$code}_name";
					$keywordFileFormatArray["{$upperCode}_Description"]      = "{$code}_description";
					$keywordFileFormatArray["{$upperCode}_BenefitsFeatures"] = "{$code}_benefits_features";
					$keywordFileFormatArray["{$upperCode}_Images"]           = "{$code}_images";
				} else {
					$keywordFileFormatArray["{$upperCode}_Title"] = "{$code}_title";
				}
			}

			$module = match ($request->type) {
				'attribute' => 'Attribute Translation',
				'attribute_value' => 'Attribute Value Translation',
				'product_attribute' => 'Product Attribute Translation',
				'product' => 'Product Translation',
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

	/**
	 * @OA\Post(
	 *     path="/api/translations/generate-translate",
	 *     summary="Generate translation from ID",
	 *     tags={"Translations"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             @OA\Property(property="translate_to", type="string", example="ar"),
	 *             @OA\Property(property="product_id", type="integer", example=2001)
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function generateTranslate(Request $request)
	{
		$request->validate([
			'translate_to' => 'required|string|in:ar,en',
			'product_id' => 'required|integer|exists:ec_products,id',
		]);

		return response()->json([
			'success' => true,
			'message' => 'Record translated successfully.',
			'data' => $record,
		]);
	}
}
