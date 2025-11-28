<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\Category;
use App\Models\Attribute;
use App\Models\TransactionLog;
use App\Models\MeasurementUnit;

use App\Services\ExcelImporterService;
use App\Repository\ExcelRepository;

use App\Jobs\ImportProductAttributeJob;

class ProductAttributeController extends BaseController
{
	/**
	 * @OA\Post(
	 *     path="/api/attributes/export",
	 *     summary="Export product attribute data to Excel",
	 *     tags={"Product Attributes"},
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
	 *     tags={"Product Attributes"},
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
	 * @OA\Get(
	 *     path="/api/products/{productId}/product-category-attribute-groups",
	 *     summary="Get product category attribute groups list",
	 *     description="Retrieve attribute groups of the latest category for a given product.",
	 *     tags={"Product Attributes"},
	 *     @OA\Parameter(
	 *         name="productId",
	 *         in="path",
	 *         description="Product ID",
	 *         required=true,
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function productCategoryAttributeGroups($productId)
	{
		$product = Product::find($productId);
		if (!$product) {
			return response()->json([
				'success' => false,
				'message' => 'Product does not exist.'
			], 404);
		}

		$category = $product->latestChildCategory();
		if (!$category) {
			return response()->json([
				'success' => false,
				'message' => 'No category found for this product.'
			], 404);
		}

		$productAttributes = $product->productAttributes->pluck('attribute_value', 'attribute_id');
		$productAttributeMeasurement = $product->productAttributes->pluck('measurement_unit_id', 'attribute_id');

		$attributeGroup = $category->categoryAttributeGroups()
		->with(['groupsAttributes.attributeValues', 'groupsAttributes.measurementUnits'])
		->get()
		->map(function ($group) use ($productAttributes, $productAttributeMeasurement) {
			return [
				'id' => $group->id,
				'name' => $group->name,
				'group_attributes' => $group->groupsAttributes->map(function ($attribute) use ($productAttributes, $productAttributeMeasurement) {
					$data = [
						'id' => $attribute->id,
						'name' => $attribute->name,
						'code' => $attribute->code,
						'type' => $attribute->type,
						'validations' => json_decode($attribute->validations, true),
						'currentValue' => $productAttributes[$attribute->id] ?? null,
					];

					if ($attribute->type === 'select') {
						$data['attributeValues'] = $attribute->attributeValues->pluck('attribute_value')->values()->all();
					}

					if ($attribute->type === 'measurement') {
						$data['attributeMeasurement'] = $attribute->measurementUnits->pluck('name', 'id')->all();
						$data['currentMeasurementId'] = $productAttributeMeasurement[$attribute->id] ?? null;
					}

					return $data;
				})->toArray(),
			];
		});


		return response()->json([
			'success' => true,
			'message' => 'Product category attribute groups',
			'data' => $attributeGroup
		]);
	}

	/**
	 * @OA\Get(
	 *     path="/api/products-attributes/{productId}",
	 *     summary="Get product's category attribute groups with current values",
	 *     description="Retrieves all attribute groups from the product's category along with the product's current attribute values",
	 *     tags={"Product Attributes"},
	 *     @OA\Parameter(
	 *         name="productId",
	 *         in="path",
	 *         description="Product ID",
	 *         required=true,
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function getProductCategoryAttributes($productId)
	{
		$product = Product::find($productId);
		if (!$product) {
			return response()->json([
				'success' => false,
				'message' => 'Product does not exist.'
			], 404);
		}

		$productAttributes = ProductAttribute::with([
			'attributeDetails.attributeGroup',
			'attributeDetails.attributeValues',
			'attributeDetails.measurementUnits',
			'measurementUnit'
		])
		->where('product_id', $productId)
		->get();

		/* Group by attribute group */
		$groupedAttributes = $productAttributes->groupBy(function ($productAttribute) {
			return $productAttribute->attributeDetails->attributeGroup->id ?? null;
		})->filter(function ($group, $key) {
			return !is_null($key); /* Remove attributes without groups */
		})->map(function ($attributes, $groupId) {
			$firstAttribute = $attributes->first();
			$group = $firstAttribute->attributeDetails->attributeGroup;

			return [
				'group_name' => $group->name,
				'attributes' => $attributes->map(function ($productAttribute) {
					$attribute = $productAttribute->attributeDetails;

					$data = [
						'id' => $attribute->id,
						'type' => $attribute->type,
						'attribute_translation' => $attribute->translations->mapWithKeys(function ($translation) {
							return [$translation->locale => $translation->name_tr];
						})->all(),
						'currentValueTranslations' => $productAttribute->translations->mapWithKeys(function ($translation) {
							return [$translation->locale => $translation->attribute_value_tr];
						})->all(),
					];

					if ($attribute->type === 'select') {
						/* Get all attribute values with their translations */
						$allValues = $attribute->attributeValues;

						/* Build translations structure grouped by locale */
						$translations = [];

						/* Add other locale translations */
						foreach ($allValues as $attrValue) {
							foreach ($attrValue->translations as $translation) {
								if (!isset($translations[$translation->locale])) {
									$translations[$translation->locale] = [];
								}
								$translations[$translation->locale][] = $translation->attribute_value_tr;
							}
						}

						$data['attributeValues'] = [
							'translations' => $translations
						];
					}

					if ($attribute->type === 'measurement') {
						$data['currentMeasurementId'] = $productAttribute->measurement_unit_id;

						/* Find the current measurement unit */
						$currentMeasurement = $attribute->measurementUnits
						->firstWhere('id', $productAttribute->measurement_unit_id);

						if ($currentMeasurement) {
							$translations = [];

							/* Add other locale translations */
							foreach ($currentMeasurement->translations as $translation) {
								$translations[$translation->locale] = $translation->name_tr;
							}

							$data['currentMeasurementTranslation'] = $translations;
						} else {
							$data['currentMeasurementTranslation'] = [];
						}
					}

					return $data;
				})->values()->all(),
			];
		})->values()->all();

		return response()->json([
			'success' => true,
			'message' => 'Product attributes with translations',
			'data' => $groupedAttributes
		]);
	}
}
