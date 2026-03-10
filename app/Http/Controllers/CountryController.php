<?php

namespace App\Http\Controllers;

use App\Models\Country;
use Illuminate\Http\Request;

class CountryController extends Controller
{
	/**
	 * @OA\Get(
	 *     path="/api/countries",
	 *     summary="Get list of countries",
	 *     description="Fetches a list of all countries.",
	 *     tags={"Countries"},
	 *     @OA\Parameter(name="page", in="query", description="Page number", example=1, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="length", in="query", description="Records per page.", example=20, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="search", in="query", description="Global search for All field", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="sort_by", in="query", description="Column name to sort by", @OA\Schema(type="string", enum={"id", "name", "phone_code", "margin", "created_at", "updated_at"})),
	 *     @OA\Parameter(name="sort_dir", in="query", description="Sort direction (asc or desc)", example="asc", @OA\Schema(type="string", enum={"asc", "desc"})),
	 *     @OA\Response(response=200, description="Countries retrieved successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function index(Request $request)
	{
		$searchableColumns = ['id', 'name', 'phone_code', 'margin'];
		$sortableColumns = array_merge($searchableColumns, ['created_at', 'updated_at']);
		$sortBy = in_array($request->input('sort_by'), $sortableColumns) ? $request->input('sort_by') : 'id';
		$sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

		$recordsQuery = Country::query();

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
				'currency:id,title,symbol',
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
				'name',
				'phone_code',
				'currency_id',
				'margin',
				'created_by',
				'created_at',
				'updated_at'
			]);

			/* Transform records (optimized) */
			$records = $records->map(function ($record) {
				return [
					'id' => $record->id,
					'name' => $record->name,
					'phone_code' => $record->phone_code,
					'currency_title' => $record->currency->title ?? null,
					'currency_symbol' => $record->currency->symbol ?? null,
					'margin' => $record->margin,
					'created_by' => $record->creator->name ?? null,
					'created_at' => $record->created_at,
					'updated_at' => $record->updated_at,
				];
			});

		} else {
			/* Return all records with minimal fields (for dropdowns) */
			$records = $recordsQuery->get(['id', 'name']);
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
	 *     path="/api/countries",
	 *     summary="Create a new country",
	 *     tags={"Countries"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="multipart/form-data",
	 *             @OA\Schema(
	 *                 required={"name", "phone_code", "currency_id"},
	 *                 @OA\Property(property="name", type="string", example="United Arab Emirates"),
	 *                 @OA\Property(property="phone_code", type="string", example="+971"),
	 *                 @OA\Property(property="icon", type="file", format="binary", description="Country icon (.webp, .png)"),
	 *                 @OA\Property(property="currency_id", type="integer", example=1),
	 *                 @OA\Property(property="margin", type="number", format="float", example=5.00)
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=201, description="Country created successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function store(Request $request)
	{
		/* Validate request data */
		$request->validate([
			'name' => 'required|string|max:191|unique:countries,name',
			'phone_code' => 'required|string|max:10',
			'icon' => 'nullable|file|mimes:webp,png|max:2048', /* Max 2MB */
			'currency_id' => 'required|integer|exists:ec_currencies,id',
			'margin' => 'nullable|numeric|min:0',
		]);

		/* Handle File Upload to S3 */
		$icon = uploadImageToWebpS3FromFile($request, 'icon', env('STORAGE_ENV') . '/country/icon');

		$country = Country::create([
			'name' => $request->name,
			'phone_code' => $request->phone_code,
			'icon' => $icon,
			'currency_id' => $request->currency_id,
			'margin' => $request->margin ?? 0,
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
	 *     path="/api/countries/{id}",
	 *     summary="Get a specific country",
	 *     tags={"Countries"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         description="Country ID",
	 *         required=true,
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\Response(response=200, description="Country retrieved successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function show($id)
	{
		$country = Country::with(['currency:id,title,symbol', 'creator:id,first_name,last_name', 'updater:id,first_name,last_name'])
		->find($id);

		if (!$country) {
			return response()->json([
				'success' => false,
				'message' => 'Country not found'
			], 404);
		}

		$data = [
			'id' => $country->id,
			'name' => $country->name,
			'phone_code' => $country->phone_code,
			'icon' => $country->icon,
			'currency_id' => $country->currency_id,
			'currency_title' => $country->currency->title ?? null,
			'currency_symbol' => $country->currency->symbol ?? null,
			'margin' => (float) $country->margin,
			'created_by_name' => $country->creator->name ?? null,
			'updated_by_name' => $country->updater->name ?? null,
			'created_at' => $country->created_at?->format('Y-m-d H:i:s'),
			'updated_at' => $country->updated_at?->format('Y-m-d H:i:s'),
		];

		return response()->json([
			'success' => true,
			'message' => 'Country retrieved successfully',
			'data' => $data
		], 200);
	}

	/**
	 * @OA\Put(
	 *     path="/api/countries/{id}",
	 *     summary="Update a country",
	 *     tags={"Countries"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         description="Country ID",
	 *         required=true,
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="multipart/form-data",
	 *             @OA\Schema(
	 *                 required={"_method", "name", "phone_code", "currency_id"},
	 *                 @OA\Property(property="_method", type="string", example="PUT", description="HTTP method override"),
	 *                 @OA\Property(property="name", type="string", example="United Arab Emirates"),
	 *                 @OA\Property(property="phone_code", type="string", example="+971"),
	 *                 @OA\Property(property="icon", type="file", format="binary", description="Country icon (.webp, .png)"),
	 *                 @OA\Property(property="icon_url", type="string", example="https://example.com/image.png"),
	 *                 @OA\Property(property="currency_id", type="integer", example=1),
	 *                 @OA\Property(property="margin", type="number", format="float", example=5.00)
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Country updated successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function update(Request $request, $id)
	{
		$country = Country::find($id);

		if (!$country) {
			return response()->json([
				'success' => false,
				'message' => __('err_exist')
			], 404);
		}

		/* Validate request data */
		$request->validate([
			'name' => 'required|string|max:191|unique:countries,name,' . $id,
			'phone_code' => 'required|string|max:10',
			'icon' => 'nullable|file|mimes:webp,png|max:2048',
			'icon_url' => 'nullable|string|url',
			'currency_id' => 'required|integer|exists:ec_currencies,id',
			'margin' => 'nullable|numeric|min:0',
		]);

		/* Handle icon upload/update */
		if ($request->hasFile('icon')) {
			/* Upload new icon */
			$icon = uploadImageToWebpS3FromFile($request, 'icon', env('STORAGE_ENV') . '/country/icon');

			$oldIcon = $country->icon;
			/* Delete old icon from S3 if exists and new upload successful */
			if ($icon && $oldIcon && str_contains($oldIcon, env('STORAGE_ENV'))) {
				try {
					/* Simply replace AWS URL to get S3 path */
					$oldPath = str_replace(env('AWS_URL') . '/', '', $oldIcon);
					Storage::disk('s3')->delete($oldPath);
				} catch (\Exception $e) {
					Log::warning('Failed to delete old icon', [
						'country_id' => $id,
						'old_icon' => $oldIcon,
						'error' => $e->getMessage()
					]);
				}
			}
		} elseif (!empty($request->icon_url)) {
			$icon = $request->icon_url;
		} else {
			$icon = $country->icon;
		}

		/* Update country */
		$country->update([
			'name' => $request->name,
			'phone_code' => $request->phone_code,
			'icon' => $icon,
			'currency_id' => $request->currency_id,
			'margin' => $request->margin ?? 0,
			'updated_by' => auth()->id(),
		]);

		return response()->json([
			'success' => true,
			'message' => __('msg_update'),
		], 200);
	}

	/**
	 * @OA\Delete(
	 *     path="/api/countries/{id}",
	 *     summary="Delete a country",
	 *     tags={"Countries"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         description="Country ID",
	 *         required=true,
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\Response(response=200, description="Country deleted successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function destroy($id)
	{
		$country = Country::find($id);

		if (!$country) {
			return response()->json([
				'success' => false,
				'message' => __('err_exist')
			], 404);
		}

		/* Delete icon from S3 */
		if ($country->icon && str_contains($country->icon, env('STORAGE_ENV'))) {
			try {
				$iconPath = str_replace(env('AWS_URL') . '/', '', $country->icon);
				Storage::disk('s3')->delete($iconPath);
			} catch (\Exception $e) {
				Log::warning('Failed to delete country icon', [
					'country_id' => $id,
					'icon' => $country->icon,
					'error' => $e->getMessage()
				]);
			}
		}

		$country->delete();

		return response()->json([
			'success' => true,
			'message' => __('msg_dlt')
		], 200);
	}
}