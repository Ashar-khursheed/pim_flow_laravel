<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

use App\Models\Category;
use App\Models\Product;
use App\Models\Attribute;
use App\Models\ProductAttribute;
use App\Models\MeasurementUnit;
use App\Models\ProductSupplier;

class FilterController extends Controller
{
	/**
	 * @OA\Post(
	 *     path="/api/frontend/products/filters",
	 *     summary="Get Filtered Products",
	 *     description="Fetch products with dynamic attribute filters",
	 *     tags={"Frontend-Categories"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"category_id"},
	 *             @OA\Property(property="category_id", type="integer", example=54, description="ID of the product category"),
	 *             @OA\Property(property="page", type="integer", example=1, description="Page number for pagination"),
	 *             @OA\Property(property="length", type="integer", example=20, description="Number of records per page"),
	 *             @OA\Property(property="sort_by", type="string", enum={"id","price","created_at"}, example="price", description="Sort field"),
	 *             @OA\Property(property="sort_dir", type="string", enum={"asc","desc"}, example="asc", description="Sort direction"),
	 *             @OA\Property(
	 *                 property="applied_filters",
	 *                 type="object",
	 *                 description="General filters applied to products",
	 *                 @OA\Property(
	 *                     property="priceRange",
	 *                     type="object",
	 *                     @OA\Property(property="min_price", type="string", example="1514.00"),
	 *                     @OA\Property(property="max_price", type="string", example="2832.50")
	 *                 ),
	 *                 @OA\Property(property="brand_ids", type="array", @OA\Items(type="integer", example=1), description="Array of brand IDs"),
	 *                 @OA\Property(property="ratings", type="integer", example=5, description="Rating values")
	 *             ),
	 *             @OA\Property(
	 *                 property="applied_range_filters",
	 *                 type="array",
	 *                 description="Range-based attribute filters",
	 *                 @OA\Items(
	 *                     type="object",
	 *                     @OA\Property(property="attribute_id", type="integer", example=590, description="Attribute ID"),
	 *                     @OA\Property(property="unit_id", type="integer", example=62, description="Measurement unit ID"),
	 *                     @OA\Property(
	 *                         property="ranges",
	 *                         type="object",
	 *                         @OA\Property(property="min", type="integer", example=210),
	 *                         @OA\Property(property="max", type="integer", example=211)
	 *                     )
	 *                 )
	 *             ),
	 *             @OA\Property(
	 *                 property="applied_fixed_filters",
	 *                 type="array",
	 *                 description="Fixed value attribute filters",
	 *                 @OA\Items(
	 *                     type="object",
	 *                     @OA\Property(property="attribute_id", type="integer", example=225, description="Attribute ID"),
	 *                     @OA\Property(property="value", type="string", example="Black", description="Filter value")
	 *                 )
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Retrieved successfully", @OA\MediaType(mediaType="application/json")),
	 * )
	 */
	public function index(Request $request)
	{
		/* Validate request data */
		$request->validate([
			'category_id' => 'required|integer|exists:categories,id',
			'page' => 'required|integer',
			'length' => 'required|integer',
			'sort_by' => 'nullable|in:price',
			'sort_dir' => 'nullable|in:asc,desc',
			'applied_filters' => 'nullable|array',
			'applied_range_filters' => 'nullable|array',
			'applied_fixed_filters' => 'nullable|array',
		]);

		/* Ensure only leaf (last-level) and published category is used */
		// $category = Category::whereDoesntHave('children')->where('id', $request->category_id)->first();
		// if (!$category) {
		// 	return response()->json([
		// 		'success' => false,
		// 		'message' => 'Only leaf-level category (categories without children) can be selected.',
		// 	], 422);
		// }

		$category = Category::where('status', 'published')->where('id', $request->category_id)->first();

		if (!$category) {
			return response()->json([
				'success' => false,
				'message' => 'Category does not exist or is not published.',
			], 422);
		}

		$categoryIds = $category->getLeafCategories()->where('status', 'published')->pluck('id');

		$filters = [];


		// $priceRange = Product::join('product_categories', 'ec_products.id', '=', 'product_categories.product_id')
		// ->join('product_suppliers', 'ec_products.id', '=', 'product_suppliers.product_id')
		// ->whereIn('product_categories.category_id', $categoryIds)
		// ->where('ec_products.status', 'published')
		// ->selectRaw('
		// 	MIN(CASE WHEN product_suppliers.sale_price > 0 THEN product_suppliers.sale_price ELSE product_suppliers.price END) as min_price,
		// 	MAX(CASE WHEN product_suppliers.sale_price > 0 THEN product_suppliers.sale_price ELSE product_suppliers.price END) as max_price
		// 	')
		// ->first();
		$priceRange = ProductSupplier::join('ec_products', 'product_suppliers.product_id', '=', 'ec_products.id')
		->join('product_categories', 'ec_products.id', '=', 'product_categories.product_id')
		->whereIn('product_categories.category_id', $categoryIds)
		->where('ec_products.status', 'published')
		->selectRaw('
			MIN(CASE WHEN product_suppliers.sale_price > 0 THEN product_suppliers.sale_price ELSE product_suppliers.price END) as min_price,
			MAX(CASE WHEN product_suppliers.sale_price > 0 THEN product_suppliers.sale_price ELSE product_suppliers.price END) as max_price
			')
		->first();

		/************************* Create Default Filter ***********************/
		$filters['priceRange'] = $priceRange->toArray();
		$filters['brands'] = $category->allBrandsFromLeaves()->toArray();
		$filters['ratings'] = [5, 4, 3, 2, 1];
		/************************* Create Default Filter ***********************/

		$allProductIds = $category->productIdsFromLeafCategories();

		/************************* Apply Default Filter ***********************/
		$filteredProducts = Product::whereIn('id', $allProductIds)->where('status', 'published');
		if (!empty($request->applied_filters['brand_ids'])) {
			$filteredProducts->whereIn('brand_id', $request->applied_filters['brand_ids']);
		}

		if (!empty($request->applied_filters['ratings'])) {
			$rating = (int) $request->applied_filters['ratings'];

			$filteredProducts->whereHas('reviews', function ($q) use ($rating) {
				$q->select('product_id')
				->groupBy('product_id')
				->havingRaw('AVG(star) BETWEEN ? AND ?', [$rating, $rating + 1]);
			});
		}

		if (
			!empty($request->applied_filters['priceRange']) &&
			!empty($request->applied_filters['priceRange']['min_price']) &&
			!empty($request->applied_filters['priceRange']['max_price'])
		) {
			$minPrice = $request->applied_filters['priceRange']['min_price'];
			$maxPrice = $request->applied_filters['priceRange']['max_price'];

			$filteredProducts->whereHas('productSuppliers', function ($q) use ($minPrice, $maxPrice) {
				$q->whereRaw('
					CASE
					WHEN product_suppliers.sale_price > 0
					THEN product_suppliers.sale_price
					ELSE product_suppliers.price
					END BETWEEN ? AND ?
					', [$minPrice, $maxPrice]);
			});
		}
		/************************* Apply Default Filter ***********************/

		$response = [];
		if (!$category->children()->exists()) {
			$categoryAttributeIds = [];
			if ($category->subCategory && $category->subCategory->attributes_ids) {
				$raw = $category->subCategory->attributes_ids;
				if (is_array($raw)) {
					$raw = $raw[0];
				}
				$categoryAttributeIds = array_map('intval', explode(',', $raw));
			}

			$categoryAttributes = Attribute::whereIn('id', $categoryAttributeIds)->get(['id', 'name', 'type']);

			/* Final grouped array with reset keys */
			$attributesByCategory = [
				'measurement' => $categoryAttributes->where('type', 'measurement')->values()->toArray(),
				'other' => $categoryAttributes->reject(fn($attr) => $attr->type == 'measurement')->values()->toArray(),
			];

			/************************* Apply Fixed Filter ***********************/
			if (!empty($request->applied_fixed_filters)) {
				foreach ($request->applied_fixed_filters as $filter) {
					if (!empty($filter['attribute_id']) && !empty($filter['value'])) {
						$filteredProducts->whereHas('productAttributes', function ($q) use ($filter) {
							$q->where('attribute_id', $filter['attribute_id']);
							$q->where('attribute_value', $filter['value']);
						});
					}
				}
			}
			$filteredProductIds = $filteredProducts->pluck('id');
			/************************* Apply Fixed Filter ***********************/


			/************************* Apply Range Filter ***********************/
			if (!empty($request->applied_range_filters)) {
				$filteredProducts = Product::whereIn('id', $filteredProductIds);
				foreach ($request->applied_range_filters as $filter) {
					if (
						!empty($filter['attribute_id']) &&
						!empty($filter['unit_id']) &&
						!empty($filter['ranges']['min']) &&
						!empty($filter['ranges']['max'])
					) {
						$filteredProducts->whereHas('productAttributes', function ($q) use ($filter) {
							$q->where('attribute_id', $filter['attribute_id']);
						});
					}
				}
				$productIds = $filteredProducts->pluck('id');

				$filteredProductIds = ProductAttribute::whereIn('product_id', $productIds)
				->get()
				->filter(function ($attr) use ($request) {
					foreach ($request->applied_range_filters as $filter) {
						if ($attr->attribute_id == $filter['attribute_id']) {
							$value = $attr->measurement_unit_id == $filter['unit_id']
							? $attr->attribute_value
							: convert_unit_with_id($attr->attribute_value, $filter['unit_id'], $attr->measurement_unit_id);

							/* Strictly check range */
							if ($value < $filter['ranges']['min'] || $value > $filter['ranges']['max']) {
								return false;
							}

							return true;
						}
					}
					return false;
				})
				->pluck('product_id')
				->unique()
				->values();
			}
			/************************* Apply Range Filter ***********************/


			/************************* Create Range Filter ***********************/
			$measurementAttributeValues = ProductAttribute::join('measurement_units', 'product_attributes.measurement_unit_id', '=', 'measurement_units.id')
			->join('measurement_types', 'measurement_units.measurement_type_id', '=', 'measurement_types.id')
			->join('category_measurement_unit_priorities', 'measurement_types.id', '=', 'category_measurement_unit_priorities.measurement_type_id')
			->whereIn('product_attributes.product_id', $filteredProductIds)
			->whereIn('product_attributes.attribute_id', array_column($attributesByCategory['measurement'], 'id'))
			->where('category_measurement_unit_priorities.category_id', $category->id)
			->select([
				'product_attributes.attribute_id',
				'product_attributes.attribute_value',
				'product_attributes.measurement_unit_id',
				'measurement_types.id as measurement_type_id',
				'measurement_types.name as measurement_type_name',
				'category_measurement_unit_priorities.measurement_unit_primary_id as primary_measurement_unit_id'
			])
			->get();

			$measurementAttributeArray = $measurementAttributeValues->toArray();

			$measurementUnitIDSymbol = MeasurementUnit::pluck('symbol', 'id')->toArray();
			$measurementUnitIDName = MeasurementUnit::pluck('name', 'id')->toArray();
			$attributeIDName = $categoryAttributes->pluck('name', 'id')->toArray();
			$rangefilterArray = [];
			foreach ($measurementAttributeArray as $key => $measurementAttribute) {
				/* Check if attribute_value is not numeric */
				if (!is_numeric($measurementAttribute['attribute_value'])) {
					continue;
				}

				/* Check if conversion is needed */
				$attributeValue = $measurementAttribute['attribute_value'];
				if ($measurementAttribute['measurement_unit_id'] != $measurementAttribute['primary_measurement_unit_id']) {
					$originalUnitName = $measurementUnitIDName[$measurementAttribute['measurement_unit_id']];
					$targetUnitName = $measurementUnitIDName[$measurementAttribute['primary_measurement_unit_id']];
					$attributeValue = convert_unit(
						$measurementAttribute['measurement_type_name'],
						$measurementAttribute['attribute_value'],
						$originalUnitName,
						$targetUnitName
					);
					// dd($measurementAttribute, $attributeValue);
				}

				$rangefilterArray[] = [
					'attribute_id' => $measurementAttribute['attribute_id'],
					'attribute_name' => $attributeIDName[$measurementAttribute['attribute_id']],
					'attribute_value' => (float) $attributeValue,
					'unit_id' => $measurementAttribute['primary_measurement_unit_id'],
					'unit_symbol' => $measurementUnitIDSymbol[$measurementAttribute['primary_measurement_unit_id']]
				];
			}

			$rangeFilters = createSmartRanges($rangefilterArray, 5);

			$response['rangeFilters'] = $rangeFilters;
			/************************* Create Range Filter ***********************/


			/************************* Create Fixed Filter ***********************/
			$otherAttributeValues = ProductAttribute::whereIn('product_id', $filteredProductIds)->whereIn('attribute_id', array_column($attributesByCategory['other'], 'id'))->get(['attribute_id', 'attribute_value']);
			$otherAttributeArray = $otherAttributeValues->toArray();

			$fixedFilters = [];

			foreach ($otherAttributeArray as $otherAttribute) {
				$attributeName = $attributeIDName[$otherAttribute['attribute_id']];
				$attributeValue = $otherAttribute['attribute_value'];

				/* Check if this attribute name already exists in fixedFilters */
				$found = false;
				foreach ($fixedFilters as $key => $value) {
					if ($key === $attributeName) {
						$found = true;
						break;
					}
				}

				/* If attribute name doesn't exist, create it */
				if (!$found) {
					$fixedFilters[$attributeName] = [
						'attribute_id' => $otherAttribute['attribute_id'],
						'values' => []
					];
				}

				/* Check if this value already exists in the values array */
				$valueExists = false;
				foreach ($fixedFilters[$attributeName]['values'] as $existingValue) {
					if ($existingValue === $attributeValue) {
						$valueExists = true;
						break;
					}
				}

				/* Add the value if it doesn't exist */
				if (!$valueExists) {
					$fixedFilters[$attributeName]['values'][] = $attributeValue;
				}
			}
			$response['fixedFilters'] = $fixedFilters;
			/************************* Create Fixed Filter ***********************/
		} else {
			$filteredProductIds = $filteredProducts->pluck('id');
			$response['categories'] = $category->children()->where('status', 'published')->with(['seoUrl:id,relational_id,relational_type,url'])->get(['id', 'name', 'icon_image'])->map(function ($child) {
				return [
					'id'   => $child->id,
					'name' => $child->name,
					'icon_image' => $child->icon_image,
					'url'  => $child->seoUrl->url ?? null,
				];
			});
		}


		/************************* Fetch Products ***********************/
		$sortableColumns = ['id', 'price'];
		$sortBy = in_array($request->input('sort_by'), $sortableColumns) ? $request->input('sort_by') : 'id';
		$sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

		$recordsQuery = Product::whereIn('id', $filteredProductIds);
		if ($sortBy === 'price') {
			$recordsQuery->addSelect([
				'best_price' => ProductSupplier::selectRaw('MIN(CASE
					WHEN sale_price IS NOT NULL AND sale_price > 0
					THEN sale_price
					ELSE price
					END)')
				->whereColumn('product_suppliers.product_id', 'ec_products.id')
			])
			->orderBy('best_price', $sortDir);
		} else {
			$recordsQuery->orderBy("ec_products.$sortBy", $sortDir);
		}


		if ($request->filled('page') && $request->filled('length')) {
			$totalRecords = (clone $recordsQuery)->count();
			$length = (int) $request->input('length');
			$totalPages = (int) ceil($totalRecords / $length);

			$page = (int) $request->input('page');
			/* If requested page exceeds total pages (after search), fallback to page 1 */
			if ($page > $totalPages && $totalPages > 0) {
				$page = 1;
			}

			$products = $recordsQuery->offset(($page - 1) * $length)->limit($length)->get();
		} else {
			$products = $recordsQuery->get();
			$totalRecords = $products->count();
			$totalPages = 1;
		}

		$transformedProducts = [];
		foreach ($products as $product) {
			$firstSupplier = $product->productSuppliers()
			->with([
				'vendor.country:id,name',
				'vendor.city:id,name',
				'inventoryUpdator:id,first_name,last_name'
			])
			->first();

			$fullValue = $product->sellingUnitAttribute->attribute_value ?? null;

			$attributeUnit = $product->sellingUnitAttribute && strpos($fullValue, '/') !== false
			? trim(explode('/', $fullValue)[1])
			: $fullValue;

			$sellingType = [
				'attribute_value' => $product->sellingUnitAttribute->attribute_value ?? null,
				'attribute_value_unit' => $attributeUnit,
			];
			$transformedProducts[] = [
				'id' => $product->id,
				'name' => $product->name,
				'category_url' => $product->category_url(),
				'parent_category_url' => $product->parent_category_url(),
				'sku' => $product->sku,
				'url' => $product->seoProductUrl?->url ?? null,
				'vendor_sku' => $firstSupplier->vendor_sku ?? null,

				'vendor_country' => $firstSupplier->vendor->country->name ?? null,
				'vendor_city' => $firstSupplier->vendor->city->name ?? null,
				'vendor_address' => $firstSupplier->vendor->address ?? null,
				'vendor_zipcode' => $firstSupplier->vendor->zipcode ?? null,

				'price' => $firstSupplier ? (float) $firstSupplier->price : null,
				'sale_price' => $firstSupplier ? (float) $firstSupplier->sale_price : null,
				'total_reviews' => $product->reviews->count(),
				'avg_rating' => $product->reviews->count() > 0 ? $product->reviews->avg('star') : null,
				'left_stock' => ($firstSupplier->quantity ?? 0) - ($product->units_sold ?? 0),
				'currency' => $product->currency->symbol,
						// 'in_wishlist' => $product->currency->symbol,
				'images' => is_array($product->images) ? $product->images : (json_decode($product->images, true) ?? []),
				'alt_tags' => is_array($product->alt_tags) ? $product->alt_tags : (json_decode($product->alt_tags, true) ?? []),
				"original_price"=> $firstSupplier ? (float) $firstSupplier->price : null,
				'front_sale_price' => $firstSupplier ? (float) $firstSupplier->sale_price : null,
				"best_price"=> $firstSupplier ? (float) $firstSupplier->price : null,
				"selling_type"=> $sellingType,
						// "per_unit_price"=>   $product->per_unit_price,
				'vendor_id' => $firstSupplier->vendor_id ?? null,
				'map' => $firstSupplier ? (float) $firstSupplier->map : null,
				'inventory' => $firstSupplier->inventory ?? null,
				'inventory_updated_by' => $firstSupplier->inventoryUpdator->name ?? null,
				'inventory' => $firstSupplier->inventory_updated_at ?? null,
				'in_stock' => $firstSupplier->in_stock ?? null,
				'delivery_days' => $firstSupplier->delivery_days ?? null,
				'return_policy' => $firstSupplier->return_policy ?? null,
				'free_shipping' => $firstSupplier->free_shipping ?? null,
				'warranty_information' => $firstSupplier->warranty_information ?? null,
				'min_quantity' => $firstSupplier->min_quantity ?? 0,
				'is_fixed' => $firstSupplier->is_fixed ?? 0,
				'quote_available' => $product->quote_available ?? null,
				'isRequired' => $product->isRequired,
			];
		}


		/************************* Fetch Products ***********************/
		$finalResponse = array_merge([
			'success' => true,
			'filters' => $filters,
			'products' => $transformedProducts,
			'total_pages' => $totalPages ?? 1,
			'total_records' => $totalRecords,
		], $response ?? []);

		return response()->json($finalResponse);
	}
}
