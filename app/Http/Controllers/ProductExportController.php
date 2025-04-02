<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Log;

class ProductExportController extends Controller
{
	/**
	 * @OA\Post(
	 *     path="/api/products/export",
	 *     summary="Export products as CSV",
	 *     tags={"Products"},
	 *     security={{"bearerAuth":{}}},
	 *     @OA\Parameter(
	 *         name="category_id",
	 *         in="query",
	 *         description="Filter products by category ID",
	 *         required=false,
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\Parameter(
	 *         name="brand_id",
	 *         in="query",
	 *         description="Filter products by brand ID",
	 *         required=false,
	 *         @OA\Schema(type="integer")
	 *     ),
	 *      @OA\Parameter(
	 *         name="store_id",
	 *         in="query",
	 *         description="Filter products by Store ID",
	 *         required=false,
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\Parameter(
	 *         name="limit",
	 *         in="query",
	 *         description="Limit the number of products (default 100)",
	 *         required=false,
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\Parameter(
	 *         name="fields",
	 *         in="query",
	 *         description="Comma-separated list of fields to include in export",
	 *         required=false,
	 *         @OA\Schema(type="string")
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="CSV file download",
	 *         @OA\Header(header="Content-Disposition", @OA\Schema(type="string"))
	 *     ),
	 *     @OA\Response(
	 *         response=404,
	 *         description="No products found",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=false),
	 *             @OA\Property(property="message", type="string", example="No products found for the given criteria.")
	 *         )
	 *     )
	 * )
	 */

	public function export(Request $request)
	{
		/* Start with detailed logging */
		Log::info('Export endpoint accessed', [
			'route' => request()->path(),
			'method' => request()->method(),
			'all_request_data' => $request->all(),
			'user' => auth()->check() ? auth()->id() : 'unauthenticated',
		]);

		/* Parse input parameters */
		$categoryId = $request->input('category_id');
		$brandId = $request->input('brand_id');
		$storeId = $request->input('store_id');

		$limit = $request->input('limit', 100); /* Default limit 100 */

		/* Parse fields from comma-separated string to array if provided */
		$fieldsParam = $request->input('fields');
		$selectedFields = $fieldsParam ? explode(',', $fieldsParam) : [];

		/* Query builder */
		$query = Product::query();

		/* Eager load relationships */
		$query->with(['categories', 'brand', 'store', 'tags', 'seoMetaData']);

		if ($categoryId) {
			Log::info('Filtering by category ID: ' . $categoryId);
			$query->whereHas('categories', function ($q) use ($categoryId) {
				$q->where('category_id', $categoryId);
			});
		}

		if ($brandId) {
			Log::info('Filtering by brand ID: ' . $brandId);
			$query->where('brand_id', $brandId);
		}

		if ($storeId) {
			Log::info('Filtering by Vendor ID: ' . $storeId);
			$query->where('store_id', $storeId);
		}

		/* Get products */
		$products = $query->limit($limit)->get();

		/* Debugging log */
		Log::info('Product Export Debug', [
			'SQL Query' => $query->toSql(),
			'Bindings' => $query->getBindings(),
			'Product Count' => $products->count(),
			'Category ID Filter' => $categoryId,
			'Limit Applied' => $limit,
			'Selected Fields' => $selectedFields
		]);

		/* Return message if products empty */
		if ($products->isEmpty()) {
			return response()->json([
				"success" => false,
				"message" => "No products found for the given criteria.",
			]);
		}

		/* Define fields in the exact sequence requested with correct capitalization */
		$allFields = [
			'id',
			'url',
			'name',
			'content',
			'description',
			'warranty_information',
			'sku',
			'brand',
			'vendor',
			// 'product_types',
			'categories',
			'tags',
			'stock_status',
			'with_storehouse_management',
			'quantity',
			'cost_per_item',
			'unit_of_measurement',
			'price',
			'sale_price',
			'start_date_sale_price',
			'end_date_sale_price',
			'minimum_order_quantity',
			'box_quantity',
			'delivery_days',
			'variant_requires_shipping',
			'images',
			'upload_video',
			// 'seo_title',
			// 'seo_description',
			'barcode',
			'refund_policy',
			'status',
			'google_shopping_category',
			'google_shopping_mpn',
			'is_featured',
			'weight_option',
			'weight',
			'dimension_option',
			'length',
			'width',
			'height',
			'depth',
			'shipping_weight_option',
			'shipping_weight',
			'shipping_dimension_option',
			'shipping_width',
			'shipping_depth',
			'shipping_height',
			'shipping_length',
			'frequently_bought_together',
			'compare_products',
			'variant_1_title',
			'variant_1_value',
			'variant_1_products',
			'variant_2_title',
			'variant_2_value',
			'variant_2_products',
			'variant_3_title',
			'variant_3_value',
			'variant_3_products',
			'variant_color_title',
			'variant_color_value',
			'variant_color_products',
			'buying_quantity1',
			'discount1',
			'start_date1',
			'end_date1',
			'buying_quantity2',
			'discount2',
			'start_date2',
			'end_date2',
			'buying_quantity3',
			'discount3',
			'start_date3',
			'end_date3',
			'name_ar',
			'description_ar',
			'content_ar',
			'warranty_information_ar'
		];

		/* Define pretty headers that match exactly what you requested */
		$headerMap = [
			'id' => 'Id',
			'url' => 'URL',
			'name' => 'Name',
			'content' => 'Content',
			'description' => 'Description',
			'warranty_information' => 'Warranty Information',
			'sku' => 'SKU',
			'brand' => 'Brand',
			'vendor' => 'Vendor',
			// 'product_types' => 'Product Types',
			'categories' => 'Categories',
			'tags' => 'Tags',
			'stock_status' => 'Stock Status',
			'with_storehouse_management' => 'With Storehouse Management',
			'quantity' => 'Quantity',
			'cost_per_item' => 'Cost Per Item',
			'unit_of_measurement' => 'Unit of Measurement',
			'price' => 'Price',
			'sale_price' => 'Sale Price',
			'start_date_sale_price' => 'Start Date Sale Price',
			'end_date_sale_price' => 'End Date Sale Price',
			'minimum_order_quantity' => 'Minimum Order Quantity',
			'box_quantity' => 'Box Quantity',
			'delivery_days' => 'Delivery Days',
			'variant_requires_shipping' => 'Variant Requires Shipping',
			'images' => 'Images',
			'upload_video' => 'Upload Video',
			// 'seo_title' => 'Seo Title',
			// 'seo_description' => 'Seo Description',
			'barcode' => 'Barcode (ISBN, UPC, GTIN, etc.)',
			'refund_policy' => 'Refund Policy',
			'status' => 'Status',
			'google_shopping_category' => 'Google Shopping Category',
			'google_shopping_mpn' => 'Google Shopping Mpn',
			'is_featured' => 'Is Featured',
			'weight_option' => 'Weight Option',
			'weight' => 'Weight',
			'dimension_option' => 'Dimension Option',
			'length' => 'Length',
			'width' => 'Width',
			'height' => 'Height',
			'depth' => 'Depth',
			'shipping_weight_option' => 'Shipping Weight Option',
			'shipping_weight' => 'Shipping Weight',
			'shipping_dimension_option' => 'Shipping Dimension Option',
			'shipping_width' => 'Shipping Width',
			'shipping_depth' => 'Shipping Depth',
			'shipping_height' => 'Shipping Height',
			'shipping_length' => 'Shipping Length',
			'frequently_bought_together' => 'Frequently Bought Together',
			'compare_products' => 'Compare Products',
			'variant_1_title' => 'Variant 1 Title',
			'variant_1_value' => 'Variant 1 Value',
			'variant_1_products' => 'Variant 1 Products',
			'variant_2_title' => 'Variant 2 Title',
			'variant_2_value' => 'Variant 2 Value',
			'variant_2_products' => 'Variant 2 Products',
			'variant_3_title' => 'Variant 3 Title',
			'variant_3_value' => 'Variant 3 Value',
			'variant_3_products' => 'Variant 3 Products',
			'variant_color_title' => 'Variant Color Title',
			'variant_color_value' => 'Variant Color Value',
			'variant_color_products' => 'Variant Color Products',
			'buying_quantity1' => 'Buying Quantity1',
			'discount1' => 'Discount1',
			'start_date1' => 'Start Date1',
			'end_date1' => 'End Date1',
			'buying_quantity2' => 'Buying Quantity2',
			'discount2' => 'Discount2',
			'start_date2' => 'Start Date2',
			'end_date2' => 'End Date2',
			'buying_quantity3' => 'Buying Quantity3',
			'discount3' => 'Discount3',
			'start_date3' => 'Start Date3',
			'end_date3' => 'End Date3',
			'name_ar' => 'Name (AR)',
			'description_ar' => 'Description (AR)',
			'content_ar' => 'Content (AR)',
			'warranty_information_ar' => 'Warranty Information (AR)'
		];

		/* Use selected fields if provided, otherwise use all fields */
		$fields = !empty($selectedFields) ? array_intersect($allFields, $selectedFields) : $allFields;

		/* CSV response create karna */
		$response = new StreamedResponse(function () use ($products, $fields, $headerMap) {
			$handle = fopen('php://output', 'w');

			/* Write headers with proper capitalization */
			$headers = [];
			foreach ($fields as $field) {
				$headers[] = $headerMap[$field] ?? $field;
			}
			fputcsv($handle, $headers);

			foreach ($products as $product) {
				$row = [];
				foreach ($fields as $field) {
					/* Format special sfields */
					switch ($field) {
						case 'categories':
						$lastCategory = $product->latestChildCategory() ? $product->latestChildCategory()->name ?? '' : '';
						$row[] = $lastCategory;
						break;

						case 'stock_status':
						$stockMap = ['in_stock' => 1, 'Out of Stock' => 2, 'Pre Order' => 3];
						$row[] = $stockMap[$product->stock_status] ?? '';
						break;

						case 'status':
						$statusMap = ['published' => 1, 'draft' => 2, 'pending' => 3];
						$row[] = $statusMap[$product->status] ?? 2; /* Default to 'Draft' (2) if not found */
						break;

						case 'unit_of_measurement':
						$unitMap = ['Each' => 1, 'Dozen' => 2, 'Box' => 3, 'Case' => 4];
						$row[] = $unitMap[$product->unit_of_measurement] ?? '';
						break;

						case 'with_storehouse_management':
						$row[] = $product->with_storehouse_management ? 1 : 0;
						break;

						case 'variant_requires_shipping':
						$row[] = $product->variant_requires_shipping ? 1 : 0;
						break;

						case 'refund_policy':
						$refundMap = ['Non-Refundable' => 1, '15 Days Refund' => 2, '90 Days Refund' => 3];
						$row[] = $refundMap[$product->refund_policy] ?? '';
						break;

						case 'is_featured':
						$row[] = $product->is_featured ? 1 : 0;
						break;

						case 'weight_option':
						case 'shipping_weight_option':
						$weightValidOptions = ['lbs', 'kg', 'g'];
						$row[] = in_array($product->$field, $weightValidOptions) ? $product->$field : '';
						break;

						case 'dimension_option':
						case 'shipping_dimension_option':
						$dimensionValidOptions = ['inch', 'cm', 'mm'];
						$row[] = in_array($product->$field, $dimensionValidOptions) ? $product->$field : '';
						break;

						case 'tags':
						$row[] = $product->tags ? implode(',', $product->tags->pluck('name')->toArray()) : '';
						break;

						case 'brand':
						/* Extract just the brand name from the object */
						if (is_string($product->brand) && json_decode($product->brand)) {
							$brandData = json_decode($product->brand, true);
							$row[] = $brandData['name'] ?? '';
						} elseif (is_object($product->brand) || is_array($product->brand)) {
							$brandData = is_array($product->brand) ? $product->brand : $product->brand->toArray();
							$row[] = $brandData['name'] ?? '';
						} else {
							$row[] = $product->brand ?? '';
						}
						break;

						case 'vendor':
						$row[] = $product->store->name ?? ''; /* Get store (vendor) name from the relationship */
						break;

						case 'images':
						/* Format images as clean URLs */
						$imageData = $product->images;
						if (is_string($imageData) && json_decode($imageData)) {
							$imageArray = json_decode($imageData, true);
							$cleanUrls = [];
							foreach ($imageArray as $img) {
								if (is_string($img)) {
									$cleanUrls[] = str_replace('\/', '/', trim($img, '"'));
								}
							}
							$row[] = implode(',', $cleanUrls);
						} else {
							$row[] = is_string($imageData) ? $imageData : '';
						}
						break;

						case 'upload_video':
						/* Format videos as clean URLs */
						$videoData = $product->upload_video;
						if (is_string($videoData) && json_decode($videoData)) {
							$videoArray = json_decode($videoData, true);
							$cleanUrls = [];
							foreach ($videoArray as $video) {
								if (is_string($video)) {
									$cleanUrls[] = str_replace('\/', '/', trim($video, '"'));
								}
							}
							$row[] = implode(',', $cleanUrls);
						} else {
							$row[] = is_string($videoData) ? $videoData : '';
						}
						break;

						case 'frequently_bought_together':
						/* Format as comma-separated values */
						$fbtData = $product->frequently_bought_together;
						if (is_string($fbtData) && json_decode($fbtData)) {
							$fbtArray = json_decode($fbtData, true);
							$values = array_map(function($item) {
								return $item['value'] ?? '';
							}, $fbtArray);
							$row[] = implode(',', $values);
						} else {
							$row[] = is_string($fbtData) ? $fbtData : '';
						}
						break;

						case 'compare_products':
						/* Ensure it's an array and format as comma-separated IDs */
						$compareData = is_string($product->compare_products) ? json_decode($product->compare_products, true) : $product->compare_products;
						if (is_array($compareData)) {
							$row[] = implode(',', $compareData); /* Ensure IDs are separated properly */
						} else {
							$row[] = '';
						}
						break;

						case 'url':
						$row[] = $product->slug ? 'https://thehorecastore.co/products/' . $product->slug->key : '';
						break;

						// case 'seo_title':
						// $row[] = $product->seoMetaData ? ($product->seoMetaData->value['seo_title'] ?? '') : '';
						// break;

						// case 'seo_description':
						// $row[] = $product->seoMetaData ? ($product->seoMetaData->value['seo_description'] ?? '') : '';
						// break;

						case 'buying_quantity1':
						case 'discount1':
						case 'start_date1':
						case 'end_date1':
						case 'buying_quantity2':
						case 'discount2':
						case 'start_date2':
						case 'end_date2':
						case 'buying_quantity3':
						case 'discount3':
						case 'start_date3':
						case 'end_date3':
						$discounts = $product->discounts->take(3); /* Get up to 3 discounts */

						/* Default empty values */
						$discountValues = [
							'buying_quantity1' => '',
							'discount1' => '',
							'start_date1' => '',
							'end_date1' => '',
							'buying_quantity2' => '',
							'discount2' => '',
							'start_date2' => '',
							'end_date2' => '',
							'buying_quantity3' => '',
							'discount3' => '',
							'start_date3' => '',
							'end_date3' => '',
						];

						/* Populate discount values */
						foreach ($discounts as $index => $discount) {
							if ($index == 0) {
								$discountValues['buying_quantity1'] = $discount->product_quantity ?? '';
								$discountValues['discount1'] = $discount->value ?? '';
								$discountValues['start_date1'] = $discount->start_date ?? '';
								$discountValues['end_date1'] = $discount->end_date ?? '';
							} elseif ($index == 1) {
								$discountValues['buying_quantity2'] = $discount->product_quantity ?? '';
								$discountValues['discount2'] = $discount->value ?? '';
								$discountValues['start_date2'] = $discount->start_date ?? '';
								$discountValues['end_date2'] = $discount->end_date ?? '';
							} elseif ($index == 2) {
								$discountValues['buying_quantity3'] = $discount->product_quantity ?? '';
								$discountValues['discount3'] = $discount->value ?? '';
								$discountValues['start_date3'] = $discount->start_date ?? '';
								$discountValues['end_date3'] = $discount->end_date ?? '';
							}
						}

						$row[] = $discountValues[$field]; /* Add the correct field value to CSV row */
						break;

						default:
						$row[] = $product->$field ?? '';
					}
				}
				fputcsv($handle, $row);
			}
			fclose($handle);
		});

		$response->headers->set('Content-Type', 'text/csv');//
		$response->headers->set('Content-Disposition', 'attachment; filename="products-' . date('Y-m-d') . '.csv"');

		return $response;
	}
}