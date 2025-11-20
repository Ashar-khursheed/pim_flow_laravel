<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Bus;
use Illuminate\Bus\Batch;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Product;
use App\Models\TransactionLog;
use App\Models\MeasurementUnit;

use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

use App\Services\ExcelImporterService;
use App\Repository\ExcelRepository;

use App\Jobs\ImportProductAttributeJob;

class AttributeController extends BaseController
{
	/**
	 * @OA\Get(
	 *     path="/api/attributes",
	 *     summary="Get Attribute List",
	 *     description="Fetches a list of attributes.",
	 *     tags={"Attributes"},
	 *     @OA\Parameter(name="has_group", in="query",  @OA\Schema(type="string", enum={"true", "false"}, nullable=true),
	 *         description="Filter attributes that have at least one associated group. Accepts 'true' or 'false'. If omitted, no filtering is applied."
	 *     ),
	 *     @OA\Parameter(name="page", in="query", description="Page number for pagination", example=1, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="length", in="query", description="Number of records per page.", example=20, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="attribute_group_id", in="query", description="Search by attribute group id.", example=6, @OA\Schema(type="integer", minimum=1)),
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
		if (!auth()->user()->can('list attribute')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}

		$hasGroup = $request->query('has_group', $request->input('has_group'));

		if ($hasGroup !== null) {
			$hasGroup = filter_var($hasGroup, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
		}

		$searchableColumns = ['id', 'name', 'code', 'type'];
		$sortableColumns = array_merge($searchableColumns, ['created_at', 'updated_at']);
		$sortBy = in_array($request->input('sort_by'), $sortableColumns) ? $request->input('sort_by') : 'id';
		$sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

		// $recordsQuery = Attribute::with(['attributeGroup:id,name', 'attributeValues:id,attribute_id,attribute_value']);
		$recordsQuery = Attribute::query();

		if ($hasGroup === false) {
			$recordsQuery->whereNull('attribute_group_id');
		} elseif ($hasGroup === true) {
			$recordsQuery->whereNotNull('attribute_group_id');
		}

		if (!empty($request->attribute_group_id)) {
			$recordsQuery->where('attribute_group_id', $request->attribute_group_id);
		}

		/* Pagination */
		if ($request->filled('page') && $request->filled('length')) {
			$recordsQuery->with(['attributeGroup:id,name', 'creator:id,first_name,last_name', 'updator:id,first_name,last_name']);

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
	 * @OA\Post(
	 *     path="/api/attributes",
	 *     summary="Create a new attribute",
	 *     description="Creates a new attribute with name, code, and type.",
	 *     tags={"Attributes"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"name", "code", "type"},
	 *             @OA\Property(property="name", type="string", example="Color"),
	 *             @OA\Property(property="code", type="string", example="color"),
	 *             @OA\Property(property="type", type="string", example="text")
	 *         )
	 *     ),
	 *     @OA\Response(response=201, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function store(Request $request)
	{
		if (!auth()->user()->can('add attribute')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}
		/* Validate request data */
		$request->validate([
			'name' => "required|unique:attributes,name",
			'code' => "required|unique:attributes,code",
			'type' => "required"
		]);

		$attribute = new Attribute();
		$attribute->name = $request->name;
		$attribute->code = $request->code;
		$attribute->type = $request->type;
		$attribute->created_by = auth()->id();
		$attribute->updated_by = auth()->id();
		$attribute->created_at = now();
		$attribute->updated_at = now();
		$attribute->save();

		if (in_array(config('app.website'), ['UAE', 'UAE_T', 'SA'])) {
			$attribute->translateOrNew('en')->name_tr = $request->name;
		}

		$attribute->save();

		return response()->json([
			'success' => true,
			'message' => __("msg_create"),
			'data' => $attribute
		]);
	}

	/**
	 * @OA\Get(
	 *     path="/api/attributes/{attribute_id}",
	 *     summary="Get attribute details",
	 *     description="Fetches attribute details based on the given attribute ID.",
	 *     tags={"Attributes"},
	 *     @OA\Parameter(
	 *         name="attribute_id",
	 *         in="path",
	 *         required=true,
	 *         description="ID of the attribute",
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\Parameter(name="locale", in="query", required=true, @OA\Schema(type="string", enum={"ar", "en"}, example="ar")),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function show($attributeId)
	{
		if (!auth()->user()->can('show attribute')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}
		$locale = in_array($request->locale ?? 'en', ['ar', 'en']) ? ($request->locale ?? 'en') : 'en';
		$attribute = Attribute::with(['attributeValues:id,attribute_id,attribute_value', 'attributeGroup:id,name', 'measurementUnits:id,name,measurement_type_id'])->find($attributeId);

		if (!$attribute) {
			return response()->json([
				'success' => false,
				'message' => __("err_exist")
			]);
		}

		// $translation = $attribute->translations->firstWhere('locale', $locale);

		/* Append measurement_type from first unit if exists */
		$firstUnit = $attribute->measurementUnits->first();
		if ($firstUnit && $firstUnit->type) {
			$attribute->measurement_type = [
				'id' => $firstUnit->type->id,
				'name' => $firstUnit->type->name
			];
		}

		$attribute->measurementUnits->each->makeHidden(['pivot', 'measurement_type_id']);


		$attribute->validations = json_decode($attribute->validations);

		// $field = 'name_tr';
		// $attribute->name = $translation ? $translation->$field : $attribute->name;

		return response()->json([
			'success' => true,
			'message' => __("msg_rec_dtl"),
			'data' => $attribute
		]);
	}

	/**
	 * @OA\Put(
	 *     path="/api/attributes/{id}",
	 *     summary="Update an existing attribute",
	 *     description="Updates an attribute's details, including name, code, type, required status, and validations.",
	 *     tags={"Attributes"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         required=true,
	 *         description="ID of the attribute to update",
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"name", "code", "type"},
	 *             @OA\Property(property="name", type="string", example="Size"),
	 *             @OA\Property(property="code", type="string", example="size"),
	 *             @OA\Property(property="type", type="string", example="select"),
	 *             @OA\Property(property="attribute_group_id", type="integer", example="1"),
	 *             @OA\Property(property="measurement_units_ids", type="array", description="Required if type is 'measurement'", @OA\Items(type="integer", example="1")),
	 *             @OA\Property(property="attribute_values", type="array", description="Array of attribute values", @OA\Items(type="string", example="value1")),
	 *             @OA\Property(
	 *                 property="validations",
	 *                 type="object",
	 *                 @OA\Property(property="min", type="integer", example=250),
	 *                 @OA\Property(property="max", type="integer", example=260),
	 *                 @OA\Property(property="required", type="boolean", example=true)
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function update(Request $request, $attributeId)
	{
		if (!auth()->user()->can('update attribute')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			], 403);
		}

		$attribute = Attribute::find($attributeId);
		if (!$attribute) {
			return response()->json([
				'success' => false,
				'message' => __("err_exist")
			], 404);
		}

		/* Validate request data */
		$request->validate([
			'name' => "required|unique:attributes,name," . $attributeId,
			'code' => "required|unique:attributes,code," . $attributeId,
			'type' => "required",
			'attribute_group_id' => 'nullable|exists:attribute_groups,id',
			// 'measurement_units_ids' => 'array|required_if:type,measurement',
			'measurement_units_ids' => 'array',
			'measurement_units_ids.*' => 'integer|exists:measurement_units,id',
			'attribute_values' => 'array',
			'attribute_values.*' => 'string',
		]);

		$input = $request->all();
		$locale = $request->locale ?? 'en';

		DB::beginTransaction();
		try {
			/* Update validations if provided */
			if (isset($input['validations'])) {
				$attribute->validations = json_encode($input['validations']);
			}

			/* Sync measurement units if type is 'measurement' */
			if ($request->type === 'measurement' && isset($input['measurement_units_ids'])) {
				$attribute->measurementUnits()->sync($input['measurement_units_ids']);
			}

			/* Detach measurement units if type changed from 'measurement' */
			if ($request->type !== 'measurement' && $attribute->type === 'measurement') {
				$attribute->measurementUnits()->detach();
			}

			/* Sync attribute values if type is 'select' */
			if ($request->type === 'select' && isset($input['attribute_values'])) {
				$providedValues = $input['attribute_values'];
				$existingValues = $attribute->attributeValues()->pluck('attribute_value')->toArray();

				$valuesToDelete = array_diff($existingValues, $providedValues);
				$valuesToAdd = array_diff($providedValues, $existingValues);

				/* Delete removed values and their translations */
				if (!empty($valuesToDelete)) {
					$attributeValuesToDelete = $attribute->attributeValues()
					->whereIn('attribute_value', $valuesToDelete)
					->get();

					foreach ($attributeValuesToDelete as $value) {
						/* Delete translations if available */
						if (method_exists($value, 'translations')) {
							$value->translations()->delete();
						}
						$value->delete();
					}
				}

				/* Add new values with translation */
				foreach ($valuesToAdd as $newValue) {
					$newAttributeValue = $attribute->attributeValues()->create([
						'attribute_value' => $newValue,
					]);

					/* Since input is always in English, save translation explicitly */
					if (in_array(config('app.website'), ['UAE', 'UAE_T', 'SA'])) {
						$newAttributeValue->translateOrNew('en')->attribute_value_tr = $newValue;
					}
					$newAttributeValue->save();
				}
			}


			/* Delete attribute values if type changed from 'select' */
			if ($request->type !== 'select' && $attribute->type === 'select') {
				foreach ($attribute->attributeValues as $value) {
					if (method_exists($value, 'translations')) {
						$value->translations()->delete();
					}
					$value->delete();
				}
			}


			/* Fill only the allowed fields */
			$fillableFields = [
				'name', 'code', 'type', 'attribute_group_id'
			];
			foreach ($fillableFields as $field) {
				if (array_key_exists($field, $input)) {
					// if ($field == 'name') {
					// 	$updatedName = $input['name'];
					// 	/* use translated table */
					// 	$existingName = optional($product->translate($locale))->name ?? [];

					// 	/* Only save if changed */
					// 	if ($updatedName !== $existingName) {
					// 		if ($locale === 'en') {
					// 			$attribute->name = $updatedName;
					// 		}

					// 		if (in_array(config('app.website'), ['UAE', 'UAE_T', 'SA'])) {
					// 			$attribute->translateOrNew($locale)->name_tr = $updatedName;
					// 		}
					// 		$attribute->save();
					// 	}
					if ($field == 'name') {
<<<<<<< HEAD
							$updatedName = $input['name'];

							// Use translated table correctly
							$existingName = optional($attribute->translate($locale))->name ?? [];
=======
						$updatedName = $input['name'];

						// Use translated table correctly
						$existingName = optional($attribute->translate($locale))->name ?? [];

						// Only save if changed
						if ($updatedName !== $existingName) {
							if ($locale === 'en') {
								$attribute->name = $updatedName;
							}
>>>>>>> d3338926134c181cdd0a9072cd8e9338ce6a24fe

							// Only save if changed
							if ($updatedName !== $existingName) {
								if ($locale === 'en') {
									$attribute->name = $updatedName;
								}

								if (in_array(config('app.website'), ['UAE', 'UAE_T', 'SA'])) {
									$attribute->translateOrNew($locale)->name_tr = $updatedName;
								}

								$attribute->save();
							}
<<<<<<< HEAD
						

=======

							$attribute->save();
						}
>>>>>>> d3338926134c181cdd0a9072cd8e9338ce6a24fe

					} else {
						$attribute->$field = $input[$field];
					}
				}
			}

			$attribute->updated_by = auth()->id();
			$attribute->save();

			DB::commit();

			return response()->json([
				'success' => true,
				'message' => __("msg_update"),
				'data' => $attribute
			]);
		} catch (\Exception $e) {
			DB::rollBack();

			return response()->json([
				'success' => false,
				'message' => __("err_update"),
				'error' => $e->getMessage()
			], 500);
		}
	}

	/**
	 * @OA\Delete(
	 *     path="/api/attributes/{id}",
	 *     summary="Delete an attribute",
	 *     description="Deletes an attribute.",
	 *     operationId="deleteAttribute",
	 *     tags={"Attributes"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         description="ID of the attribute to delete",
	 *         required=true,
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function destroy($id)
	{
		if (!auth()->user()->can('delete attribute')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}
		$attribute = Attribute::find($id);

		if (!$attribute) {
			return response()->json([
				'success' => false,
				'message' => __("err_exist")
			], 404);
		}

		if ($attribute->type === 'measurement') {
			$attribute->measurementUnits()->detach();
		}

		if ($attribute->type === 'select') {
			foreach ($attribute->attributeValues as $value) {
				if (method_exists($value, 'translations')) {
					$value->translations()->delete();
				}
				$value->delete();
			}
		}

		/* Proceed with deletion */
		if (method_exists($attribute, 'translations')) {
			$attribute->translations()->delete();
		}
		$attribute->delete();

		return response()->json([
			'success' => true,
			'message' => __("msg_dlt")
		], 200);
	}

	/**
	 * @OA\Post(
	 *     path="/api/attributes/export",
	 *     summary="Export product attribute data to Excel",
	 *     tags={"Attributes"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"parent_category_id", "range_from", "range_to"},
	 *             @OA\Property(property="status", type="string", example="all", description="Status (e.g., draft, published)"),
	 *             @OA\Property(property="parent_category_id", type="integer", example=1, description="Parent category ID"),
	 *             @OA\Property(property="brand_id", type="integer", example=1, description="Brand ID"),
	 *             @OA\Property(property="range_from", type="integer", example=1, description="Starting range (must be >=1)"),
	 *             @OA\Property(property="range_to", type="integer", example=50, description="Ending range (must be >= range_from and max 2000 more)")
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function export(Request $request, ExcelRepository $excelRepo)
	{
		if (!auth()->user()->can('export attribute')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}
		/* Validate request data */
		$request->validate([
			'status' => 'required|string|in:all,draft,published',
			'parent_category_id' => 'required|integer|exists:categories,id',
			'brand_id' => 'nullable|integer|exists:ec_brands,id',
			'range_from' => 'required|integer|min:1',
			'range_to' => 'required|integer|gte:range_from|max:' . ($request->range_from + 2000),
		]);

		$parentCategory = Category::findOrFail($request->parent_category_id);

		$categoryAttributes = [];
		if (!$parentCategory->children()->exists()) {
			$categoryAttributeIds = [];
			if ($parentCategory->subCategory && $parentCategory->subCategory->attributes_ids) {
				$raw = $parentCategory->subCategory->attributes_ids;
				if (is_array($raw)) {
					$raw = $raw[0];
				}
				$categoryAttributeIds = array_map('intval', explode(',', $raw));
			}

			$categoryAttributes = Attribute::whereIn('id', $categoryAttributeIds)->pluck('name')->toArray();
		}

		/* Get leaf categories */
		$leafCategories = Category::getLeafCategories($parentCategory);
		$leafCategoryIds = $leafCategories->pluck('id')->toArray();

		/* Fetch products within range */
		$products = Product::query();
		if ($request->status && $request->status != "all") {
			$products->where('status', $request->status);
		}
		$products = $products->whereHas('categories', fn($query) => $query->whereIn('category_id', $leafCategoryIds));
		if ($request->brand_id) {
			$products = $products->whereHas('brand', function ($query) use ($request) {
				$query->where('id', $request->brand_id);
			});
		}
		$products = $products->offset($request->range_from - 1)
		->limit($request->range_to - $request->range_from + 1)
		->orderBy('id', 'asc')
		->get(['id', 'sku', 'name']);

		if ($products->isEmpty()) {
			return response()->json([
				'success' => false,
				'message' => 'No products exist in the associated leaf categories.'
			]);
		}

		/* Fetch unique attributes */
		$uniqueAttributes = $leafCategories
		->flatMap->categoryAllAttributes()
		->unique('id')
		->sortBy('id')
		->reject(fn($attribute) => $attribute->type === 'multiselect')
		->mapWithKeys(function ($attribute) {
			$data = [
				'name' => $attribute->name,
				'type' => $attribute->type,
				'attribute_value' => $attribute->type === 'toggle'
				? ['Yes', 'No']
				: $attribute->attributeValues->pluck('attribute_value')->toArray(),
			];

			if ($attribute->type === 'measurement') {
				$data['measurement_units'] = $attribute->measurementUnits->pluck('name')->toArray();
			}

			return [$attribute->id => $data];
		})
		->toArray();

		$attributeNames = array_map(function ($attr) {
			if ($attr['type'] === 'measurement') {
				return [$attr['name'], $attr['name'] . ' Measurement Unit'];
			}
			return [$attr['name']];
		}, $uniqueAttributes);

		$attributeNames = array_merge(...$attributeNames);

		$header = array_merge(['ID', 'SKU', 'Name'], $attributeNames);

		/* Prepare spreadsheet */
		$spreadsheet = $excelRepo->newSpreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setTitle('Attributes');

		$highlightedAttribute = [];
		foreach ($categoryAttributes as $attribute) {
			$highlightedAttribute[] = $attribute;
			$measurementUnit = $attribute . ' Measurement Unit';
			if (in_array($measurementUnit, $attributeNames)) {
				$highlightedAttribute[] = $measurementUnit;
			}
		}

		/* Set headers */
		$excelRepo->setHeader($sheet, $header, $highlightedAttribute);

		/* Populate data */
		$measurementNameIds = MeasurementUnit::pluck('name', 'id')->toArray();
		$row = 2;
		foreach ($products as $product) {
			$existingAttributes = $product->productAttributes->pluck('attribute_value', 'attribute_id')->toArray();
			$existingMeasuments = $product->productAttributes->whereNotNull('measurement_unit_id')->pluck('measurement_unit_id', 'attribute_id')->toArray();
			$col = 'A';

			/* Set product details */
			$sheet->setCellValue($col++ . $row, $product->id);
			$sheet->setCellValue($col++ . $row, $product->sku);
			$sheet->setCellValue($col++ . $row, $product->name);

			foreach ($uniqueAttributes as $attributeId => $attributeDetail) {
				$existingVal = $existingAttributes[$attributeId] ?? '';
				$cell = $col++ . $row;

				if (!empty($attributeDetail['attribute_value']) && in_array($attributeDetail['type'], ['select', 'toggle'])) {
					$excelRepo->setDropdown($spreadsheet, $sheet, $cell, $attributeDetail['name'], $attributeDetail['attribute_value'], $existingVal);

				} else if ($attributeDetail['type'] === 'measurement') {
					$sheet->setCellValue($cell, $existingVal);

					$existingMeasurementUnitID = $existingMeasuments[$attributeId] ?? '';
					$existingMeasurementValue = $measurementNameIds[$existingMeasurementUnitID] ?? '';

					$unitCell = $col++ . $row;
					$excelRepo->setDropdown(
						$spreadsheet,
						$sheet,
						$unitCell,
						$attributeDetail['name'] . ' Measurement Unit',
						$attributeDetail['measurement_units'],
						$existingMeasurementValue
					);

				} else {
					$sheet->setCellValue($cell, $existingVal);
				}
			}

			$row++;
		}

		$parentCategoryName = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $parentCategory->name);
		$fileName = 'attributes_'.$parentCategoryName.'_' . $request->range_from . '-' . $request->range_to . '_' . now()->format('Y-m-d_H-i-s') . '.xlsx';

		return $excelRepo->downloadFile($fileName, $spreadsheet);
	}

	/**
	 * @OA\Post(
	 *     path="/api/attributes/import",
	 *     summary="Import product attributes from an Excel file",
	 *     tags={"Attributes"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="multipart/form-data",
	 *             @OA\Schema(
	 *                 required={"upload_file"},
	 *                 @OA\Property(property="upload_file", type="string", format="binary", description="xlsx file (.xlsx) max 2MB")
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Imported successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function import(Request $request, ExcelImporterService $excelImporter)
	{
		if (!auth()->user()->can('import attribute')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}

		/* Validate request data */
		$request->validate([
			'upload_file' => 'required|file|mimes:xlsx,xls|max:2048',
		]);

		try {
			$attributeFileFormatArray = [
				'ID'   => 'id',
				'SKU' => 'sku',
				'Name' => 'name',
			];

			$excelImporter->processExcelImport(
				$request->file('upload_file'),
				$attributeFileFormatArray,
				'Product Attribute', /* Module name */
				config('app.website') . '_ATTRIBUTE', /* Job name */
				'Import Product Attributes', /* Batch name */
				ImportProductAttributeJob::class
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
	 * @OA\Post(
	 *     path="/api/attributes/generate-translation",
	 *     summary="Generate or update attribute translation",
	 *     description="This endpoint generates or updates translations for an attribute and its values.",
	 *     tags={"Attributes"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"id", "locale", "name"},
	 *             @OA\Property(property="id", type="integer", example=1, description="ID of the attribute to translate"),
	 *             @OA\Property(property="locale", type="string", example="ar", description="Locale code for translation (e.g. ar)"),
	 *             @OA\Property(property="name", type="string", example="الحجم", description="Translated name of the attribute"),
	 *             @OA\Property(
	 *                 property="attribute_values",
	 *                 type="object",
	 *                 example={"1": "صغير", "2": "متوسط", "3": "كبير"},
	 *                 description="Key-value pairs of attribute value translations (key = attribute_value_id, value = translated text)"
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function generateTranslation(Request $request)
	{
		/* Validate request data */
		$validated = $request->validate([
			'id' => 'required|exists:attributes,id',
			'locale' => 'required|string|in:ar',
			'name' => 'required|string',
			'attribute_values' => 'nullable|array',
			'attribute_values.*' => 'string|nullable',
		]);

		$attribute = Attribute::find($validated['id']);

		DB::beginTransaction();
		try {
			$locale = $validated['locale'];

			/* Update attribute translation */
			$attribute->translateOrNew($locale)->name_tr = $validated['name'];
			$attribute->save();

			/* Update attribute value translations */
			if ($attribute->type === 'select' && !empty($validated['attribute_values'])) {
				foreach ($validated['attribute_values'] as $id => $translatedValue) {
					$attrValue = AttributeValue::find($id);
					if ($attrValue) {
						$attrValue->translateOrNew($locale)->attribute_value_tr = $translatedValue;
						$attrValue->save();
					}
				}
			}

			DB::commit();

			return response()->json([
				'success' => true,
				'message' => __("Translations updated successfully."),
				'data' => $attribute->load('attributeValues'),
			]);
		} catch (\Exception $e) {
			DB::rollBack();

			return response()->json([
				'success' => false,
				'message' => __("err_update"),
				'error' => $e->getMessage(),
			], 500);
		}
	}
}
