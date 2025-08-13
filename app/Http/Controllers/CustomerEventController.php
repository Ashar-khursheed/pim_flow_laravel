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
	 *
	 *     @OA\Parameter(name="event_type", in="query",  @OA\Schema(type="string")),
	 *     @OA\Parameter(name="page_url", in="query",  @OA\Schema(type="string")),
	 *     @OA\Parameter(name="element", in="query",  @OA\Schema(type="string")),
	 *     @OA\Parameter(name="customer_name", in="query",  @OA\Schema(type="string")),
	 *     @OA\Parameter(name="session_id", in="query",  @OA\Schema(type="string")),
	 *     @OA\Parameter(name="ip_address", in="query",  @OA\Schema(type="string")),
	 *     @OA\Parameter(name="user_agent", in="query",  @OA\Schema(type="string")),
	 *
	 *     @OA\Parameter(name="sort_by", in="query", description="Column name to sort by", @OA\Schema(type="string", enum={"id", "event_type", "page_url", "element", "customer_name", "session_id", "ip_address", "user_agent", "created_at", "updated_at"})),
	 *     @OA\Parameter(name="sort_dir", in="query", description="Sort direction (asc or desc)", example="asc", @OA\Schema(type="string", enum={"asc", "desc"})),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function index(Request $request)
	{
		$searchableColumns = ['id', 'event_type', 'page_url', 'element', 'customer_name', 'session_id', 'ip_address', 'user_agent'];
		$sortableColumns = array_merge($searchableColumns, ['created_at', 'updated_at']);
		$sortBy = in_array($request->input('sort_by'), $sortableColumns) ? $request->input('sort_by') : 'id';
		$sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

		$recordsQuery = CustomerEvent::query();

		/* Pagination */
		if ($request->filled('page') && $request->filled('length')) {

			if ($sortBy === 'customer_name' || ($request->filled('global') && in_array('customer_name', $searchableColumns))) {
				$recordsQuery->leftJoin('customers', 'customer_events.customer_id', '=', 'customers.id');
				$recordsQuery->select('customer_events.*');
			}
			$recordsQuery->with(['customer:id,name']);


			/* Apply global or column-specific filters */
			if ($request->filled('global')) {
				$search = $request->input('global');
				$recordsQuery->where(function ($q) use ($searchableColumns, $search) {
					foreach ($searchableColumns as $col) {
						if ($col === 'customer_name') {
							$q->orWhereHas('customer', function ($sub) use ($search) {
								$sub->where('name', 'like', '%' . $search . '%');
							});
						} else {
							$q->orWhere("customer_events.$col", 'like', '%' . $search . '%');
						}
					}
				});
			} else {
				foreach ($searchableColumns as $col) {
					if ($request->filled($col)) {
						$search = $request->input($col);
						if ($col === 'customer_name') {
							$recordsQuery->whereHas('customer', function ($sub) use ($search) {
								$sub->where('name', 'like', '%' . $search . '%');
							});
						} else {
							$recordsQuery->where("customer_events.$col", 'like', '%' . $search . '%');
						}
					}
				}
			}

			if ($sortBy === 'customer_name') {
				$recordsQuery->orderBy('customers.name', $sortDir);
			} else {
				$recordsQuery->orderBy("customer_events.$sortBy", $sortDir);
			}

			/* Clone query for counting */
			$totalRecords = (clone $recordsQuery)->count();
			$length = (int) $request->input('length');
			$totalPages = (int) ceil($totalRecords / $length);

			$page = (int) $request->input('page');
			/* If requested page exceeds total pages (after search), fallback to page 1 */
			if ($page > $totalPages && $totalPages > 0) {
				$page = 1;
			}

			$records = $recordsQuery->offset(($page - 1) * $length)->limit($length)->get();

			/* Add attribute_group_name and created_by */
			$records->transform(function ($record) {
				$record->customer_name = $record->customer->name ?? null;
				unset($record->customer_id);
				return $record;
			});
		} else {
			$records = $recordsQuery->orderBy('event_type', 'asc')->get([
				'id', 'event_type'
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
	 * @OA\Get(
	 *     path="/api/customer-events/{id}",
	 *     summary="Get customer event details",
	 *     tags={"Customer Events"},
	 *     security={{"bearerAuth":{}}},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         description="Event ID",
	 *         required=true,
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\Response(response=200, description="Details retrieved successfully", @OA\MediaType(mediaType="application/json"))
	 * )
	 */
	public function show($id)
	{
		$customerEvent = CustomerEvent::find($id);

		if (!$customerEvent) {
			return response()->json([
				'success' => false,
				'message' => "Customer event not found."
			]);
		}

		/* Load relationships */
		$customerEvent->load([
			'customer:id,name'
		]);

		return response()->json([
			'success' => true,
			'data' => $customerEvent
		]);
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
