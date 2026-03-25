<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
	/**
	 * @OA\Get(
	 *     path="/api/currencies",
	 *     summary="Get list of currencies",
	 *     description="Fetches a list of all currencies.",
	 *     tags={"Currencies"},
	 *     @OA\Parameter(name="page", in="query", description="Page number", example=1, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="length", in="query", description="Records per page.", example=20, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="search", in="query", description="Global search for All field", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="sort_by", in="query", description="Column name to sort by", @OA\Schema(type="string", enum={"id", "title", "symbol", "is_default", "created_at", "updated_at"})),
	 *     @OA\Parameter(name="sort_dir", in="query", description="Sort direction (asc or desc)", example="asc", @OA\Schema(type="string", enum={"asc", "desc"})),
	 *     @OA\Response(response=200, description="Currencies retrieved successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function index(Request $request)
	{
		$searchableColumns = ['id', 'title', 'symbol', 'major_unit_name', 'minor_unit_name'];
		$sortableColumns = array_merge($searchableColumns, ['is_default', 'created_at', 'updated_at']);
		$sortBy = in_array($request->input('sort_by'), $sortableColumns) ? $request->input('sort_by') : 'id';
		$sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

		$recordsQuery = Currency::query();

		/* Apply search filter */
		if ($request->filled('search')) {
			$search = $request->input('search');
			$recordsQuery->where(function ($q) use ($searchableColumns, $search) {
				foreach ($searchableColumns as $col) {
					$q->orWhere($col, 'LIKE', '%' . $search . '%');
				}
			});
		}

		/* Apply sorting (for both pagination and dropdown) */
		$recordsQuery->orderBy($sortBy, $sortDir);

		if ($request->filled('page') && $request->filled('length')) {
			$totalRecords = (clone $recordsQuery)->count();

			/* Eager load relationships */
			$recordsQuery->with([
				'creator:id,first_name,last_name',
			]);

			$length = max(1, (int) $request->input('length'));
			$totalPages = (int) ceil($totalRecords / $length);
			$page = max(1, (int) $request->input('page'));

			/* If requested page exceeds total pages, fallback to page 1 */
			if ($page > $totalPages && $totalPages > 0) {
				$page = 1;
			}

			$records = $recordsQuery
				->offset(($page - 1) * $length)
				->limit($length)
				->get([
					'id',
					'title',
					'symbol',
					'major_unit_name',
					'minor_unit_name',
					'is_default',
					'created_by',
					'created_at',
					'updated_at'
				]);

			/* Transform records (optimized) */
			$records = $records->map(function ($record) {
				return [
					'id' => $record->id,
					'title' => $record->title,
					'symbol' => $record->symbol,
					'major_unit_name' => $record->major_unit_name,
					'minor_unit_name' => $record->minor_unit_name,
					'is_default' => (bool) $record->is_default,
					'created_by' => $record->creator->name ?? null,
					'created_at' => $record->created_at,
					'updated_at' => $record->updated_at,
				];
			});

		} else {
			/* Return all records with minimal fields (for dropdowns) */
			$records = $recordsQuery->get(['id', 'title', 'symbol']);
			$totalRecords = $records->count();
			$totalPages = 1;
		}

		return response()->json([
			'message' => __("msg_rec_list"),
			'data' => $records,
			'total_pages' => $totalPages,
			'total_records' => $totalRecords,
		], 200);
	}

	/**
	 * @OA\Post(
	 *     path="/api/currencies",
	 *     summary="Create a new currency",
	 *     tags={"Currencies"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="application/json",
	 *             @OA\Schema(
	 *                 required={"title", "symbol"},
	 *                 @OA\Property(property="title", type="string", example="USD"),
	 *                 @OA\Property(property="symbol", type="string", example="$"),
	 *                 @OA\Property(property="major_unit_name", type="string", example="U.S. Dollars"),
	 *                 @OA\Property(property="minor_unit_name", type="string", example="Cents"),
	 *                 @OA\Property(property="is_default", type="boolean", example=false)
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=201, description="Currency created successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function store(Request $request)
	{
		/* Validate request data */
		$request->validate([
			'title' => 'required|string|max:191|unique:currencies,title',
			'symbol' => 'required|string|max:10',
			'major_unit_name' => 'nullable|string|max:50',
			'minor_unit_name' => 'nullable|string|max:50',
			'is_default' => 'nullable|boolean',
		]);

		/* If this currency is set as default, unset all others */
		if ($request->is_default) {
			Currency::where('is_default', 1)->update(['is_default' => 0]);
		}

		$currency = Currency::create([
			'title' => $request->title,
			'symbol' => $request->symbol,
			'major_unit_name' => $request->major_unit_name,
			'minor_unit_name' => $request->minor_unit_name,
			'is_default' => $request->is_default ?? 0,
			'created_by' => auth()->id(),
			'updated_by' => auth()->id(),
		]);

		return response()->json([
			'success' => true,
			'message' => __("msg_create"),
		], 201);
	}

	/**
	 * @OA\Get(
	 *     path="/api/currencies/{id}",
	 *     summary="Get a specific currency",
	 *     tags={"Currencies"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         description="Currency ID or title",
	 *         required=true,
	 *         @OA\Schema(type="string", example="1")
	 *     ),
	 *     @OA\Response(response=200, description="Currency retrieved successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function show($id)
	{
		$query = Currency::with(['creator:id,first_name,last_name', 'updater:id,first_name,last_name']);

		/* Fetch by ID or Title */
		if (is_numeric($id)) {
			$currency = $query->find($id);
		} else {
			$currency = $query->where('title', $id)->first();
		}

		if (!$currency) {
			return response()->json([
				'success' => false,
				'message' => 'Currency not found'
			], 404);
		}

		$data = [
			'id' => $currency->id,
			'title' => $currency->title,
			'symbol' => $currency->symbol,
			'major_unit_name' => $currency->major_unit_name,
			'minor_unit_name' => $currency->minor_unit_name,
			'is_default' => (bool) $currency->is_default,
			'created_by_name' => $currency->creator->name ?? null,
			'updated_by_name' => $currency->updater->name ?? null,
			'created_at' => $currency->created_at?->format('Y-m-d H:i:s'),
			'updated_at' => $currency->updated_at?->format('Y-m-d H:i:s'),
		];

		return response()->json([
			'success' => true,
			'message' => 'Currency retrieved successfully',
			'data' => $data
		], 200);
	}

	/**
	 * @OA\Put(
	 *     path="/api/currencies/{id}",
	 *     summary="Update a currency",
	 *     tags={"Currencies"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         description="Currency ID",
	 *         required=true,
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="application/json",
	 *             @OA\Schema(
	 *                 required={"title", "symbol"},
	 *                 @OA\Property(property="title", type="string", example="USD"),
	 *                 @OA\Property(property="symbol", type="string", example="$"),
	 *                 @OA\Property(property="major_unit_name", type="string", example="U.S. Dollars"),
	 *                 @OA\Property(property="minor_unit_name", type="string", example="Cents"),
	 *                 @OA\Property(property="is_default", type="boolean", example=false)
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Currency updated successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function update(Request $request, $id)
	{
		$currency = Currency::find($id);

		if (!$currency) {
			return response()->json([
				'success' => false,
				'message' => __('err_exist')
			], 404);
		}

		/* Validate request data */
		$request->validate([
			'title' => 'required|string|max:191|unique:currencies,title,' . $id,
			'symbol' => 'required|string|max:10',
			'major_unit_name' => 'nullable|string|max:50',
			'minor_unit_name' => 'nullable|string|max:50',
			'is_default' => 'nullable|boolean',
		]);

		/* If this currency is set as default, unset all others */
		if ($request->is_default) {
			Currency::where('is_default', 1)->where('id', '!=', $id)->update(['is_default' => 0]);
		}

		/* Update currency */
		$currency->update([
			'title' => $request->title,
			'symbol' => $request->symbol,
			'major_unit_name' => $request->major_unit_name,
			'minor_unit_name' => $request->minor_unit_name,
			'is_default' => $request->is_default ?? 0,
			'updated_by' => auth()->id(),
		]);

		return response()->json([
			'success' => true,
			'message' => __('msg_update'),
		], 200);
	}

	/**
	 * @OA\Delete(
	 *     path="/api/currencies/{id}",
	 *     summary="Delete a currency",
	 *     tags={"Currencies"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         description="Currency ID",
	 *         required=true,
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\Response(response=200, description="Currency deleted successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function destroy($id)
	{
		$currency = Currency::find($id);

		if (!$currency) {
			return response()->json([
				'success' => false,
				'message' => __('err_exist')
			], 404);
		}

		/* Prevent deletion if currency is in use */
		if ($currency->countries()->exists()) {
			return response()->json([
				'success' => false,
				'message' => 'Cannot delete currency. It is being used by one or more countries.'
			], 422);
		}

		/* Prevent deletion of default currency */
		if ($currency->is_default) {
			return response()->json([
				'success' => false,
				'message' => 'Cannot delete the default currency.'
			], 422);
		}

		$currency->delete();

		return response()->json([
			'success' => true,
			'message' => __('msg_dlt')
		], 200);
	}

	/**
	 * @OA\Get(
	 *     path="/api/currencies/convert",
	 *     summary="Convert currency",
	 *     description="Convert amount from source currency to all currencies or a specific target currency.",
	 *     tags={"Currencies"},
	 *     @OA\Parameter(name="from", in="query", required=true, description="Source currency code", example="AED", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="amount", in="query", required=true, description="Amount to convert", example=100, @OA\Schema(type="number", minimum=0)),
	 *     @OA\Parameter(name="to", in="query", required=false, description="Target currency code", example="INR", @OA\Schema(type="string")),
	 *     @OA\Response(response=200, description="Currency converted successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function convertAll(Request $request)
	{
	    $request->validate([
	        'from'   => 'required|string|size:3',
	        'amount' => 'required|numeric|min:0',
	        'to'     => 'nullable|string|size:3',
	    ]);

	    $from   = strtoupper($request->input('from'));
	    $amount = (float) $request->input('amount');
	    $to     = $request->filled('to') ? strtoupper($request->input('to')) : null;

	    $availableCurrencies = CurrencyConverter::getAvailableCurrencies();

	    if (empty($availableCurrencies)) {
	        return response()->json(['success' => false, 'message' => 'Exchange rates not available.']);
	    }

	    if (!in_array($from, $availableCurrencies)) {
	        return response()->json(['success' => false, 'message' => "Source currency '{$from}' not found."]);
	    }

	    /* If "to" is provided, return single conversion */
	    if ($to) {
	        if (!in_array($to, $availableCurrencies)) {
	            return response()->json(['success' => false, 'message' => "Target currency '{$to}' not found."]);
	        }

	        return response()->json([
	            'success' => true,
	            'data'    => [
	                $to => CurrencyConverter::convertCurrency($from, $to, $amount),
	            ],
	        ], 200);
	    }

	    /* Otherwise return all currencies */
	    $conversions = [];
	    foreach ($availableCurrencies as $currency) {
	        $conversions[$currency] = CurrencyConverter::convertCurrency($from, $currency, $amount);
	    }

	    return response()->json([
	        'success' => true,
	        'data'    => $conversions,
	    ], 200);
	}
}