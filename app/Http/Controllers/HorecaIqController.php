<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;

class HorecaIqController extends BaseController
{
	/**
	 * @OA\Get(
	 *     path="/api/horeca-iq/products",
	 *     summary="Get products list for Horeca IQ",
	 *     description="Returns paginated list of products with search and sort support",
	 *     tags={"Product IQ"},
	 *     security={{"bearerAuth":{}}},
	* @OA\Parameter(name="page", in="query", description="Page number for pagination", required=true, example=1, @OA\Schema(type="integer", minimum=1)),
	* @OA\Parameter(name="length", in="query", description="Number of records per page", required=true, example=20, @OA\Schema(type="integer", minimum=1)),
	 *     @OA\Parameter(name="global", in="query", description="Global search across all fields", @OA\Schema(type="string")),
	 *     @OA\Parameter(name="sort_by", in="query", description="Column name to sort by", @OA\Schema(type="string", enum={"id", "name", "sku"})),
	 *     @OA\Parameter(name="sort_dir", in="query", description="Sort direction", example="asc", @OA\Schema(type="string", enum={"asc", "desc"})),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 * )
	 */
	public function index(Request $request)
	{
		$searchableColumns = ['id', 'name', 'sku'];
		$sortableColumns = $searchableColumns;

		$sortBy = in_array($request->input('sort_by'), $sortableColumns) ? $request->input('sort_by') : 'id';
		$sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

		$recordsQuery = Product::query();

		/* Validate required pagination params */
		if (!$request->filled('page') || !$request->filled('length')) {
			return response()->json([
				'success' => false,
				'message' => 'The page and length fields are required.',
			], 422);
		}

		/* Eager load relationships */
		$recordsQuery->with([
			'firstSupplier' => function ($q) {
				$q->select('product_suppliers.id', 'product_suppliers.product_id', 'product_suppliers.total_cost_per_item', 'product_suppliers.sale_price', 'product_suppliers.price', 'product_suppliers.margin');
			},
			'brand:id,name',
			'categories:id,name,parent_id',
			'categories.parentRecursiveNames',
		]);

		/* Global search */
		if ($request->filled('global')) {
			$search = $request->input('global');
			$recordsQuery->where(function ($q) use ($searchableColumns, $search) {
				foreach ($searchableColumns as $col) {
					$q->orWhere($col, 'LIKE', '%' . $search . '%');
				}
			});
		} else {
			/* Column specific search */
			foreach ($searchableColumns as $col) {
				if ($request->filled($col)) {
					$recordsQuery->where($col, 'LIKE', '%' . $request->input($col) . '%');
				}
			}
		}

		/* Sorting */
		$recordsQuery->orderBy($sortBy, $sortDir);

		/* Count before pagination */
		$totalRecords = (clone $recordsQuery)->count();

		/* Pagination calculation */
		$length = (int) $request->input('length');
		$page = (int) $request->input('page');
		$totalPages = (int) ceil($totalRecords / $length);
		$page = ($page > $totalPages && $totalPages > 0) ? 1 : $page;

		/* Fetch paginated records */
		$records = $recordsQuery
		->offset(($page - 1) * $length)
		->limit($length)
		->get(['id', 'name', 'sku', 'images', 'brand_id']);

		/* Transform records */
		$records->transform(function ($record) {
			/* Decode images */
			$images = is_array($record->images) ? $record->images : (is_array($decoded = json_decode($record->images, true)) ? $decoded : null);

			$regularPrice = $record->firstSupplier->price ?? null;
			$salePrice = $record->firstSupplier->sale_price ?? null;

			$record->title = $record->name;
			$record->image = $images[0] ?? null;
			$record->brand_name = $record->brand->name ?? null;
			$record->cost = $record->firstSupplier->total_cost_per_item ?? null;
			$record->regular_price = $regularPrice;
			$record->sale_price = ($salePrice !== null && $salePrice > 0) ? $salePrice : $regularPrice;
			$record->margin = $record->firstSupplier->margin ?? null;

			unset($record->name, $record->images, $record->firstSupplier, $record->brand_id, $record->brand);

			return $record;
		});

		return response()->json([
			'success' => true,
			'message' => __('msg_rec_list'),
			'data' => $records,
			'total_pages' => $totalPages,
			'total_records' => $totalRecords,
		]);
	}

	/**
	 * @OA\Get(
	 *     path="/api/horeca-iq/products/{product_id}",
	 *     summary="Get product details for Horeca IQ",
	 *     description="Returns detailed information of a single product including pricing, brand, categories and attributes",
	 *     tags={"Product IQ"},
	 *     security={{"bearerAuth":{}}},
	 *     @OA\Parameter(
	 *         name="product_id",
	 *         in="path",
	 *         required=true,
	 *         description="Product ID",
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     @OA\Response(response=404, description="Product not found", @OA\MediaType(mediaType="application/json"))
	 * )
	 */
	public function show($productId)
	{
		$product = Product::with([
			'firstSupplier' => function ($q) {
				$q->select('product_suppliers.id', 'product_suppliers.product_id', 'product_suppliers.total_cost_per_item', 'product_suppliers.sale_price', 'product_suppliers.price', 'product_suppliers.shipping_charge');
			},
			'brand:id,name',
			'seoProductUrl:id,relational_id,relational_type,url',
			'productAttributes:id,product_id,attribute_id,attribute_value,measurement_unit_id',
			'productAttributes.attributeDetails:id,name',
			'productAttributes.measurementUnit:id,name,symbol',
		])
		->where('status', 'published')
		->select('id', 'name', 'sku', 'images', 'brand_id', 'description')
		->find($productId);

		if (!$product) {
			return response()->json([
				'success' => false,
				'message' => 'Product not found.',
			], 404);
		}

		/* Decode images */
		$images = is_array($product->images) ? $product->images : (is_array($decoded = json_decode($product->images, true)) ? $decoded : null);

		$regularPrice = $product->firstSupplier->price ?? null;
		$salePrice = $product->firstSupplier->sale_price ?? null;

		$product->title = $product->name;
		$product->image = $images[0] ?? null;
		$product->description = is_array($product->description) ? $product->description : (is_array($decoded = json_decode($product->description, true)) ? $decoded : null);
		$product->brand_name = $product->brand->name ?? null;
		$product->regular_price = $regularPrice;
		$product->sale_price = ($salePrice !== null && $salePrice > 0) ? $salePrice : $regularPrice;
		$product->cost_price = $product->firstSupplier->total_cost_per_item ?? null;
		$product->shipping_cost = $product->firstSupplier->shipping_charge ?? null;

		$product->url = config('app.url') . '/' . $product->parent_category_url() . '/' . $product->category_url() . '/' . ($product->seoProductUrl->url ?? "");

		unset($product->name, $product->images, $product->firstSupplier, $product->brand, $product->brand_id, $product->seoProductUrl);

		$product->product_attributes = $product->productAttributes->map(function ($attribute) {
	$unit = $attribute->measurementUnit->symbol ?? '';
	return [
		$attribute->attributeDetails->name => $attribute->attribute_value . ($unit ? ' ' . $unit : ''),
	];
})->values();

unset($product->productAttributes,$product->categories);

		return response()->json([
			'success' => true,
			'data' => $product,
		]);
	}
}
