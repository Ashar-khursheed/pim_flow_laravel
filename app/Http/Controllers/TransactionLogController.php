<?php

namespace App\Http\Controllers;

use App\Models\TransactionLog;
use Illuminate\Http\Request;

class TransactionLogController extends BaseController
{
	/**
	 * @OA\Get(
	 *     path="/api/transaction-logs",
	 *     summary="Get Transaction Log List",
	 *     description="Fetches a list of all transaction logs.",
	 *     tags={"Transaction Logs"},
	 *     @OA\Parameter(name="page", in="query", description="Page number for pagination", example=1, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="length", in="query", description="Number of records per page.", example=20, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="global", in="query", description="Global search for All field", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="id", in="query", description="Search by log id", example="1", @OA\Schema(type="integer")),
	 *     @OA\Parameter(name="module", in="query", description="Filter transaction logs by module.", example="Product",  @OA\Schema(type="string")),
	 *     @OA\Parameter(name="action", in="query", description="Filter transaction logs by action.", example="Import",  @OA\Schema(type="string")),
	 *     @OA\Parameter(name="sort_by", in="query", description="Column name to sort by", @OA\Schema(type="string", enum={"id", "name", "code", "type", "created_at", "updated_at"})),
	 *     @OA\Parameter(name="sort_dir", in="query", description="Sort direction (asc or desc)", example="asc", @OA\Schema(type="string", enum={"asc", "desc"})),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */

	public function index(Request $request)
	{
		// if (!auth()->user()->can('list activity log')) {
		// 	return response()->json([
		// 		'success' => false,
		// 		'message' => "You don't have permission to access this module.",
		// 	]);
		// }

		/* Dynamic search filters */
		$searchableColumns = ['id', 'module', 'action', 'identifier'];
		$sortableColumns = array_merge($searchableColumns, ['created_at']);
		$sortBy = in_array($request->input('sort_by'), $sortableColumns) ? $request->input('sort_by') : 'id';
		$sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

		$recordsQuery = TransactionLog::query();

		/* Pagination */
		if ($request->filled('page') && $request->filled('length')) {
			$recordsQuery->with(['creator:id,first_name,last_name']);

			/* Apply global or column-specific filters */
			if ($request->filled('global')) {
				$search = $request->input('global');
				$recordsQuery->where(function ($q) use ($searchableColumns, $search) {
					foreach ($searchableColumns as $col) {
						$q->orWhere($col, 'LIKE', '%' . $search . '%');
					}
				});
			} else {
				if ($request->filled('action')) {
					if ($request->filled('module')) {
						$recordsQuery->where('module', $request->input('module'));
					}
					$recordsQuery->where('action', $request->input('action'));
				} else {
					foreach ($searchableColumns as $col) {
						if ($request->filled($col)) {
							$recordsQuery->where($col, 'LIKE', '%' . $request->input($col) . '%');
						}
					}
				}
			}

			/* Apply sorting */
			$recordsQuery->orderBy($sortBy, $sortDir);

			/* Clone query for counting */
			$totalRecords = (clone $recordsQuery)->count();
			$length = (int) $request->input('length');
			$totalPages = (int) ceil($totalRecords / $length);

			$page = (int) $request->input('page');
			/* If requested page exceeds total pages (after search), fallback to page 1 */
			if ($page > $totalPages && $totalPages > 0) {
				$page = 1;
			}

			$records = $recordsQuery->offset(($page - 1) * $length)->limit($length)->get([
				'id', 'module', 'action', 'identifier', 'status', 'created_at', 'created_by'
			]);

			/* Add country_name and created_by */
			$records->transform(function ($record) {
				$record->created_by = $record->creator->name ?? null;
				unset($record->creator);

				return $record;
			});
		} else {
			$records = $recordsQuery->orderBy('id', 'asc')->get([
				'id', 'module'
			]);
			$totalRecords = $records->count();
			$totalPages = 1;
		}

		return response()->json([
			'success' => true,
			'message' => 'Transaction Log List',
			'data' => $records,
			'total_pages' => $totalPages ?? 1,
			'total_records' => $totalRecords,
		]);
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
	/**
	 * @OA\Get(
	 *     path="/api/transaction-logs/{transaction_log_id}",
	 *     summary="Get transaction log details",
	 *     description="Fetches transaction log details based on the given transaction log ID.",
	 *     tags={"Transaction Logs"},
	 *     @OA\Parameter(
	 *         name="transaction_log_id",
	 *         in="path",
	 *         required=true,
	 *         description="ID of the transaction log",
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function show($id)
	{
		// if (!auth()->user()->can('show activity log')) {
		// 	return response()->json([
		// 		'success' => false,
		// 		'message' => "You don't have permission to access this module.",
		// 	]);
		// }
		$record = TransactionLog::with(['creator:id,first_name,last_name'])->find($id);

		/* Check if record exists */
		if (!$record) {
			return response()->json([
				'success' => false,
				'message' => 'Transaction log not found.',
			], 404);
		}

		/* Decode JSON in 'description' field */
		if ($record->description && json_validate($record->description)) {
			$decoded = json_decode($record->description, true);
			/* Handle 'Error' field */
			array_walk_recursive($decoded, function (&$value, $key) {
				if ($key === 'Error') {
					$value = explode(' | ', $value);
				}
			});
			$record->description = $decoded;
		}

		/* Decode JSON in 'change_obj' field */
		if ($record->change_obj && json_validate($record->change_obj)) {
			$decoded = json_decode($record->change_obj, true);
			/* Handle 'Error' field */
			array_walk_recursive($decoded, function (&$value, $key) {
				if (is_string($value) && json_validate($value)) {
					$value = json_decode($value, true);
				}
			});
			$record->change_obj = $decoded;
		}

		return response()->json([
			'success' => true,
			'message' => 'Transaction log detail',
			'data' => $record
		]);
	}

	/**
	 * Show the form for editing the specified resource.
	 */
	public function edit(Website $website)
	{
		//
	}

	/**
	 * Update the specified resource in storage.
	 */
	public function update(Request $request, Website $website)
	{
		//
	}

	/**
	 * Remove the specified resource from storage.
	 */
	public function destroy(Website $website)
	{
		//
	}
}
