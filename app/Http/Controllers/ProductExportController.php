<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductExportController extends Controller
{
	/**
	 * @OA\Post(
	 *     path="/api/products/export",
	 *     summary="Export products to CSV",
	 *     tags={"Products"},
	 *     @OA\Parameter(
	 *         name="type",
	 *         in="query",
	 *         description="Type of the relational entity",
	 *         required=true,
	 *         @OA\Schema(
	 *             type="string",
	 *             enum={"Brand", "Category", "Store"}
	 *         )
	 *     ),
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"relational_id", "range_from", "range_to"},
	 *             @OA\Property(property="relational_id", type="integer", example=1, description="Relational ID"),
	 *             @OA\Property(property="range_from", type="integer", example=1, description="Starting range (must be >=1)"),
	 *             @OA\Property(property="range_to", type="integer", example=50, description="Ending range (must be >= range_from and max 2000 more)"),
	 *             @OA\Property(
	 *                 property="selected_fields",
	 *                 type="array",
	 *                 description="Optional list of fields to export. If blank, all fields will be exported.",
	 *                 @OA\Items(type="string", example="name")
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function export(Request $request)
	{
        if (!auth()->user()->can('export product')) {
            return response()->json([
                'success' => false,
                'message' => "You don't have permission to access this module.",
            ]);
        }
		/* Validate request data */
		$request->validate([
			'type' => 'required|string|in:Brand,Category,Store',
			'relational_id' => 'required|integer',
			'range_from' => 'required|integer|min:1',
			'range_to' => 'required|integer|gte:range_from|max:' . ($request->range_from + 2000),
			'selected_fields' => 'nullable|array',
			'selected_fields.*' => 'string',
		]);

		$baseFields = Schema::getColumnListing('ec_products');

		/* Default empty if not set */
		$selectedFields = $request->selected_fields ?? [];

		$userRole = auth()->user()->getRoleNames()->first() ?? null;

		/* If role is in 'Content Writing Manager', 'Content Writer', enforce valid fields */
		if (!empty($userRole) && in_array($userRole, ['Content Writing Manager', 'Content Writer'])) {
			$validFields = [
				"id", "sku", "name", "brand", "categories",
				"description", "benefits_features", "faq_section", "seo_section"
			];

			/* If no selectedFields given, use all valid fields */
			if (empty($selectedFields)) {
				$selectedFields = $validFields;
			} else {
				$selectedFields = array_intersect($validFields, $selectedFields);
			}
		}

		/* Final filtering with DB columns */
		if (!empty($selectedFields)) {
			$fieldsToSelect = array_intersect($selectedFields, $baseFields);
			$fieldsToSelect = empty($fieldsToSelect) ? ['*'] : array_values($fieldsToSelect);
		} else {
			$fieldsToSelect = ['*'];
		}

		$query = Product::with(['categories:id,name', 'brand:id,name', 'store:id,name', 'tags', 'seoMetaData']);

		/* Apply relational filters */
		if ($request->type == "Brand") {
			$query->where('brand_id', $request->relational_id);
		} elseif ($request->type == "Store") {
			$query->where('store_id', $request->relational_id);
		} elseif ($request->type == "Category") {
			$category = Category::find($request->relational_id);
			$leafCategories = Category::getLeafCategories($category);
			$leafCategoryIds = $leafCategories->pluck('id')->toArray();
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
		$headerMap1 = [
			"id" => "Id",
			"url" => "URL",
			"name" => "Name",
			"sku" => "SKU",
			"brand" => "Brand",
			"categories" => "Categories",
			// "content" => "Content",
		];

		$descriptionColumns = [
			"description1" => "Description1",
			"description2" => "Description2",
			"description3" => "Description3",
			"description4" => "Description4",
		];

		$benifitsFeaturesColumns = [
			"benefit1" => "Benefit1",
			"feature1" => "Feature1",
			"benefit2" => "Benefit2",
			"feature2" => "Feature2",
			"benefit3" => "Benefit3",
			"feature3" => "Feature3",
			"benefit4" => "Benefit4",
			"feature4" => "Feature4",
			"benefit5" => "Benefit5",
			"feature5" => "Feature5",
			"benefit6" => "Benefit6",
			"feature6" => "Feature6",
			"benefit7" => "Benefit7",
			"feature7" => "Feature7",
			"benefit8" => "Benefit8",
			"feature8" => "Feature8",
			"benefit9" => "Benefit9",
			"feature9" => "Feature9",
			"benefit10" => "Benefit10",
			"feature10" => "Feature10",
		];

		$faqColumns = [
			"faq_question1" => "FAQ Question1",
			"faq_answer1" => "FAQ Answer1",
			"faq_question2" => "FAQ Question2",
			"faq_answer2" => "FAQ Answer2",
			"faq_question3" => "FAQ Question3",
			"faq_answer3" => "FAQ Answer3",
			"faq_question4" => "FAQ Question4",
			"faq_answer4" => "FAQ Answer4",
			"faq_question5" => "FAQ Question5",
			"faq_answer5" => "FAQ Answer5",
			"faq_question6" => "FAQ Question6",
			"faq_answer6" => "FAQ Answer6",
			"faq_question7" => "FAQ Question7",
			"faq_answer7" => "FAQ Answer7",
			"faq_question8" => "FAQ Question8",
			"faq_answer8" => "FAQ Answer8",
			"faq_question9" => "FAQ Question9",
			"faq_answer9" => "FAQ Answer9",
			"faq_question10" => "FAQ Question10",
			"faq_answer10" => "FAQ Answer10",
		];

		$headerMap2 = [
			// "description" => "Description",
			"warranty_information" => "Warranty Information",
			"vendor" => "Vendor",
			"tags" => "Tags",
			"stock_status" => "Stock Status",
			"with_storehouse_management" => "With Storehouse Management",
			"quantity" => "Quantity",
			"cost_per_item" => "Cost Per Item",
			"unit_of_measurement" => "Unit of Measurement",
			"price" => "Price",
			"sale_price" => "Sale Price",
			"start_date_sale_price" => "Start Date Sale Price",
			"end_date_sale_price" => "End Date Sale Price",
			"minimum_order_quantity" => "Minimum Order Quantity",
			"box_quantity" => "Box Quantity",
			"delivery_days" => "Delivery Days",
			"variant_requires_shipping" => "Variant Requires Shipping",
			"images" => "Images",
			"upload_video" => "Upload Video",
			"barcode" => "Barcode (ISBN, UPC, GTIN, etc.)",
			"refund_policy" => "Refund Policy",
			"status" => "Status",
			"google_shopping_category" => "Google Shopping Category",
			"google_shopping_mpn" => "Google Shopping Mpn",
			"is_featured" => "Is Featured",
			"weight_option" => "Weight Option",
			"weight" => "Weight",
			"dimension_option" => "Dimension Option",
			"length" => "Length",
			"width" => "Width",
			"height" => "Height",
			"depth" => "Depth",
			"shipping_weight_option" => "Shipping Weight Option",
			"shipping_weight" => "Shipping Weight",
			"shipping_dimension_option" => "Shipping Dimension Option",
			"shipping_width" => "Shipping Width",
			"shipping_depth" => "Shipping Depth",
			"shipping_height" => "Shipping Height",
			"shipping_length" => "Shipping Length",
			"frequently_bought_together" => "Frequently Bought Together",
			"compare_products" => "Compare Products",
			"variant_1_title" => "Variant 1 Title",
			"variant_1_value" => "Variant 1 Value",
			"variant_1_products" => "Variant 1 Products",
			"variant_2_title" => "Variant 2 Title",
			"variant_2_value" => "Variant 2 Value",
			"variant_2_products" => "Variant 2 Products",
			"variant_3_title" => "Variant 3 Title",
			"variant_3_value" => "Variant 3 Value",
			"variant_3_products" => "Variant 3 Products",
			"variant_color_title" => "Variant Color Title",
			"variant_color_value" => "Variant Color Value",
			"variant_color_products" => "Variant Color Products",
		];

		$seoSection = [
			"meta_title" => "Meta Title",
			"meta_description" => "Meta Description",
		];

		$discountSection = [
			"buying_quantity1" => "Buying Quantity1",
			"discount1" => "Discount1",
			"start_date1" => "Start Date1",
			"end_date1" => "End Date1",
			"buying_quantity2" => "Buying Quantity2",
			"discount2" => "Discount2",
			"start_date2" => "Start Date2",
			"end_date2" => "End Date2",
			"buying_quantity3" => "Buying Quantity3",
			"discount3" => "Discount3",
			"start_date3" => "Start Date3",
			"end_date3" => "End Date3",
		];

		$translationSection = [
			"name_ar" => "Name (AR)",
			"description_ar" => "Description (AR)",
			"content_ar" => "Content (AR)",
			"warranty_information_ar" => "Warranty Information (AR)"
		];

		/* Initialize header map */
		$headerMap = [];

		/* Helper to decide if a section should be included */
		$includeSection = function ($key) use ($selectedFields) {
			return empty($selectedFields) || in_array($key, $selectedFields);
		};

		/* Always filter headerMap1 and headerMap2 based on selected fields if provided */
		$filteredHeaderMap1 = empty($selectedFields) ? $headerMap1 : array_intersect_key($headerMap1, array_flip($selectedFields));
		$filteredHeaderMap2 = empty($selectedFields) ? $headerMap2 : array_intersect_key($headerMap2, array_flip($selectedFields));

		/* Start building final header map */
		$headerMap = array_merge($headerMap, $filteredHeaderMap1);

		/* Include description if requested or blank */
		if ($includeSection('description')) {
			$headerMap = array_merge($headerMap, $descriptionColumns);
		}

		/* Include benefits_features if requested or blank */
		if ($includeSection('benefits_features')) {
			$headerMap = array_merge($headerMap, $benifitsFeaturesColumns);
		}

		/* Include benefits_features if requested or blank */
		if ($includeSection('faq_section')) {
			$headerMap = array_merge($headerMap, $faqColumns);
		}

		/* Add remaining fields */
		$headerMap = array_merge($headerMap, $filteredHeaderMap2);

		if ($includeSection('seo_section')) {
			$headerMap = array_merge($headerMap, $seoSection);
		}

		if ($includeSection('discount_section')) {
			$headerMap = array_merge($headerMap, $discountSection);
		}

		if ($includeSection('translation_section')) {
			$headerMap = array_merge($headerMap, $translationSection);
		}

		$allFields = array_keys($headerMap);

	// 	/* CSV response create karna */
	// 	$response = new StreamedResponse(function () use ($products, $allFields, $headerMap) {
	// 		$handle = fopen('php://output', 'w');

	// 		/* Write headers with proper capitalization */
	// 		$headers = [];
	// 		foreach ($allFields as $field) {
	// 			$headers[] = $headerMap[$field] ?? $field;
	// 		}
	// 		fputcsv($handle, $headers);

	// 		foreach ($products as $product) {
	// 			$row = [];
	// 			foreach ($allFields as $field) {
	// 				$skipFields = [
	// 					'discount1', 'start_date1', 'end_date1',
	// 					'buying_quantity2', 'discount2', 'start_date2', 'end_date2',
	// 					'buying_quantity3', 'discount3', 'start_date3', 'end_date3',
	// 					'feature1', 'benefit2', 'feature2', 'benefit3', 'feature3',
	// 					'description2', 'description3', 'description4',
	// 					'benefit4', 'feature4', 'benefit5', 'feature5',
	// 					'benefit6', 'feature6', 'benefit7', 'feature7',
	// 					'benefit8', 'feature8', 'benefit9', 'feature9',
	// 					'benefit10', 'feature10',
	// 					"faq_answer1", "faq_question2", "faq_answer2", "faq_question3", "faq_answer3", "faq_question4", "faq_answer4",
	// 					"faq_question5", "faq_answer5", "faq_question6", "faq_answer6", "faq_question7", "faq_answer7", "faq_question8",
	// 					"faq_answer8", "faq_question9", "faq_answer9", "faq_question10", "faq_answer10",
	// 					"meta_description",
	// 					'description_ar', 'content_ar', 'warranty_information_ar'
	// 				];

	// 				if (in_array($field, $skipFields)) {
	// 					continue; /* skip this field entirely */
	// 				}
	// 				/* Format special sfields */
	// 				switch ($field) {
	// 					case 'categories':
	// 					$lastCategory = $product->latestChildCategory() ? $product->latestChildCategory()->name ?? '' : '';
	// 					$row[] = $lastCategory;
	// 					break;

	// 					case 'stock_status':
	// 					$stockMap = ['in_stock' => 1, 'Out of Stock' => 2, 'Pre Order' => 3];
	// 					$row[] = $stockMap[$product->stock_status] ?? '';
	// 					break;

	// 					case 'status':
	// 					$statusMap = ['published' => 1, 'draft' => 2, 'pending' => 3];
	// 					$row[] = $statusMap[$product->status] ?? 2; /* Default to 'Draft' (2) if not found */
	// 					break;

	// 					case 'unit_of_measurement':
	// 					$unitMap = ['Each' => 1, 'Dozen' => 2, 'Box' => 3, 'Case' => 4];
	// 					$row[] = $unitMap[$product->unit_of_measurement] ?? '';
	// 					break;

	// 					case 'with_storehouse_management':
	// 					$row[] = $product->with_storehouse_management ? 1 : 0;
	// 					break;

	// 					case 'variant_requires_shipping':
	// 					$row[] = $product->variant_requires_shipping ? 1 : 0;
	// 					break;

	// 					case 'refund_policy':
	// 					$refundMap = ['Non-Refundable' => 1, '15 Days Refund' => 2, '90 Days Refund' => 3];
	// 					$row[] = $refundMap[$product->refund_policy] ?? '';
	// 					break;

	// 					case 'is_featured':
	// 					$row[] = $product->is_featured ? 1 : 0;
	// 					break;

	// 					case 'weight_option':
	// 					case 'shipping_weight_option':
	// 					$weightValidOptions = ['lbs', 'kg', 'g'];
	// 					$row[] = in_array($product->$field, $weightValidOptions) ? $product->$field : '';
	// 					break;

	// 					case 'dimension_option':
	// 					case 'shipping_dimension_option':
	// 					$dimensionValidOptions = ['inch', 'cm', 'mm'];
	// 					$row[] = in_array($product->$field, $dimensionValidOptions) ? $product->$field : '';
	// 					break;

	// 					case 'tags':
	// 					$row[] = $product->tags ? implode(',', $product->tags->pluck('name')->toArray()) : '';
	// 					break;

	// 					case 'brand':
	// 					/* Extract just the brand name from the object */
	// 					if (is_string($product->brand) && json_decode($product->brand)) {
	// 						$brandData = json_decode($product->brand, true);
	// 						$row[] = $brandData['name'] ?? '';
	// 					} elseif (is_object($product->brand) || is_array($product->brand)) {
	// 						$brandData = is_array($product->brand) ? $product->brand : $product->brand->toArray();
	// 						$row[] = $brandData['name'] ?? '';
	// 					} else {
	// 						$row[] = $product->brand ?? '';
	// 					}
	// 					break;

	// 					case 'vendor':
	// 					$row[] = $product->store->name ?? '';
	// 					break;

	// 					case 'images':
	// 					/* Format images as clean URLs */
	// 					$imageData = $product->images;
	// 					if (is_string($imageData) && json_decode($imageData)) {
	// 						$imageArray = json_decode($imageData, true);
	// 						$cleanUrls = [];
	// 						foreach ($imageArray as $img) {
	// 							if (is_string($img)) {
	// 								$cleanUrls[] = str_replace('\/', '/', trim($img, '"'));
	// 							}
	// 						}
	// 						$row[] = implode(',', $cleanUrls);
	// 					} else {
	// 						$row[] = is_string($imageData) ? $imageData : '';
	// 					}
	// 					break;

	// 					case 'upload_video':
	// 					/* Format videos as clean URLs */
	// 					$videoData = $product->upload_video;
	// 					if (is_string($videoData) && json_decode($videoData)) {
	// 						$videoArray = json_decode($videoData, true);
	// 						$cleanUrls = [];
	// 						foreach ($videoArray as $video) {
	// 							if (is_string($video)) {
	// 								$cleanUrls[] = str_replace('\/', '/', trim($video, '"'));
	// 							}
	// 						}
	// 						$row[] = implode(',', $cleanUrls);
	// 					} else {
	// 						$row[] = is_string($videoData) ? $videoData : '';
	// 					}
	// 					break;

	// 					case 'frequently_bought_together':
	// 					/* Format as comma-separated values */
	// 					$fbtData = $product->frequently_bought_together;
	// 					if (is_string($fbtData) && json_decode($fbtData)) {
	// 						$fbtArray = json_decode($fbtData, true);
	// 						$values = array_map(function($item) {
	// 							return $item['value'] ?? '';
	// 						}, $fbtArray);
	// 						$row[] = implode(',', $values);
	// 					} else {
	// 						$row[] = is_string($fbtData) ? $fbtData : '';
	// 					}
	// 					break;

	// 					case 'compare_products':
	// 					/* Ensure it's an array and format as comma-separated IDs */
	// 					$compareData = is_string($product->compare_products) ? json_decode($product->compare_products, true) : $product->compare_products;
	// 					if (is_array($compareData)) {
	// 						$row[] = implode(',', $compareData); /* Ensure IDs are separated properly */
	// 					} else {
	// 						$row[] = '';
	// 					}
	// 					break;

	// 					case 'url':
	// 					$row[] = $product->slug ? 'https://thehorecastore.co/products/' . $product->slug->key : '';
	// 					break;

	// 					case 'buying_quantity1':
	// 					$discounts = $product->discounts->take(3); /* Get up to 3 discounts */

	// 					for ($i = 0; $i < 3; $i++) {
	// 						$discount = $discounts[$i] ?? null;
	// 						$row[] = $discount->product_quantity ?? '';
	// 						$row[] = $discount->value ?? '';
	// 						$row[] = $discount->start_date ?? '';
	// 						$row[] = $discount->end_date ?? '';
	// 					}
	// 					break;

	// 					case 'description1':
	// 					$descriptions = json_validate($product->description) ? json_decode($product->description, true) : (is_array($product->description) ? $product->description : explode('|', $product->description));

	// 					for ($i = 0; $i < 4; $i++) {
	// 						$row[] = $descriptions[$i] ?? '';
	// 					}
	// 					break;

	// 					case 'benefit1':
	// 					$benefits = is_array($product->benefits_features) ? $product->benefits_features : json_decode($product->benefits_features, true);

	// 					for ($i = 0; $i < 10; $i++) {
	// 						$row[] = $benefits[$i]['benefit'] ?? '';
	// 						$row[] = $benefits[$i]['feature'] ?? '';
	// 					}
	// 					break;

	// 					case 'faq_question1':
	// 					$faqs = $product->faqs->take(10); /* Get up to 3 discounts */

	// 					for ($i = 0; $i < 10; $i++) {
	// 						$faq = $faqs[$i] ?? null;
	// 						$row[] = $faq->question ?? '';
	// 						$row[] = $faq->answer ?? '';
	// 					}
	// 					break;

	// 					case 'meta_title':
	// 					$row[] = $product->seoManagement->meta_title ?? '';
	// 					$row[] = $product->seoManagement->meta_description ?? '';
	// 					break;

	// 					$benefits = is_array($product->benefits_features) ? $product->benefits_features : json_decode($product->benefits_features, true);
	// 					for ($i = 0; $i < 10; $i++) {
	// 						$row[] = $benefits[$i]['benefit'] ?? '';
	// 						$row[] = $benefits[$i]['feature'] ?? '';
	// 					}
	// 					break;

	// 					case 'name_ar':
	// 					$arTranslations = $product->arTranslations;
	// 					$row[] = $arTranslations['name'] ?? '';
	// 					$row[] = $arTranslations['description'] ?? '';
	// 					$row[] = $arTranslations['content'] ?? '';
	// 					$row[] = $arTranslations['warranty_information'] ?? '';
	// 					break;

	// 					default:
	// 					$row[] = $product->$field ?? '';
	// 				}
	// 			}
	// 			fputcsv($handle, $row);
	// 		}
	// 		fclose($handle);
	// 	});

	// 	$response->headers->set('Content-Type', 'text/csv');//
	// 	$response->headers->set('Content-Disposition', 'attachment; filename="products-' . date('Y-m-d') . '.csv"');

	// 	return $response;
	// }

	$spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Prepare headers with proper capitalization
    $headers = [];
    foreach ($allFields as $field) {
        $headers[] = $headerMap[$field] ?? $field;
    }

    // Write headers to first row (Excel rows start at 1)
    foreach ($headers as $colIndex => $header) {
        $sheet->setCellValueByColumnAndRow($colIndex + 1, 1, $header);
    }

    $skipFields = [
        'discount1', 'start_date1', 'end_date1',
        'buying_quantity2', 'discount2', 'start_date2', 'end_date2',
        'buying_quantity3', 'discount3', 'start_date3', 'end_date3',
        'feature1', 'benefit2', 'feature2', 'benefit3', 'feature3',
        'description2', 'description3', 'description4',
        'benefit4', 'feature4', 'benefit5', 'feature5',
        'benefit6', 'feature6', 'benefit7', 'feature7',
        'benefit8', 'feature8', 'benefit9', 'feature9',
        'benefit10', 'feature10',
        "faq_answer1", "faq_question2", "faq_answer2", "faq_question3", "faq_answer3", "faq_question4", "faq_answer4",
        "faq_question5", "faq_answer5", "faq_question6", "faq_answer6", "faq_question7", "faq_answer7", "faq_question8",
        "faq_answer8", "faq_question9", "faq_answer9", "faq_question10", "faq_answer10",
        "meta_description",
        'description_ar', 'content_ar', 'warranty_information_ar'
    ];

    $rowIndex = 2; // Start from second row for product data

    foreach ($products as $product) {
        $colIndex = 1;
        foreach ($allFields as $field) {
            if (in_array($field, $skipFields)) {
                continue; // skip these fields
            }

            $value = '';

            switch ($field) {
                case 'categories':
                    $value = $product->latestChildCategory() ? ($product->latestChildCategory()->name ?? '') : '';
                    break;

                case 'stock_status':
                    $stockMap = ['in_stock' => 1, 'Out of Stock' => 2, 'Pre Order' => 3];
                    $value = $stockMap[$product->stock_status] ?? '';
                    break;

                case 'status':
                    $statusMap = ['published' => 1, 'draft' => 2, 'pending' => 3];
                    $value = $statusMap[$product->status] ?? 2;
                    break;

                case 'unit_of_measurement':
                    $unitMap = ['Each' => 1, 'Dozen' => 2, 'Box' => 3, 'Case' => 4];
                    $value = $unitMap[$product->unit_of_measurement] ?? '';
                    break;

                case 'with_storehouse_management':
                case 'variant_requires_shipping':
                    $value = $product->$field ? 1 : 0;
                    break;

                case 'refund_policy':
                    $refundMap = ['Non-Refundable' => 1, '15 Days Refund' => 2, '90 Days Refund' => 3];
                    $value = $refundMap[$product->refund_policy] ?? '';
                    break;

                case 'is_featured':
                    $value = $product->is_featured ? 1 : 0;
                    break;

                case 'weight_option':
                case 'shipping_weight_option':
                    $weightValidOptions = ['lbs', 'kg', 'g'];
                    $value = in_array($product->$field, $weightValidOptions) ? $product->$field : '';
                    break;

                case 'dimension_option':
                case 'shipping_dimension_option':
                    $dimensionValidOptions = ['inch', 'cm', 'mm'];
                    $value = in_array($product->$field, $dimensionValidOptions) ? $product->$field : '';
                    break;

                case 'tags':
                    $value = $product->tags ? implode(',', $product->tags->pluck('name')->toArray()) : '';
                    break;

                case 'brand':
                    if (is_string($product->brand) && json_decode($product->brand)) {
                        $brandData = json_decode($product->brand, true);
                        $value = $brandData['name'] ?? '';
                    } elseif (is_object($product->brand) || is_array($product->brand)) {
                        $brandData = is_array($product->brand) ? $product->brand : $product->brand->toArray();
                        $value = $brandData['name'] ?? '';
                    } else {
                        $value = $product->brand ?? '';
                    }
                    break;

                case 'vendor':
                    $value = $product->store->name ?? '';
                    break;

                case 'images':
                    $imageData = $product->images;
                    if (is_string($imageData) && json_decode($imageData)) {
                        $imageArray = json_decode($imageData, true);
                        $cleanUrls = [];
                        foreach ($imageArray as $img) {
                            if (is_string($img)) {
                                $cleanUrls[] = str_replace('\/', '/', trim($img, '"'));
                            }
                        }
                        $value = implode(',', $cleanUrls);
                    } else {
                        $value = is_string($imageData) ? $imageData : '';
                    }
                    break;

                case 'upload_video':
                    $videoData = $product->upload_video;
                    if (is_string($videoData) && json_decode($videoData)) {
                        $videoArray = json_decode($videoData, true);
                        $cleanUrls = [];
                        foreach ($videoArray as $video) {
                            if (is_string($video)) {
                                $cleanUrls[] = str_replace('\/', '/', trim($video, '"'));
                            }
                        }
                        $value = implode(',', $cleanUrls);
                    } else {
                        $value = is_string($videoData) ? $videoData : '';
                    }
                    break;

                case 'frequently_bought_together':
                    $fbtData = $product->frequently_bought_together;
                    if (is_string($fbtData) && json_decode($fbtData)) {
                        $fbtArray = json_decode($fbtData, true);
                        $valuesArr = array_map(fn($item) => $item['value'] ?? '', $fbtArray);
                        $value = implode(',', $valuesArr);
                    } else {
                        $value = is_string($fbtData) ? $fbtData : '';
                    }
                    break;

                case 'compare_products':
                    $compareData = is_string($product->compare_products) ? json_decode($product->compare_products, true) : $product->compare_products;
                    $value = is_array($compareData) ? implode(',', $compareData) : '';
                    break;

                case 'url':
                    $value = $product->slug ? 'https://thehorecastore.co/products/' . $product->slug->key : '';
                    break;

                case 'buying_quantity1':
                    $discounts = $product->discounts->take(3);
                    for ($i = 0; $i < 3; $i++) {
                        $discount = $discounts[$i] ?? null;
                        $sheet->setCellValueByColumnAndRow($colIndex++, $rowIndex, $discount->product_quantity ?? '');
                        $sheet->setCellValueByColumnAndRow($colIndex++, $rowIndex, $discount->value ?? '');
                        $sheet->setCellValueByColumnAndRow($colIndex++, $rowIndex, $discount->start_date ?? '');
                        $sheet->setCellValueByColumnAndRow($colIndex++, $rowIndex, $discount->end_date ?? '');
                    }
                    continue 2; // skip the rest of the current iteration

                case 'description1':
                    $descriptions = json_validate($product->description) ? json_decode($product->description, true) : (is_array($product->description) ? $product->description : explode('|', $product->description));
                    for ($i = 0; $i < 4; $i++) {
                        $sheet->setCellValueByColumnAndRow($colIndex++, $rowIndex, $descriptions[$i] ?? '');
                    }
                    continue 2;

                case 'benefit1':
                    $benefits = is_array($product->benefits_features) ? $product->benefits_features : json_decode($product->benefits_features, true);
                    for ($i = 0; $i < 10; $i++) {
                        $sheet->setCellValueByColumnAndRow($colIndex++, $rowIndex, $benefits[$i]['benefit'] ?? '');
                        $sheet->setCellValueByColumnAndRow($colIndex++, $rowIndex, $benefits[$i]['feature'] ?? '');
                    }
                    continue 2;

                case 'faq_question1':
                    $faqs = is_array($product->faqs) ? $product->faqs : json_decode($product->faqs, true);
                    for ($i = 0; $i < 10; $i++) {
                        $sheet->setCellValueByColumnAndRow($colIndex++, $rowIndex, $faqs[$i]['question'] ?? '');
                        $sheet->setCellValueByColumnAndRow($colIndex++, $rowIndex, $faqs[$i]['answer'] ?? '');
                    }
					case 'meta_title':
						$value = strip_tags($product->meta_title);
						break;
		
					default:
						$value = $product->$field ?? '';
						break;
				}
		
				$sheet->setCellValueByColumnAndRow($colIndex++, $rowIndex, $value);
			}
			$rowIndex++;
		}
		
		// Write Excel file to output stream for download
		$writer = new Xlsx($spreadsheet);
		
		$response = new StreamedResponse(function () use ($writer) {
			$writer->save('php://output');
		});
		
		$filename = 'products_export_' . date('Ymd_His') . '.xlsx';
		
		$response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		$response->headers->set('Content-Disposition', 'attachment;filename="' . $filename . '"');
		$response->headers->set('Cache-Control', 'max-age=0');
		
		return $response;
		
		 
}
}