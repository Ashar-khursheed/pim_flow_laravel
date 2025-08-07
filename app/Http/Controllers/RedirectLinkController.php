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
	 *     summary="Get list of all redirect links with search, sort, and pagination",
	 *     tags={"Redirect Links"},
	 *     security={{"bearerAuth":{}}},
	 *     @OA\Parameter(
	 *         name="search",
	 *         in="query",
	 *         description="Search by 'from' or 'to' URL",
	 *         required=false,
	 *         @OA\Schema(type="string", example="category1")
	 *     ),
	 *     @OA\Parameter(
	 *         name="sort_by",
	 *         in="query",
	 *         description="Column to sort by (id, from, to)",
	 *         required=false,
	 *         @OA\Schema(type="string", default="id")
	 *     ),
	 *     @OA\Parameter(
	 *         name="sort_order",
	 *         in="query",
	 *         description="Sort order (asc or desc)",
	 *         required=false,
	 *         @OA\Schema(type="string", default="desc")
	 *     ),
	 *     @OA\Parameter(
	 *         name="per_page",
	 *         in="query",
	 *         description="Number of items per page",
	 *         required=false,
	 *         @OA\Schema(type="integer", default=10)
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
	 *         description="Paginated list of redirect links",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="current_page", type="integer", example=1),
	 *             @OA\Property(property="data", type="array", @OA\Items(
	 *                 @OA\Property(property="id", type="integer", example=1),
	 *                 @OA\Property(property="from", type="string", example="/category1"),
	 *                 @OA\Property(property="to", type="string", example="/category4324/22")
	 *             )),
	 *             @OA\Property(property="first_page_url", type="string", example="http://yourdomain.com/api/redirect-links?page=1"),
	 *             @OA\Property(property="last_page", type="integer", example=5),
	 *             @OA\Property(property="last_page_url", type="string", example="http://yourdomain.com/api/redirect-links?page=5"),
	 *             @OA\Property(property="next_page_url", type="string", example="http://yourdomain.com/api/redirect-links?page=2"),
	 *             @OA\Property(property="path", type="string", example="http://yourdomain.com/api/redirect-links"),
	 *             @OA\Property(property="per_page", type="integer", example=10),
	 *             @OA\Property(property="prev_page_url", type="string", nullable=true, example=null),
	 *             @OA\Property(property="total", type="integer", example=50)
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=401,
	 *         description="Unauthenticated"
	 *     )
	 * )
	 */

	// public function index()
	// {
	// 	return response()->json(RedirectLink::select('id', 'from', 'to')->get());
	// }
	public function index(Request $request)
	{
		$query = RedirectLink::query()->select('id', 'from', 'to');

		// Search (by 'from' or 'to')
		if ($request->has('search') && !empty($request->search)) {
			$search = $request->search;
			$query->where(function ($q) use ($search) {
				$q->where('from', 'like', "%{$search}%")
				->orWhere('to', 'like', "%{$search}%");
			});
		}

		// Sort
		$sortBy = $request->get('sort_by', 'id');
		$sortOrder = $request->get('sort_order', 'desc');
		$query->orderBy($sortBy, $sortOrder);

		// Pagination
		$perPage = $request->get('per_page', 10);
		$redirectLinks = $query->paginate($perPage);

		return response()->json($redirectLinks);
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
     * @OA\Get(
     *     path="/api/redirect-links/{id}",
     *     summary="Get a specific redirect link",
     *     tags={"Redirect Links"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Redirect link found"),
     *     @OA\Response(response=404, description="Redirect link not found")
     * )
     */
    public function show($id)
    {
        $redirect = RedirectLink::find($id);

        if (!$redirect) {
            return response()->json([
                'message' => 'Redirect link not found'
            ], 404);
        }

        return response()->json([
            'data' => $redirect
        ]);
    }
    /**
     * @OA\Put(
     *     path="/api/redirect-links/{id}",
     *     summary="Update a redirect link",
     *     tags={"Redirect Links"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(property="from", type="string", example="/category1"),
     *                 @OA\Property(property="to", type="string", example="/category4324/22")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Redirect link updated successfully"),
     *     @OA\Response(response=404, description="Redirect link not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, $id)
    {
        $redirect = RedirectLink::find($id);

        if (!$redirect) {
            return response()->json([
                'message' => 'Redirect link not found'
            ], 404);
        }

        $request->validate([
            'from' => 'required|string|unique:redirect_links,from,' . $redirect->id,
            'to'   => 'required|string',
        ]);

        $redirect->update($request->only('from', 'to'));

        return response()->json([
            'message' => 'Redirect link updated successfully',
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

		$fileName = 'redirect_links_import_template_' . now()->format('Y-m-d_H-i-s') . '.xlsx';

		return $excelRepo->downloadFile($fileName, $spreadsheet);
	}

// 	public function getByFrom($from)
// {
//     $from = '/' . ltrim($from, '/'); // Ensures one leading slash

//     $redirect = RedirectLink::where('from', $from)->first();

//     if ($redirect) {
//         return response()->json(['to' => $redirect->to]);
//     }

//     return response()->json(['message' => 'Not found'], 404);
// }
public function getByFrom($from)
{
    $from = '/' . ltrim($from, '/'); // Ensure one leading slash

    $redirect = RedirectLink::where('from', $from)->first();

    if ($redirect) {
        return response()->json([
            'success' => true,
            'to' => $redirect->to
        ]);
    }

    return response()->json([
        'success' => false,
        'message' => 'Not found'
    ], 404);
}


}