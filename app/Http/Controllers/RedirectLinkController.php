<?php

namespace App\Http\Controllers;

use App\Models\RedirectLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Jobs\ImportRedirectLinkJob;
use App\Services\ExcelImporterService;
use App\Repository\ExcelRepository;

class RedirectLinkController extends Controller
{
	/**
	 * @OA\Get(
	 *     path="/api/redirect-links",
	 *     summary="Get list of all redirect links",
	 *     tags={"Redirect Links"},
	 *     security={{"bearerAuth":{}}},
	 *     @OA\Response(
	 *         response=200,
	 *         description="List of redirect links",
	 *         @OA\JsonContent(
	 *             type="array",
	 *             @OA\Items(
	 *                 @OA\Property(property="from", type="string", example="/category1"),
	 *                 @OA\Property(property="to", type="string", example="/category4324/22")
	 *             )
	 *         )
	 *     )
	 * )
	 */
	public function index()
	{
		return response()->json(RedirectLink::select('from', 'to')->get());
	}

	/**
	 * @OA\Post(
	 *     path="/api/redirect-links",
	 *     summary="Create a redirect link",
	 *     tags={"Redirect Links"},
	 *     security={{"bearerAuth":{}}},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="application/json",
	 *             @OA\Schema(
	 *                 required={"from", "to"},
	 *                 @OA\Property(property="from", type="string", example="/category1"),
	 *                 @OA\Property(property="to", type="string", example="/category4324/22")
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Redirect created successfully"),
	 *     @OA\Response(response=422, description="Validation error")
	 * )
	 */
	public function store(Request $request)
	{
		$request->validate([
			'from' => 'required|string|unique:redirect_links,from',
			'to'   => 'required|string',
		]);

		$redirect = RedirectLink::create($request->only('from', 'to'));

		return response()->json([
			'message' => 'Redirect created successfully',
			'data' => $redirect
		]);
	}

	/**
	 * @OA\Post(
	 *     path="/api/redirect-links/import",
	 *     summary="Import redirect links from an excel file",
	 *     tags={"Redirect Links"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="multipart/form-data",
	 *             @OA\Schema(
	 *                 required={"upload_file"},
	 *                 @OA\Property(property="upload_file", type="string", format="binary", description="xlsx file (.xlsx) max 2MB"),
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
			'upload_file' => 'required|file|mimes:xlsx,xls|max:2048',
		]);

		try {
			$redirectLinkFileFormatArray = [
				'From' => 'from',
				'To' => 'to'
			];

			$excelImporter->processExcelImport(
				$request->file('upload_file'),
				$redirectLinkFileFormatArray,
				'Redirect Link', /* Module name */
				'JOB_REDIRECT_LINK', /* Job name */
				'Import Redirect Links', /* Batch name */
				ImportRedirectLinkJob::class
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
	 *     path="/api/redirect-links/template",
	 *     summary="Download import template for redirect links",
	 *     description="Downloads an Excel template for redirect link imports",
	 *     tags={"Redirect Links"},
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function downloadTemplate(ExcelRepository $excelRepo)
	{
		$redirectLinkFileFormatArray = [
			'From' => 'from',
			'To' => 'to'
		];

		$header = array_keys($redirectLinkFileFormatArray);

		/* Initialize spreadsheet */
		$spreadsheet = $excelRepo->newSpreadsheet();
		$spreadsheet->setActiveSheetIndex(0);
		$sheet = $spreadsheet->getActiveSheet();

		/* Set headers */
		$excelRepo->setHeader($sheet, $header);

		$fileName = 'redirect-links_import_template' . now()->format('Y-m-d_H-i-s') . '.xlsx';

		return $excelRepo->downloadFile($fileName, $spreadsheet);
	}
}