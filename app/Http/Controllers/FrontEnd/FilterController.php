<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Product;
use App\Models\Category;

class FilterController extends Controller
{
	/**
	 * @OA\Post(
	 *     path="/api/frontend/products/filters",
	 *     summary="Get Attribute List",
	 *     description="Fetch products with dynamic attribute filters",
	 *     tags={"Frontend-Categories"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"category_id"},
	 *             @OA\Property(property="category_id", type="integer", example=1, description="ID of the product category"),
	 *             @OA\Property(property="page", type="integer", example=1, description="Page number for pagination"),
	 *             @OA\Property(property="length", type="integer", example=20, description="Number of records per page"),
	 *             @OA\Property(property="price_min", type="number", example=100, description="Minimum price"),
	 *             @OA\Property(property="price_max", type="number", example=1000, description="Maximum price"),
	 *             @OA\Property(property="sort_by", type="string", enum={"id","price","created_at"}, example="price", description="Sort field"),
	 *             @OA\Property(property="sort_dir", type="string", enum={"asc","desc"}, example="asc", description="Sort direction"),
	 *             @OA\Property(property="filters", type="array", @OA\Items(
	 *                 type="object",
	 *                 @OA\Property(property="specification_name", type="string", example="Color"),
	 *                 @OA\Property(property="specification_value", oneOf={
	 *                     @OA\Schema(type="string", example="Red"),
	 *                     @OA\Schema(type="array", @OA\Items(type="string", example="Red")),
	 *                     @OA\Schema(type="object", @OA\Property(property="min", type="number", example=10), @OA\Property(property="max", type="number", example=100))
	 *                 })
	 *             ), description="Dynamic attribute filters")
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
			'price_min' => 'nullable|numeric|min:0',
			'price_max' => 'nullable|numeric|min:0',
			'rating' => 'nullable|numeric|min:1|max:5',
			'sort_by' => 'nullable|in:price',
			'sort_dir' => 'nullable|in:asc,desc',
			'brand_ids' => 'nullable|array',
			'filters' => 'nullable|array',
		]);

		/* Ensure only leaf (last-level) and published category is used */
		$category = Category::whereDoesntHave('children')->where('id', $request->category_id)->first();

		if (!$category) {
			return response()->json([
				'success' => false,
				'message' => 'Only leaf-level category (categories without children) can be selected.',
			], 422);
		}

		$filters = [];

		$priceRange = Product::join('product_categories', 'ec_products.id', '=', 'product_categories.product_id')
		->join('product_suppliers', 'ec_products.id', '=', 'product_suppliers.product_id')
		->where('product_categories.category_id', $category->id)
		->selectRaw('
			MIN(CASE WHEN product_suppliers.sale_price > 0 THEN product_suppliers.sale_price ELSE product_suppliers.price END) as min_price,
			MAX(CASE WHEN product_suppliers.sale_price > 0 THEN product_suppliers.sale_price ELSE product_suppliers.price END) as max_price
			')
		->first();

		$filters['priceRange'] = $priceRange->toArray();
		$filters['brands'] = $category->allBrandsFromLeaves()->toArray();
		$filters['ratings'] = [5, 4, 3, 2, 1];

		$allProductIds = $category->productIds();
		$categoryAttributeIds = [];

		if ($category->subCategory && $category->subCategory->attributes_ids) {
			if (is_string($category->subCategory->attributes_ids)) {
				$categoryAttributeIds = array_map('intval', explode(',', $category->subCategory->attributes_ids));
			} elseif (is_array($category->subCategory->attributes_ids)) {
				$categoryAttributeIds = array_map('intval', $category->subCategory->attributes_ids);
			}
		}

		dd($categoryAttributeIds);


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

	private function getCategoryBrands($categoryId) {

	}
}
