<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

use App\Models\Language;
use App\Models\AttributeGroup;
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
	 *             @OA\Property(property="type", type="string", enum={"attribute_group", "attribute", "attribute_value", "product_attribute", "product"}, example="attribute"),
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
		if (!in_array(config('app.website'), ['UAE', 'UAE_T', 'SA'])) {
			return response()->json([
				'success' => false,
				'message' => "This feature is restricted and only available for UAE and SA websites.",
			]);
		}

		/* Validate the request data */
		$maxRange = ($request->type === 'product') ? $request->range_from + 1000 : $request->range_from + 2000;

		$request->validate([
			// 'type' => 'required|string|in:attribute_group,attribute,attribute_value,product_attribute,product',
			'type' => 'required|string|in:attribute,attribute_value,product_attribute',
			'range_from' => 'required|integer|min:1',
			'range_to' => "required|integer|gte:range_from|max:$maxRange",
		]);

		/* Get available language codes */
		$langCodeArray = Language::pluck('code')->toArray();

		/* Prepare header row */
		$localizedTitleHeaders = [];
		if (in_array($request->type, ['attribute_group', 'attribute', 'attribute_value', 'product_attribute'])) {
			$localizedTitleHeaders = collect($langCodeArray)->flatMap(function ($code) {
				$prefix = strtoupper($code);
				return ["{$prefix}_Title"];
			})->toArray();
		} elseif ($request->type === 'product') {
			$localizedTitleHeaders = collect($langCodeArray)->flatMap(function ($code) {
				$prefix = strtoupper($code);
				return [
					"{$prefix}_Name",
					"{$prefix}_Description",
					"{$prefix}_BenefitsFeatures",
					"{$prefix}_Images",
				];
			})->toArray();
		}

		// Define base headers based on type
		if ($request->type === 'product') {
			$baseArray = ['ID', 'Name', 'Description', 'Benefits Features', 'Images'];
		} elseif ($request->type === 'attribute_value') {
			$baseArray = ['ID', 'Attribute Name', 'Value'];
		} else {
			$baseArray = ['ID', 'Name'];
		}

		$excelHeaders = array_merge($baseArray, $localizedTitleHeaders);

		// Get the model class
		$model = match ($request->type) {
			'attribute_group' => AttributeGroup::class,
			'attribute' => Attribute::class,
			'attribute_value' => AttributeValue::class,
			'product_attribute' => ProductAttribute::class,
			'product' => Product::class,
		};

		/* Fetch and format records */
		if ($request->type === 'attribute_value') {
			// For attribute_value, get attribute IDs in the range first
			$attributeIds = Attribute::orderBy('id', 'asc')
			->offset($request->range_from - 1)
			->limit($request->range_to - $request->range_from + 1)
			->whereHas('attributeValues')
			->pluck('id')
			->toArray();

			// Then get all attribute values for those attributes
			$records = AttributeValue::with([
				'attribute:id,name',
				'translations' => function ($query) use ($langCodeArray) {
					$query->whereIn('locale', $langCodeArray);
				}
			])
			->whereIn('attribute_id', $attributeIds)
			->orderBy('attribute_id', 'asc')
			->orderBy('id', 'asc')
			->get();
		} else {
			$query = $model::with(['translations' => function ($query) use ($langCodeArray) {
				$query->whereIn('locale', $langCodeArray);
			}]);

			$records = $query->offset($request->range_from - 1)
			->limit($request->range_to - $request->range_from + 1)
			->orderBy('id', 'asc')
			->get();
		}

		// Map records to Excel rows
		$records = $records->map(function ($table) use ($langCodeArray, $request) {
			$translations = $table->translations->keyBy('locale');

			/* Define field mapping based on type */
			$fieldMapping = [
				'attribute_group' => ['id', 'name'],
				'attribute' => ['id', 'name'],
				'attribute_value' => ['id', 'attribute_name', 'attribute_value'],
				'product_attribute' => ['id', 'attribute_value'],
				'product' => ['id', 'name', 'description', 'benefits_features', 'images'],
			];

			/* Define translation field mapping */
			$translationFields = [
				'attribute_group' => ['name_tr'],
				'attribute' => ['name_tr'],
				'attribute_value' => ['attribute_value_tr'],
				'product_attribute' => ['attribute_value_tr'],
				'product' => ['name_tr', 'description_tr', 'benefits_features_tr', 'images_tr'],
			];

			/* Build base row */
			$row = [];
			$fields = $fieldMapping[$request->type] ?? [];

			foreach ($fields as $field) {
				if ($field === 'attribute_name' && $request->type === 'attribute_value') {
					// Get attribute name from relationship
					$row[] = $table->attribute->name ?? '';
				} else {
					$row[] = $table->$field ?? '';
				}
			}

			/* Add translations for each language */
			foreach ($langCodeArray as $code) {
				$translation = $translations->get($code);
				$transFields = $translationFields[$request->type] ?? [];

				foreach ($transFields as $transField) {
					$row[] = $translation?->$transField ?? '';
				}
			}

			return $row;
		});

		/* Prepare spreadsheet */
		$spreadsheet = $excelRepo->newSpreadsheet();
		$sheet = $spreadsheet->getActiveSheet();

		$sheetTitle = substr("{$request->type}_{$request->range_from}_{$request->range_to}", 0, 31);
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
	 *                 @OA\Property(property="type", type="string", enum={"attribute_group", "attribute", "attribute_value", "product_attribute", "product"}, example="attribute"),
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
		if (!in_array(config('app.website'), ['UAE', 'UAE_T', 'SA'])) {
			return response()->json([
				'success' => false,
				'message' => "This feature is restricted and only available for UAE and SA websites.",
			]);
		}
		/* Validate request data */
		$request->validate([
			'type' => 'required|string|in:attribute_group,attribute,attribute_value,product_attribute,product,faq',
			'upload_file' => 'required|file|mimes:xlsx,xls|max:2048',
		]);

		try {
			$langCodeArray = Language::pluck('code')->toArray();

			/* Define base columns and translation fields based on type */
			$typeConfig = [
				'attribute_group' => [
					'base' => ['ID' => 'id', 'Name' => 'name'],
					'trans' => ['{CODE}_Title' => '{code}_name']
				],
				'attribute' => [
					'base' => ['ID' => 'id', 'Name' => 'name'],
					'trans' => ['{CODE}_Title' => '{code}_name']
				],
				'attribute_value' => [
					'base' => ['ID' => 'id', 'Attribute Name' => 'attribute_name', 'Value' => 'attribute_value'],
					'trans' => ['{CODE}_Title' => '{code}_attribute_value']
				],
				'product_attribute' => [
					'base' => ['ID' => 'id', 'Attribute Value' => 'attribute_value'],
					'trans' => ['{CODE}_Title' => '{code}_attribute_value']
				],
				'product' => [
					'base' => [
						'ID' => 'id',
						'Name' => 'name',
						'Description' => 'description',
						'Benefits Features' => 'benefits_features',
						'Images' => 'images',
					],
					'trans' => [
						'{CODE}_Name' => '{code}_name',
						'{CODE}_Description' => '{code}_description',
						'{CODE}_BenefitsFeatures' => '{code}_benefits_features',
						'{CODE}_Images' => '{code}_images',
					]
				],
				'faq' => [
					'base' => [
						'ID' => 'id',
						'Question' => 'question',
						'Answer' => 'answer',
					],
					'trans' => [
						'{CODE}_Question' => '{code}_question',
						'{CODE}_Answer' => '{code}_answer',
					]
				],
			];

			$config = $typeConfig[$request->type] ?? ['base' => [], 'trans' => []];

			/* Build keyword file format array */
			$keywordFileFormatArray = $config['base'];

			/* Add translation columns for each language */
			foreach ($langCodeArray as $code) {
				$upperCode = strtoupper($code);

				foreach ($config['trans'] as $keyTemplate => $valueTemplate) {
					$key = str_replace('{CODE}', $upperCode, $keyTemplate);
					$value = str_replace('{code}', $code, $valueTemplate);
					$keywordFileFormatArray[$key] = $value;
				}
			}

			/* Define module name */
			$module = match ($request->type) {
				'attribute_group' => 'Attribute Group Translation',
				'attribute' => 'Attribute Translation',
				'attribute_value' => 'Attribute Value Translation',
				'product_attribute' => 'Product Attribute Translation',
				'product' => 'Product Translation',
				'faq' => 'FAQ Translation',
				default => 'Translation'
			};

			$excelImporter->processExcelImport(
				$request->file('upload_file'),
				$keywordFileFormatArray,
				$module, /* Module name */
				config('app.website') . '_TRANS', /* Job name */
				'Import Translation', /* Batch name */
				ImportTranslationJob::class
			);

			return response()->json([
				'success' => true,
				'message' => 'The import process has been scheduled successfully. Please track it under import log.'
			]);

		} catch(\Exception $exception) {
			\Log::error('Translation Import Error', [
				'message' => $exception->getMessage(),
				'file' => $exception->getFile(),
				'line' => $exception->getLine(),
				'trace' => $exception->getTraceAsString()
			]);

			return response()->json([
				'success' => false,
				'message' => 'Import failed: ' . $exception->getMessage(),
				'error' => [
					'message' => $exception->getMessage(),
					'file' => $exception->getFile(),
					'line' => $exception->getLine(),
				]
			], 500);
		}
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
		if (!in_array(config('app.website'), ['UAE', 'UAE_T', 'SA'])) {
			return response()->json([
				'success' => false,
				'message' => "This feature is restricted and only available for UAE and SA websites.",
			]);
		}

		$request->validate([
			'translate_to' => 'required|string|in:ar,en',
			'product_id' => 'required|integer|exists:ec_products,id',
		]);

		$isPublished = Product::where('id', $request->product_id)->where('status', 'published')->exists();

		if (!$isPublished) {
			return response()->json([
				'success' => false,
				'message' => 'Only published products can be translated.',
			]);
		}

		if ($request->translate_to == 'ar') {
			$scriptPath = base_path('app/Script/ar_translation.py');
			if (!file_exists($scriptPath)) {
				return response()->json([
					'success' => false,
					'error' => 'Python script not found',
					'details' => $scriptPath
				], 500);
			}

			$productId = $request->product_id;
			$pythonCmd = env('PYTHON_PATH', base_path('venv/bin/python'));

			$process = new Process([$pythonCmd, $scriptPath, $productId]);
			$process->setTimeout(600);
			$process->setEnv([
				'PYTHONIOENCODING' => 'utf-8'
			]);
			$process->run();

			if (!$process->isSuccessful()) {
				throw new ProcessFailedException($process);
			}

			$output = $process->getOutput();

			$record = json_decode($output, true);

			if (json_last_error() !== JSON_ERROR_NONE) {
				dd([
					'error' => 'JSON decode failed',
					'json_error' => json_last_error_msg(),
					'cleaned_output' => $output,
					'first_100_chars' => substr($output, 0, 100)
				]);
			}

			return response()->json([
				'success' => true,
				'message' => 'Record translated successfully.',
				'data' => $record,
			]);
		}
	}
}
