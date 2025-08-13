<?php

namespace App\Http\Controllers;

use App\Models\FrontEnd\CustomerEvent;
use Illuminate\Http\Request;

class CustomerEventController extends BaseController
{
	/**
	 * @OA\Get(
	 *     path="/api/customer-events",
	 *     summary="Get Customer Event List",
	 *     description="Fetches a list of customer events.",
	 *     tags={"Customer Events"},
	 *     @OA\Parameter(name="page", in="query", description="Page number for pagination", example=1, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="length", in="query", description="Number of records per page.", example=20, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="global", in="query", description="Global search for All field", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="id", in="query", description="Search by attribute id", @OA\Schema(type="integer")),
	 *     @OA\Parameter(name="name", in="query", description="Search by attribute name", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="code", in="query", description="Search by attribute code", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="type", in="query", description="Search by attribute type", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="sort_by", in="query", description="Column name to sort by", @OA\Schema(type="string", enum={"id", "name", "code", "type", "created_at", "updated_at"})),
	 *     @OA\Parameter(name="sort_dir", in="query", description="Sort direction (asc or desc)", example="asc", @OA\Schema(type="string", enum={"asc", "desc"})),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function index(Request $request)
	{
		$searchableColumns = ['id', 'name', 'code', 'type'];
		$sortableColumns = array_merge($searchableColumns, ['created_at', 'updated_at']);
		$sortBy = in_array($request->input('sort_by'), $sortableColumns) ? $request->input('sort_by') : 'id';
		$sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

		$recordsQuery = CustomerEvent::query();

		/* Apply global or column-specific filters */
		if ($request->filled('global')) {
			$search = $request->input('global');
			$recordsQuery->where(function ($q) use ($searchableColumns, $search) {
				foreach ($searchableColumns as $col) {
					$q->orWhere($col, 'LIKE', '%' . $search . '%');
				}
			});
		} else {
			foreach ($searchableColumns as $col) {
				if ($request->filled($col)) {
					$recordsQuery->where($col, 'LIKE', '%' . $request->input($col) . '%');
				}
			}
		}

		/* Pagination */
		if ($request->filled('page') && $request->filled('length')) {
			$recordsQuery->with(['attributeGroup:id,name', 'creator:id,first_name,last_name', 'updator:id,first_name,last_name']);


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
				'id', 'name', 'code', 'type', 'attribute_group_id', 'created_by', 'created_at', 'updated_at'
			]);

			/* Add attribute_group_name and created_by */
			$records->transform(function ($record) {
				$record->attribute_group_name = $record->attributeGroup->name ?? null;
				unset($record->attributeGroup);
				unset($record->attribute_group_id);

				$record->created_by = $record->creator->name ?? null;
				unset($record->creator);

				$record->updated_by = $record->updator->name ?? null;
				unset($record->updator);
				return $record;
			});
		} else {
			$records = $recordsQuery->orderBy('name', 'asc')->get([
				'id', 'name'
			]);
			$totalRecords = $records->count();
			$totalPages = 1;
		}

		return response()->json([
			'success' => true,
			'message' => __("msg_rec_list"),
			'data' => $records,
			'total_pages' => $totalPages ?? 1,
			'total_records' => $totalRecords,
		]);
	}

	/**
	 * Display the specified resource.
	 */
	public function show(CustomerEvent $customerEvent)
	{
		//
	}

	/**
	 * Update the specified resource in storage.
	 */
	public function update(Request $request, CustomerEvent $customerEvent)
	{
		//
	}

	/**
	 * Remove the specified resource from storage.
	 */
	public function destroy(CustomerEvent $customerEvent)
	{
		//
	}
}
