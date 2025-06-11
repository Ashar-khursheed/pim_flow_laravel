<?php

namespace App\Http\Controllers;

use App\Models\AppKeyword;
use App\Models\Language;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Repository\ExcelRepository;

use App\Jobs\ImportKeywordJob;
use App\Services\ExcelImporterService;

class AppKeywordController extends BaseController
{
	/**
	 * @OA\Post(
	 *     path="/api/keywords/export",
	 *     summary="Export app keyword data to Excel",
	 *     tags={"Keywords"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
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
		/* Permission Check */
		if (!auth()->user()->can('export keywords')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}

		/* Validate the request data */
		$request->validate([
			'range_from' => 'required|integer|min:1',
			'range_to' => 'required|integer|gte:range_from|max:' . ($request->range_from + 5000),
		]);

		/* Get available language codes */
		$langCodeArray = Language::pluck('code')->toArray();

		/* Prepare header row */
		$localizedTitleHeaders = array_map(function ($code) {
			return strtoupper($code) . '_Title';
		}, $langCodeArray);

		$excelHeaders = array_merge(['ID', 'Code'], $localizedTitleHeaders);

		/* Fetch and format records */
		$records = AppKeyword::with(['translations' => function ($query) use ($langCodeArray) {
			$query->whereIn('locale', $langCodeArray);
		}])
		->get()
		->map(function ($keyword) use ($langCodeArray) {
			$translations = $keyword->translations->keyBy('locale');
			$row = [
				$keyword->id,
				$keyword->code,
			];

			foreach ($langCodeArray as $code) {
				$row[] = $translations[$code]->title ?? '';
			}
			return $row;
		});

		/* Prepare spreadsheet */
		$spreadsheet = $excelRepo->newSpreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setTitle('Keywords');

		/* Set headers */
		$excelRepo->setHeader($sheet, $excelHeaders);

		/* Fill data rows */
		$rowIndex = 2;
		foreach ($records as $recordRow) {
			$excelRepo->writeRow($sheet, $recordRow, $rowIndex++);
		}

		$fileName = 'app_keywords_' . $request->range_from . '-' . $request->range_to . '_' . now()->format('Y-m-d_H-i-s') . '.xlsx';

		return $excelRepo->downloadFile($fileName, $spreadsheet);
	}


	/**
	 * @OA\Post(
	 *     path="/api/keywords/import",
	 *     summary="Import keywords from an excel file",
	 *     tags={"Keywords"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="multipart/form-data",
	 *             @OA\Schema(
	 *                 required={"upload_file"},
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
		/* Permission Check */
		if (!auth()->user()->can('import keywords')) {
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
			$langCodeArray = Language::pluck('code')->toArray();

			$keywordFileFormatArray = [
				'ID'   => 'id',
				'Code' => 'code',
			];

			/* Append language-specific title mappings */
			foreach ($langCodeArray as $code) {
				$upperCode = strtoupper($code);
				$keywordFileFormatArray["{$upperCode}_Title"] = "{$code}_title";
			}

			$excelImporter->processExcelImport(
				$request->file('upload_file'),
				$keywordFileFormatArray,
				'Keyword', /* Module name */
				'JOB_KEYWORD', /* Job name */
				'Import Keywords', /* Batch name */
				ImportKeywordJob::class
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
