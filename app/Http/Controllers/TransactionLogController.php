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
	 *     @OA\Parameter(
	 *         name="module",
	 *         in="query",
	 *         description="Filter transaction logs by module (All, Product, Product Attribute).",
	 *         required=false,
	 *         example="Product",
	 *         @OA\Schema(
	 *             type="string",
	 *             enum={"All", "Product", "Product Attribute"}
	 *         )
	 *     ),
	 *     @OA\Parameter(
	 *         name="action",
	 *         in="query",
	 *         description="Filter transaction logs by action (All, Import).",
	 *         required=false,
	 *         example="Import",
	 *         @OA\Schema(
	 *             type="string",
	 *             enum={"All", "Import"}
	 *         )
	 *     ),
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
		$records = TransactionLog::query();

		if ($request->filled('module') && in_array($request->module, ['Product', 'Product Attribute'])) {
			$records->where('module', $request->module);
		}

		if ($request->filled('action') && in_array($request->action, ['Import'])) {
			$records->where('action', $request->action);
		}

		/* Pagination */
		if ($request->filled('page') && $request->filled('length')) {
			$page = (int) $request->input('page');
			$length = (int) $request->input('length');
			$totalRecords = $records->count();
			$totalPages = ceil($totalRecords / $length);

			$records = $records->offset(($page - 1) * $length)->limit($length)->get();
		} else {
			$records = $records->get();
		}

		/* Decode JSON in 'description' field */
		$records->transform(function ($record) {
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

			return $record;
		});

		return response()->json([
			'success' => true,
			'message' => 'Transaction Log List',
			'data' => $records,
			'total_pages' => $totalPages ?? 1,
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
	public function show(Website $website)
	{
		//
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
