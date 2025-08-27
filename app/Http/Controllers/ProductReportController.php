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
	 *     path="/api/product-report",
	 *     summary="Get product report list",
	 *     description="Report of products display with id, sku, name, and branch name. Can search across product name, SKU, brand, status, and categories.",
	 *     tags={"Products Report"},
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
	 *     @OA\Property(property="brand", type="string", example="Brand Name"),
	 *     @OA\Property(property="category", type="string", example="category Name"),
	 *     @OA\Parameter(
	 *         name="search",
	 *         in="query",
	 *         description="Search term for filtering products by name, SKU, brand, store, or category",
	 *         required=false,
	 *         @OA\Schema(type="string", example="samsung")
	 *     ),
	 *		@OA\Parameter(
	 * 				name="status",
	 *				in="query",
	 *				description="Filter products by status (e.g., published, draft)",
	 *				required=false,
	 *				@OA\Schema(type="string", example="active")
	 *				),
	 *      @OA\Parameter(
	 * 				name="approved",
	 *				in="query",
	 *				description="Filter approved by status (e.g., 0, 1)",
	 *				required=false,
	 *				@OA\Schema(type="string", example="active")
	 *				),
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
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function index(Request $request, ExcelRepository $excelRepo)
	{
		$perPage = $request->input('page');
		$length = $request->input('length');
		$search = trim($request->input('search'));
		$status = trim($request->input('status'));
		$approved = trim($request->input('approved'));
		$brand = trim($request->input('brand'));
		$category = trim($request->input('category'));
		$sortBy = $request->input('sort_by', 'id');
		$sortDirection = $request->input('sort_direction', 'desc');

		/* Validate request data */
		$request->validate([
			'status' => 'required|string|in:draft,published',
			'approved' => 'string|in:0,1',
			// 'brand' => 'integer',
			'sort_by' => 'required',

		]);

		// Validate sort columns to prevent SQL injection
		$allowedSortColumns = ['id', 'name', 'sku', 'brand_id', 'status', 'gen_type', 'approved'];
		if (!in_array($sortBy, $allowedSortColumns)) {
			$sortBy = 'id'; // Default to id if invalid column
		}

		// Validate sort direction
		if (!in_array(strtolower($sortDirection), ['asc', 'desc'])) {
			$sortDirection = 'desc'; // Default to descending if invalid direction
		}

		$query = Product::with([
			'brand:id,name',
			'categories:id,name',
			'slug:id,key,reference_id',
			'productSuppliers',
			'vendors'
		])
			->select(['id', 'name', 'sku', 'images', 'brand_id', 'status', 'gen_type', 'approved']);



		/* Apply search if provided */

		// Apply status filter
		if ($status !== null) {
			$query->where('status', $status);
		}
		if ($approved !== null) {
			$query->where('approved', $approved);
		}
		if (!empty($category)) {
			$query->where(function ($q) use ($category) {
				$q->whereHas('categories', function ($categoryQuery) use ($category) {
					$categoryQuery->where('id', $category);
				});
			});
		}

		if (!empty($brand)) {
			$query->where(function ($q) use ($brand) {
				$q->whereHas('brand', function ($brandQuery) use ($brand) {
					$brandQuery->where('id', $brand);
				});
			});
		}

		if (!empty($search)) {
			$query->where(function ($q) use ($search) {
				$q->where('name', 'like', "%{$search}%")
					->orWhere('sku', 'like', "%{$search}%")
					->orWhereHas('brand', function ($brand) use ($search) {
						$brand->where('name', 'like', "%{$search}%");
					})

					->orWhereHas('categories', function ($categoryQuery) use ($search) {
						$categoryQuery->where('name', 'like', "%{$search}%");
					});
			});
		}
		$products = $query->orderBy($sortBy, $sortDirection);
		if ($length) {
			$products = $products->paginate($length);
		} else {
			$products = $products->get();
		}

		/* Formatting response */
		$formattedProducts = $products->map(function ($product) {

			return [
				'id' => $product->id,
				'name' => $product->name,
				'approved' => $product->approved,
				'sku' => $product->sku,
				'image' => ($imageUrls = json_decode($product->images, true)) && isset($imageUrls[0]) ? $imageUrls[0] : null,
				'brand' => optional($product->brand)->name,
				'status' => $product->status,
				'category_id' => $product->categories->pluck('id')->implode(', '),
				'category_name' => $product->categories->pluck('name')->implode(', '),
				'count_category' => $product->categories->count(),
			];
		});
	 
		$excelHeaders = ['id', 'name', 'approved', 'sku', 'image', 'brand', 'status', 'category_id', 'category_name', 'count_category'];

		$spreadsheet = $excelRepo->newSpreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setTitle('reports');

		/* Set headers */
		$excelRepo->setHeader($sheet, $excelHeaders);

		/* Fill data rows */
		$rowIndex = 2;
		foreach ($formattedProducts as $recordRow) {
			$excelRepo->writeRow($sheet, $recordRow, $rowIndex++);
		}

		$fileName = 'product_report_' . now()->format('Y-m-d_H-i-s') . '.xlsx';
		return $excelRepo->downloadFile($fileName, $spreadsheet);

	}


}
