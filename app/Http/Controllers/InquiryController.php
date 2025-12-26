<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\FrontEnd\Inquiry;

class InquiryController extends Controller
{
	/**
 * @OA\Get(
 *     path="/api/inquiries",
 *     summary="Get inquiry IDs with optional date range filter",
 *     tags={"Inquiries"},
 *     @OA\Parameter(
 *         name="from_date",
 *         in="query",
 *         description="Start date in YYYY-MM-DD format",
 *         required=false,
 *         @OA\Schema(type="string", format="date")
 *     ),
 *     @OA\Parameter(
 *         name="to_date",
 *         in="query",
 *         description="End date in YYYY-MM-DD format",
 *         required=false,
 *         @OA\Schema(type="string", format="date")
 *     ),
 *     @OA\Parameter(
 *         name="global",
 *         in="query",
 *         description="Global search across multiple fields",
 *         required=false,
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="sort_by",
 *         in="query",
 *         description="Column to sort by",
 *         required=false,
 *         @OA\Schema(
 *             type="string",
 *             enum={"id","full_name","phone","email","company_name","lead_type","lead_source","created_at","updated_at"}
 *         )
 *     ),
 *     @OA\Parameter(
 *         name="sort_dir",
 *         in="query",
 *         description="Sort direction",
 *         required=false,
 *         @OA\Schema(type="string", enum={"asc","desc"}, default="desc")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="IDs retrieved successfully",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="success", type="boolean"),
 *             @OA\Property(property="message", type="string"),
 *             @OA\Property(
 *                 property="data",
 *                 type="array",
 *                 @OA\Items(type="integer", example=1)
 *             ),
 *             @OA\Property(property="total_records", type="integer", example=100)
 *         )
 *     ),
 *     security={{"bearerAuth":{}}}
 * )
 */

	// public function index(Request $request)
	// {
	// 	$searchableColumns = ['id', 'full_name', 'phone', 'email', 'company_name', 'lead_type', 'lead_source'];
	// 	$sortableColumns = array_merge($searchableColumns, ['created_at', 'updated_at']);

	// 	$sortBy = in_array($request->input('sort_by'), $sortableColumns) ? $request->input('sort_by') : 'id';
	// 	$sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

	// 	$recordsQuery = Inquiry::query();

	// 	if ($request->filled('from_date') && $request->filled('to_date')) {
	// 		$from = $request->from_date . ' 00:00:00';
	// 		$to = $request->to_date . ' 23:59:59';
	// 		$recordsQuery = $recordsQuery->whereBetween('created_at', [$from, $to]);
	// 	}

	// 	if ($request->filled('global')) {
	// 		$search = $request->input('global');
	// 		$recordsQuery->where(function ($q) use ($searchableColumns, $search) {
	// 			foreach ($searchableColumns as $col) {
	// 				$q->orWhere($col, 'like', '%' . $search . '%');
	// 			}
	// 		});
	// 	}

	// 	$recordsQuery->orderBy($sortBy, $sortDir);

	// 	if ($request->filled('page') && $request->filled('length')) {
	// 		$length = (int) $request->input('length');
	// 		$page = (int) $request->input('page');

	// 		$totalRecords = (clone $recordsQuery)->count();
	// 		$totalPages = (int) ceil($totalRecords / $length);

	// 		if ($page > $totalPages && $totalPages > 0) {
	// 			$page = 1;
	// 		}

	// 		$records = $recordsQuery
	// 		->offset(($page - 1) * $length)
	// 		->limit($length)
	// 		->get();
	// 	} else {
	// 		$records = $recordsQuery->orderBy('id', 'desc')->pluck('id');
	// 		$totalRecords = $records->count();
	// 		$totalPages = 1;
	// 	}

	// 	return response()->json([
	// 		'success' => true,
	// 		'message' => __('msg_rec_list'),
	// 		'data' => $records,
	// 		'total_pages' => $totalPages,
	// 		'total_records' => $totalRecords,
	// 	]);
	// }
	public function index(Request $request)
	{
		$recordsQuery = Inquiry::query();

		// Date range filter
		if ($request->filled('from_date') && $request->filled('to_date')) {
			$from = $request->from_date . ' 00:00:00';
			$to = $request->to_date . ' 23:59:59';
			$recordsQuery->whereBetween('created_at', [$from, $to]);
		}

		// Global search filter (optional)
		if ($request->filled('global')) {
			$search = $request->input('global');
			$searchableColumns = ['id', 'full_name', 'phone', 'email', 'company_name', 'lead_type', 'lead_source'];
			$recordsQuery->where(function ($q) use ($searchableColumns, $search) {
				foreach ($searchableColumns as $col) {
					$q->orWhere($col, 'like', '%' . $search . '%');
				}
			});
		}

		// Sorting
		$sortableColumns = ['id', 'full_name', 'phone', 'email', 'company_name', 'lead_type', 'lead_source', 'created_at', 'updated_at'];
		$sortBy = in_array($request->input('sort_by'), $sortableColumns) ? $request->input('sort_by') : 'id';
		$sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
		$recordsQuery->orderBy($sortBy, $sortDir);

		// Only return IDs
		$records = $recordsQuery->pluck('id');

		return response()->json([
			'success' => true,
			'message' => __('msg_rec_list'),
			'data' => $records,
			'total_records' => $records->count(),
		]);
	}


	/**
	 * @OA\Get(
	 *     path="/api/inquiries/{id}",
	 *     summary="Get inquiry details",
	 *     tags={"Inquiries"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         description="Inquiry ID",
	 *         required=true,
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\Response(response=200, description="Details retrieved successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}},
	 * )
	 */
	public function show($id)
	{
		$record = Inquiry::find($id);

		if (!$record) {
			return response()->json([
				'success' => false,
				'message' => "Record not found."
			]);
		}

		return response()->json([
			'success' => true,
			'data' => $record
		]);
	}
}
