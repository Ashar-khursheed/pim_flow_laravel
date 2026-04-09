<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Support\Facades\Storage;
// use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

use App\Repository\ExcelRepository;

class ProductExportController extends BaseController
{
	/**
	 * @OA\Post(
	 *     path="/api/products/export",
	 *     summary="Export product data to Excel",
	 *     tags={"Products"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"type", "relational_id", "range_from", "range_to"},
	 *             @OA\Property(property="status", type="string", example="all", description="Status"),
	 *             @OA\Property(property="type", type="string", example="Category", description="Filter type (e.g., Category, Brand)"),
	 *             @OA\Property(property="relational_id", type="integer", example=1, description="ID based on selected type (e.g., Category ID)"),
	 *             @OA\Property(property="range_from", type="integer", example=1, description="Starting product index (must be >= 1)"),
	 *             @OA\Property(property="range_to", type="integer", example=1000, description="Ending product index (max range allowed: 1000 products)"),
	 *             @OA\Property(property="include_descriptive_attributes", type="boolean", example=true, description="Include descriptive attributes (URL, material, size, color) in export"),
	 *             @OA\Property(
	 *                 property="selected_fields",
	 *                 type="array",
	 *                 description="Optional list of specific fields to export. If omitted, all fields will be exported.",
	 *                 @OA\Items(type="string", example="name")
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function export(Request $request, ExcelRepository $excelRepo)
	{
		if (!auth()->user()->can('export product')) {
			return response()->json([
				'success' => false,
				'message' => "You don't have permission to access this module.",
			]);
		}

		$userRole = auth()->user()->getRoleNames()->first() ?? null;

		/* Validate request data */
		$request->validate([
			'status' => 'required|string|in:all,draft,published',
			'type' => 'required|string|in:Brand,Category,Vendor',
			'relational_id' => 'required|integer',
			'range_from' => 'required|integer|min:1',
			'range_to' => 'required|integer|gte:range_from|max:' . ($request->range_from + 1000),
			'include_descriptive_attributes' => 'nullable|boolean',
			'selected_fields' => 'nullable|array',
			'selected_fields.*' => 'string',
		]);

		$baseFields = Schema::getColumnListing('ec_products');

		/* Default empty if not set */
		$selectedFields = $request->selected_fields ?? [];

		/* If role is in 'Content Writing Manager', 'Content Writer', enforce valid fields */
		if (!empty($userRole) && in_array($userRole, ['Content Writing Manager', 'Content Writer'])) {
			$validFields = [
				"id", "sku", "name", "brand", "categories",
				"description", "benefits_features", "faq_section"
			];

			/* If no selectedFields given, use all valid fields */
			if (empty($selectedFields)) {
				$selectedFields = $validFields;
			} else {
				$selectedFields = array_intersect($validFields, $selectedFields);
			}
		}

		/* If role is in 'Content Writing Manager', 'Content Writer', enforce valid fields */
		if (!empty($userRole) && in_array($userRole, ['Ecommerce Manager', 'Ecommerce Specialist'])) {
			$validFields = [
				"id", "name", "sku", "brand", "categories", "header_map2"
			];

			/* If no selectedFields given, use all valid fields */
			if (empty($selectedFields)) {
				$selectedFields = $validFields;
			} else {
				$selectedFields = array_intersect($validFields, $selectedFields);
			}
		}

		$includeDescriptiveAttributes = $request->boolean('include_descriptive_attributes');

		$query = Product::with([
			'categories:id,name,parent_id',
			'brand:id,name',
			'vendors:id,name',
			'tags:id,name',
			'faqs:id,relational_type,relational_id,question,answer',
			'latestChildCategoryRelation:id,name',
		])
		->when($includeDescriptiveAttributes, function($query) {
			$query->with([
				'descriptiveAttributes:id,product_id,attribute_id,attribute_value',
				'descriptiveAttributes.attributeDetails:id,name',
			]);
		})
		->when($request->status != "all", function($query) use ($request) {
			$query->where('status', $request->status);
		});


		if ($request->type == "Brand") {
			$query->where('brand_id', $request->relational_id);
		} elseif ($request->type == "Vendor") {
			$query->whereHas('vendors', function ($q) use ($request) {
				$q->where('vendor_id', $request->relational_id);
			});
		} elseif ($request->type == "Category") {
			$category = Category::find($request->relational_id);
			$leafCategoryIds = $category->getLeafCategories()->pluck('id')->toArray();
			$query->whereHas('categories', function ($q) use ($leafCategoryIds) {
				$q->whereIn('category_id', $leafCategoryIds);
			});
		}

		$products = $query->offset($request->range_from - 1)
		->limit($request->range_to - $request->range_from + 1)
		->orderBy('id', 'asc')
		->get();

		/* Return message if products empty */
		if ($products->isEmpty()) {
			return response()->json([
				"success" => false,
				"message" => "No products found for the given criteria.",
			]);
		}

		/* Define pretty headers that match exactly what you requested */
		$headerMap1 = product_constants('HEADER_MAP1');
		$descriptionColumns = product_constants('DESCRIPTION_COLUMNS');
		$benifitsFeaturesColumns = product_constants('BENIFITS_FEATURES_COLUMNS');
		$faqColumns = product_constants('FAQ_COLUMNS');
		$headerMap2 = product_constants('HEADER_MAP2');
		$descriptiveSection = product_constants('DESCRIPTIVE_SECTION');
		// $discountSection = product_constants('DISCOUNT_SECTION');

		/* Initialize header map */
		$headerMap = [];

		/* Determine if full header maps should be used */
		$includeAll = empty($selectedFields);
		$includeHeaderMap1 = $includeAll || in_array('header_map1', $selectedFields);
		$includeHeaderMap2 = $includeAll || in_array('header_map2', $selectedFields);

		/* Apply filters based on $selectedFields */
		$filteredHeaderMap1 = $includeHeaderMap1 ? $headerMap1 : array_intersect_key($headerMap1, array_flip($selectedFields));
		$filteredHeaderMap2 = $includeHeaderMap2 ? $headerMap2 : array_intersect_key($headerMap2, array_flip($selectedFields));

		/* Merge primary headers */
		$headerMap = array_merge($headerMap, $filteredHeaderMap1);

		/* Include description if requested or all fields */
		if ($includeAll || in_array('description', $selectedFields)) {
			$headerMap = array_merge($headerMap, $descriptionColumns);
		}

		/* Include benefits_features if requested or all fields */
		if ($includeAll || in_array('benefits_features', $selectedFields)) {
			$headerMap = array_merge($headerMap, $benifitsFeaturesColumns);
		}

		/* Include FAQ section */
		if ($includeAll || in_array('faq_section', $selectedFields)) {
			$headerMap = array_merge($headerMap, $faqColumns);
		}

		/* Merge secondary headers */
		$headerMap = array_merge($headerMap, $filteredHeaderMap2);

		/* Transform descriptive_attributes to key-value format */
		if ($includeDescriptiveAttributes) {
			$headerMap = array_merge($headerMap, $descriptiveSection);
		}

		/* Discount section */
		// if ($includeAll || in_array('discount_section', $selectedFields)) {
		// 	$headerMap = array_merge($headerMap, $discountSection);
		// }

		$allFields = array_keys($headerMap);

		$spreadsheet = $excelRepo->newSpreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setTitle('Products');

		$headers = [];
		foreach ($allFields as $field) {
			$headers[] = $headerMap[$field] ?? $field;
		}
		$excelRepo->setHeader($sheet, $headers);

		$stockMap = ['in_stock' => 1, 'Out of Stock' => 2, 'Pre Order' => 3];
		$statusMap = ['published' => 1, 'draft' => 2, 'pending' => 3];
		$skipFields = [
			// 'discount1', 'start_date1', 'end_date1',
			// 'buying_quantity2', 'discount2', 'start_date2', 'end_date2',
			// 'buying_quantity3', 'discount3', 'start_date3', 'end_date3',
			'feature1', 'benefit2', 'feature2', 'benefit3', 'feature3',
			'description2', 'description3', 'description4',
			'benefit4', 'feature4', 'benefit5', 'feature5',
			'benefit6', 'feature6', 'benefit7', 'feature7',
			'benefit8', 'feature8', 'benefit9', 'feature9',
			'benefit10', 'feature10',
			"faq_answer1", "faq_question2", "faq_answer2", "faq_question3", "faq_answer3", "faq_question4", "faq_answer4",
			"faq_question5", "faq_answer5", "faq_question6", "faq_answer6", "faq_question7", "faq_answer7", "faq_question8",
			"faq_answer8", "faq_question9", "faq_answer9", "faq_question10", "faq_answer10",
			"material", "size"
			// "meta_description"
		];

		$rowIndex = 2;
		foreach ($products as $product) {
			$row = [];

			$descriptiveAttributes = [];
			if ($includeDescriptiveAttributes && $product->descriptiveAttributes) {
				$descriptiveAttributes = $product->descriptiveAttributes->pluck('attribute_value', 'attributeDetails.name')->toArray();
			}


			$fullURL = config('app.url') . '/' . $product->parent_category_url() . '/' . $product->category_url() . '/' . ($product->seoProductUrl->url ?? "");

			$benefits = is_array($product->benefits_features) ? $product->benefits_features : json_decode($product->benefits_features, true) ?? [];
			$descriptionData = $product->description;
			if (!is_null($descriptionData) && is_string($descriptionData) && json_validate($descriptionData)) {
				$descriptions = json_decode($descriptionData, true);
			} elseif (is_array($descriptionData)) {
				$descriptions = $descriptionData;
			} elseif (!is_null($descriptionData)) {
				$descriptions = explode('|', $descriptionData);
			} else {
				$descriptions = [];
			}

			$faqs = $product->faqs->take(10);
			// $discounts = $product->discounts->take(3);

			foreach ($allFields as $field) {
				if (in_array($field, $skipFields)) continue;

				switch ($field) {
					case 'categories':
					$row[] = $product->latestChildCategoryRelation->first()->name ?? '';
					break;

					case 'stock_status':
					$row[] = $stockMap[$product->stock_status] ?? '';
					break;

					case 'status':
					$row[] = $statusMap[$product->status] ?? 2;
					break;

					case 'is_featured':
					$row[] = $product->$field ? 1 : 0;
					break;

					case 'tags':
					$row[] = $product->tags->pluck('name')->implode(',') ?? '';
					break;

					case 'brand':
					$brandData = is_array($product->brand) ? $product->brand :
					(json_decode($product->brand, true) ?: (is_object($product->brand) ? $product->brand->toArray() : []));
					$row[] = $brandData['name'] ?? '';
					break;

					case 'images':
					case 'upload_video':
					$data = $field == 'images' ? $product->images : $product->upload_video;
					$array = is_array($data) ? $data : (json_decode($data, true) ?? []);
					$cleanUrls = array_filter(array_map(fn($val) => is_string($val) ? str_replace('\/', '/', trim($val, '"')) : '', $array));
					$row[] = implode(',', $cleanUrls);
					break;

					// case 'frequently_bought_together':
					// $fbtArray = json_decode($product->frequently_bought_together, true) ?? [];
					// $row[] = implode(',', array_column($fbtArray, 'value'));
					// break;

					case 'url':
					$row[] = $fullURL;
					break;

					// case 'buying_quantity1':
					// for ($i = 0; $i < 3; $i++) {
					// 	$discount = $discounts[$i] ?? null;
					// 	$row[] = $discount->product_quantity ?? '';
					// 	$row[] = $discount->value ?? '';
					// 	$row[] = $discount->start_date ?? '';
					// 	$row[] = $discount->end_date ?? '';
					// }
					// break;

					case 'description1':
					for ($i = 0; $i < 4; $i++) {
						$row[] = $descriptions[$i] ?? '';
					}
					break;

					case 'benefit1':
					for ($i = 0; $i < 10; $i++) {
						$row[] = $benefits[$i]['benefit'] ?? '';
						$row[] = $benefits[$i]['feature'] ?? '';
					}
					break;

					case 'faq_question1':
					for ($i = 0; $i < 10; $i++) {
						$faq = $faqs[$i] ?? null;
						$row[] = $faq->question ?? '';
						$row[] = $faq->answer ?? '';
					}
					break;

					case 'color':
					$row[] = $descriptiveAttributes['Color'] ?? '';
					$row[] = $descriptiveAttributes['Material'] ?? '';
					$row[] = $descriptiveAttributes['Size'] ?? '';
					break;

					default:
					$row[] = $product->$field ?? '';
				}
			}
			$excelRepo->writeRow($sheet, $row, $rowIndex++);
		}

		$fileName = 'products_' . $request->range_from . '-' . $request->range_to . '_' . now()->format('Y-m-d_H-i-s') . '.xlsx';

		return $excelRepo->downloadFile($fileName, $spreadsheet);
	}
}