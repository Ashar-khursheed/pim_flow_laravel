<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CategoryMeasurementUnitPriority;
use App\Models\Category;
use App\Models\MeasurementUnit;

use App\Jobs\ImportCategoryPriorityJob;
use App\Services\ExcelImporterService;

class CategoryMeasurementUnitPriorityController extends BaseController
{
	/**
	 * @OA\Get(
	 *     path="/api/measurement-unit-priorities",
	 *     summary="Get all category measurement unit priorities",
	 *     tags={"Measurement Unit Priorities"},
	 *     @OA\Parameter(name="page", in="query", description="Page number for pagination", example=1, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="length", in="query", description="Number of records per page.", example=20, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="global", in="query", description="Global search for All field", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="id", in="query", description="Search by id", @OA\Schema(type="integer")),
	 *     @OA\Response(response=200, description="List of priorities", @OA\MediaType(mediaType="application/json")),
	 *     @OA\Parameter(name="sort_by", in="query", description="Column name to sort by", @OA\Schema(type="string", enum={"id", "created_at", "updated_at"})),
	 *     @OA\Parameter(name="sort_dir", in="query", description="Sort direction (asc or desc)", example="asc", @OA\Schema(type="string", enum={"asc", "desc"})),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function index(Request $request)
	{
		$data = CategoryMeasurementUnitPriority::all();

		$searchableColumns = ['id'];
		$sortableColumns = array_merge($searchableColumns, ['created_at', 'updated_at']);
		$sortBy = in_array($request->input('sort_by'), $sortableColumns) ? $request->input('sort_by') : 'id';
		$sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

		$recordsQuery = CategoryMeasurementUnitPriority::query();

		/* Pagination */
		if ($request->filled('page') && $request->filled('length')) {
			$recordsQuery->with(['measurementType', 'category', 'primaryMeasurementUnit', 'secondaryMeasurementUnit','creator:id,first_name,last_name']);

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
				'id', 'measurement_type_id', 'category_id', 'measurement_unit_primary_id', 'measurement_unit_secondary_id', 'created_by', 'created_at', 'updated_at'
			]);
			$records->transform(function ($record) {
				$record->measurement_type = $record->measurementType->name ?? '';
				$record->category_name = $record->category->name ?? '';
				$record->primary_measurement_unit = $record->primaryMeasurementUnit->name ?? '';
				$record->secondary_measurement_unit = $record->secondaryMeasurementUnit->name ?? '';
				$record->created_by = $record->creator->name;

				unset($record->measurementType, $record->category, $record->primaryMeasurementUnit, $record->secondaryMeasurementUnit, $record->creator, $record->measurement_type_id, $record->category_id, $record->measurement_unit_primary_id, $record->measurement_unit_secondary_id);
				return $record;
			});
		} else {
			// $records = $recordsQuery->orderBy('name', 'asc')->get([
			// 	'id', 'name'
			// ]);
			// $totalRecords = $records->count();
			// $totalPages = 1;
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
	 * @OA\Post(
	 *     path="/api/measurement-unit-priorities",
	 *     summary="Create a new category measurement unit priority",
	 *     tags={"Measurement Unit Priorities"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"measurement_type_id","category_id","measurement_unit_primary_id"},
	 *             @OA\Property(property="measurement_type_id", type="integer"),
	 *             @OA\Property(property="category_id", type="integer"),
	 *             @OA\Property(property="measurement_unit_primary_id", type="integer"),
	 *             @OA\Property(property="measurement_unit_secondary_id", type="integer", nullable=true)
	 *         )
	 *     ),
	 *     @OA\Response(response=201, description="Created successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function store(Request $request)
	{
		$request->validate([
			'measurement_type_id' => 'required|integer|exists:measurement_types,id',
			'category_id' => 'required|integer|exists:categories,id',
			'measurement_unit_primary_id' => 'required|integer|exists:measurement_units,id',
			'measurement_unit_secondary_id' => 'nullable|integer|exists:measurement_units,id',
		]);

		/* Ensure only leaf (last-level) categories are used */
		$validLeafCategory = Category::whereDoesntHave('children')->where('id', $request->category_id)->first();

		if (!$validLeafCategory) {
			return response()->json([
				'success' => false,
				'message' => 'Only leaf-level category (categories without children) can be selected.',
			], 422);
		}

		/* Check that the primary unit belongs to the specified type */
		$primaryValid = MeasurementUnit::where('id', $request->measurement_unit_primary_id)
		->where('measurement_type_id', $request->measurement_type_id)
		->exists();

		if (!$primaryValid) {
			return response()->json([
				'success' => false,
				'message' => 'The primary measurement unit does not belong to the selected measurement type.'
			], 422);
		}

		/* If secondary is present, check that it belongs to the same type */
		if ($request->filled('measurement_unit_secondary_id')) {
			$secondaryValid = MeasurementUnit::where('id', $request->measurement_unit_secondary_id)
			->where('measurement_type_id', $request->measurement_type_id)
			->exists();

			if (!$secondaryValid) {
				return response()->json([
					'success' => false,
					'message' => 'The secondary measurement unit does not belong to the selected measurement type.'
				], 422);
			}
		}

		if ($request->measurement_unit_primary_id === $request->measurement_unit_secondary_id) {
			return response()->json([
				'success' => false,
				'message' => 'Primary and secondary measurement units cannot be the same.'
			], 422);
		}

		$exists = CategoryMeasurementUnitPriority::where('measurement_type_id', $request->measurement_type_id)
		->where('category_id', $request->category_id)
		->exists();

		if ($exists) {
			return response()->json([
				'success' => false,
				'message' => 'This combination already exists.'
			], 409);
		}

		$data = $request->all();
		$data['created_by'] = auth()->id();

		$priority = CategoryMeasurementUnitPriority::create($data);

		return response()->json([
			'success' => true,
			'message' => __("msg_create"),
			'data' => $priority
		], 201);
	}

	/**
	 * @OA\Get(
	 *     path="/api/measurement-unit-priorities/{id}",
	 *     summary="Get category measurement unit priority details",
	 *     description="Fetches category measurement unit priority details based on the given ID.",
	 *     tags={"Measurement Unit Priorities"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         required=true,
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\Response(response=200, description="Details retrieved successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function show($id)
	{
		$record = CategoryMeasurementUnitPriority::with(['measurementType:id,name', 'category:id,name', 'primaryMeasurementUnit:id,name', 'secondaryMeasurementUnit:id,name'])->find($id);

		if (!$record) {
			return response()->json([
				'success' => false,
				'message' => __("err_exist")
			]);
		}

		return response()->json([
			'success' => true,
			'message' => __("msg_rec_dtl"),
			'data' => $record
		]);
	}

	/**
	 * @OA\Put(
	 *     path="/api/measurement-unit-priorities/{id}",
	 *     summary="Update an existing priority",
	 *     tags={"Measurement Unit Priorities"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         required=true,
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"measurement_type_id","category_id","measurement_unit_primary_id"},
	 *             @OA\Property(property="measurement_type_id", type="integer"),
	 *             @OA\Property(property="category_id", type="integer"),
	 *             @OA\Property(property="measurement_unit_primary_id", type="integer"),
	 *             @OA\Property(property="measurement_unit_secondary_id", type="integer", nullable=true)
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Updated successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function update(Request $request, $id)
	{
		$request->validate([
			'measurement_type_id' => 'required|integer|exists:measurement_types,id',
			'category_id' => 'required|integer|exists:categories,id',
			'measurement_unit_primary_id' => 'required|integer|exists:measurement_units,id',
			'measurement_unit_secondary_id' => 'nullable|integer|exists:measurement_units,id',
		]);

		/* Ensure only leaf (last-level) categories are used */
		$validLeafCategory = Category::whereDoesntHave('children')->where('id', $request->category_id)->first();

		if (!$validLeafCategory) {
			return response()->json([
				'success' => false,
				'message' => 'Only leaf-level category (categories without children) can be selected.',
			], 422);
		}

		/* Check that the primary unit belongs to the specified type */
		$primaryValid = MeasurementUnit::where('id', $request->measurement_unit_primary_id)
		->where('measurement_type_id', $request->measurement_type_id)
		->exists();

		if (!$primaryValid) {
			return response()->json([
				'success' => false,
				'message' => 'The primary measurement unit does not belong to the selected measurement type.'
			], 422);
		}

		/* If secondary is present, check that it belongs to the same type */
		if ($request->filled('measurement_unit_secondary_id')) {
			$secondaryValid = MeasurementUnit::where('id', $request->measurement_unit_secondary_id)
			->where('measurement_type_id', $request->measurement_type_id)
			->exists();

			if (!$secondaryValid) {
				return response()->json([
					'success' => false,
					'message' => 'The secondary measurement unit does not belong to the selected measurement type.'
				], 422);
			}
		}

		if ($request->measurement_unit_primary_id === $request->measurement_unit_secondary_id) {
			return response()->json([
				'success' => false,
				'message' => 'Primary and secondary measurement units cannot be the same.'
			], 422);
		}

		$priority = CategoryMeasurementUnitPriority::findOrFail($id);

		$exists = CategoryMeasurementUnitPriority::where('measurement_type_id', $request->measurement_type_id)
		->where('category_id', $request->category_id)
		->where('id', '!=', $id)
		->exists();

		if ($exists) {
			return response()->json([
				'success' => false,
				'message' => 'This combination already exists.'
			], 409);
		}

		$data = $request->all();
		$data['updated_by'] = auth()->id();

		$priority->update($data);

		return response()->json([
			'success' => true,
			'message' => __("msg_update"),
			'data' => $priority
		]);
	}

	/**
	 * @OA\Post(
	 *     path="/api/measurement-unit-priorities/import",
	 *     summary="Import measurement unit priorities from an excel file",
	 *     tags={"Measurement Unit Priorities"},
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
			$catPriorityFileFormatArray = [
				'Measurement Type' => 'measurementType',
				'Product Family' => 'productFamily',
				'Primary Unit' => 'primaryUnit',
				'Secondary Unit (Optional)' => 'secondaryUnit',
			];

			$excelImporter->processExcelImport(
				$request->file('upload_file'),
				$catPriorityFileFormatArray,
				'Product', /* Module name */
				config('app.website') . '_CAT_PRIORITY', /* Job name */
				'Import Category Priorities', /* Batch name */
				ImportCategoryPriorityJob::class
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
}
