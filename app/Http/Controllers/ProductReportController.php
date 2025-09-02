<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Repository\ExcelRepository;
class ProductReportController extends Controller
{
	/**
	 * @OA\Get(
	 *     path="/api/product-report-export",
	 *     summary="Get product report list",
	 *     description="Report of products with id, sku, name, and branch name. Can search across product name, SKU, brand, status, and categories.",
	 *     tags={"Products Report"},
	 *
	 *     @OA\Parameter(
	 *         name="page",
	 *         in="query",
	 *         description="Page number for pagination",
	 *         required=false,
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\Parameter(
	 *         name="per_page",
	 *         in="query",
	 *         description="Number of products per page (default: 50)",
	 *         required=false,
	 *         @OA\Schema(type="integer", example=50)
	 *     ),
	 *     @OA\Parameter(
	 *         name="search",
	 *         in="query",
	 *         description="Search term for filtering products by name, SKU, brand, store, or category",
	 *         required=false,
	 *         @OA\Schema(type="string", example="samsung")
	 *     ),
	 *     @OA\Parameter(
	 *         name="status",
	 *         in="query",
	 *         description="Filter products by status (published, draft)",
	 *         required=false,
	 *         @OA\Schema(type="string", enum={"published", "draft"}, example="published")
	 *     ),
	 *     @OA\Parameter(
	 *         name="approved",
	 *         in="query",
	 *         description="Filter approved by status (0 = not approved, 1 = approved)",
	 *         required=false,
	 *         @OA\Schema(type="integer", enum={0,1}, example=1)
	 *     ),
	 *     @OA\Parameter(
	 *         name="brand",
	 *         in="query",
	 *         description="Filter by brand id",
	 *         required=false,
	 *         @OA\Schema(type="integer", example=5)
	 *     ),
	 *     @OA\Parameter(
	 *         name="category",
	 *         in="query",
	 *         description="Filter by category id",
	 *         required=false,
	 *         @OA\Schema(type="integer", example=3)
	 *     ),
	 *     @OA\Parameter(
	 *         name="sort_by",
	 *         in="query",
	 *         description="Column to sort by (id, name, sku, brand_id, vendor_id, status)",
	 *         required=false,
	 *         @OA\Schema(type="string", example="id")
	 *     ),
	 *     @OA\Parameter(
	 *         name="sort_direction",
	 *         in="query",
	 *         description="Sort direction (asc or desc)",
	 *         required=false,
	 *         @OA\Schema(type="string", enum={"asc", "desc"}, example="desc")
	 *     ),
	 *
	 *     security={{"bearerAuth":{}}},
	 *
	 *     @OA\Response(
	 *         response=200,
	 *         description="Product report Excel file",
	 *         @OA\MediaType(
	 *             mediaType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=401,
	 *         description="Unauthorized"
	 *     ),
	 *     @OA\Response(
	 *         response=500,
	 *         description="Server error"
	 *     )
	 * )
	 */
	public function index(Request $request, ExcelRepository $excelRepo)
	{
		/* Validate request data */
		$request->validate([
			'status' => 'string|in:draft,published',
			'approved' => 'string|in:0,1',
			'range_from' => 'required|integer|min:1',
			'range_to' => 'required|integer|gte:range_from|max:' . ($request->range_from + 500),
			'type' => 'required|string|in:Brand,Category,Vendor',
			'relational_id' => 'required|integer',
		]);
		$query = Product::with([
			'brand:id,name',
			'categories:id,name',
			'slug:id,key,reference_id',
			'productSuppliers',
			'vendors',
			'productAttributes.attributeDetails',
			'latestChildCategoryRelation:id,name'

		])->select(['id', 'name', 'sku', 'images', 'brand_id', 'status', 'gen_type', 'approved']);
		/* Apply relational filters */
		if (!empty($request->status)) {
			$query->where('status', $request->status);
		}
		if ($request->approved != '') {
			$query->where('approved', $request->approved);
		}
		if ($request->type == "Brand") {
			$query->where('brand_id', $request->relational_id);
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

		/* Formatting response */
		$formattedProducts = $products->map(function ($product) {

			foreach ($product->productAttributes as $attr) {
				$product_attributes[] = [
					'attribute_id' => $attr->attribute_id,
					'attribute_name' => $attr->attributeDetails->name ?? null,
					'attribute_value' => $attr->attribute_value,
					'measurement_unit_id' => $attr->measurement_unit_id,
					'measurement_unit_name' => $attr->measurementUnit->name ?? null,
				];
			}

			$brands = "";
			if ($product->brand) {
				$brands = Brand::withCount('products')->where('id', $product->brand->id)->first();
			}
			if (!empty($product_attributes)) {
				foreach ($product_attributes as $attributes) {

					$data[] = [
						'id' => $product->id,
						'name' => $product->name,
						'approved' => $product->approved,
						'sku' => $product->sku,
						'image' => ($imageUrls = json_decode($product->images, true)) && isset($imageUrls[0]) ? $imageUrls[0] : null,
						'brand_id' => optional($product->brand)->id,
						'brand' => optional($product->brand)->name,
						'status' => $product->status,
						'category_id' => $product->categories->pluck('id')->implode(', '),
						'category_name' => $product->categories->pluck('name')->implode(', '),
						'category_count' => $product->categories->count(),
						'product_count' => $brands ? $brands->products_count : null,
						'attribute_id' => $attributes['attribute_id'],
						'attribute_name' => $attributes['attribute_name'] ?? null,
						'attribute_value' => $attributes['attribute_value'],
						'measurement_unit_id' => $attributes['measurement_unit_id'],
						'measurement_unit_name' => $attributes['measurement_unit_name'] ?? null,
					];
				}
				return $data;
			}
		});

		$excelHeaders = ['id', 'name', 'approved', 'sku', 'image', 'brand id', 'brand', 'status', 'category_id', 'category_name', 'category_count', 'product_count', 'attribute_id', 'attribute_name', 'attribute_value', 'measurement_unit_id', 'measurement_unit_name'];

		$spreadsheet = $excelRepo->newSpreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setTitle('reports');

		/* Set headers */
		$excelRepo->setHeader($sheet, $excelHeaders);

		/* Fill data rows */
		$rowIndex = 2;
		foreach ($formattedProducts as $firstRow) {
			foreach ($firstRow as $recordRow) {

				$excelRepo->writeRow($sheet, $recordRow, $rowIndex++);
			}
		}
		//xlsx
		$fileName = 'product_report_' . now()->format('Y-m-d_H-i-s') . '.xlsx';
		return $excelRepo->downloadFile($fileName, $spreadsheet);
	}
}
