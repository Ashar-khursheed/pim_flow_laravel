<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Repository\ExcelRepository;
use Illuminate\Support\Facades\Schema;
class ProductReportController extends Controller
{
	/**
	 * @OA\Get(
	 *     path="/api/product-report-export",
	 *     summary="Get product report list",
	 *     description="Report of products with id, sku, name, and branch name. Can search across product name, SKU, brand, status, categories, and approval status.",
	 *     tags={"Products Report"},
	 *     @OA\Parameter(
	 *         name="range_from",
	 *         in="query",
	 *         description="Starting product index (must be >= 1)",
	 *         required=false,
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\Parameter(
	 *         name="range_to",
	 *         in="query",
	 *         description="Ending product index (max range allowed: 500 products)",
	 *         required=false,
	 *         @OA\Schema(type="integer", example=5)
	 *     ),
	 *  @OA\Parameter(
	 *         name="status",
	 *         in="query",
	 *         description="Filter products by status (e.g., published, draft)",
	 *         required=true,
	 *         @OA\Schema(type="string", enum={"all","published","draft"}, example="all")
	 *     ),
	 *     @OA\Parameter(
	 *         name="approved",
	 *         in="query",
	 *         description="Filter approved by status (0 = not approved, 1 = approved)",
	 *         required=false,
	 *         @OA\Schema(type="integer", enum={0,1}, example="")
	 *     ),
	 *     @OA\Parameter(
	 *         name="type",
	 *         in="query",
	 *         description="Filter type (e.g., Category, Brand)",
	 *         required=false,
	 *         @OA\Schema(type="string", enum={"Category","Brand"}, example="Category")
	 *     ),
	 *     @OA\Parameter(
	 *         name="relational_id",
	 *         in="query",
	 *         description="Enter brand id, category id",
	 *         required=false,
	 *         @OA\Schema(type="string", example=121)
	 *     ),
	 *    
	 *
	 *     @OA\Response(
	 *         response=200,
	 *         description="Successful product report export",
	 *         @OA\JsonContent(
	 *             type="object",
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(
	 *                 property="data",
	 *                 type="array",
	 *                 @OA\Items(
	 *                     type="object",
	 *                     @OA\Property(property="id", type="integer", example=101),
	 *                     @OA\Property(property="sku", type="string", example="SKU12345"),
	 *                     @OA\Property(property="name", type="string", example="Sample Product"),
	 *                     @OA\Property(property="branch", type="string", example="Delhi Branch"),
	 *                     @OA\Property(property="status", type="string", enum={"all","publish","draft"}, example="publish"),
	 *                     @OA\Property(property="approved", type="integer", enum={0,1}, example=1)
	 *                 )
	 *             )
	 *         )
	 *     ),
	 *
	 *     @OA\Response(
	 *         response=400,
	 *         description="Invalid request parameters",
	 *         @OA\JsonContent(
	 *             type="object",
	 *             @OA\Property(property="success", type="boolean", example=false),
	 *             @OA\Property(property="message", type="string", example="Invalid range values")
	 *         )
	 *     ),
	 *
	 *     @OA\Response(
	 *         response=401,
	 *         description="Unauthorized - Invalid or missing token",
	 *         @OA\JsonContent(
	 *             type="object",
	 *             @OA\Property(property="success", type="boolean", example=false),
	 *             @OA\Property(property="message", type="string", example="Unauthorized")
	 *         )
	 *     ),
	 *
	 *     @OA\Response(
	 *         response=404,
	 *         description="No products found for the given filters",
	 *         @OA\JsonContent(
	 *             type="object",
	 *             @OA\Property(property="success", type="boolean", example=false),
	 *             @OA\Property(property="message", type="string", example="No products found")
	 *         )
	 *     ),
	 *
	 *     @OA\Response(
	 *         response=500,
	 *         description="Internal server error",
	 *         @OA\JsonContent(
	 *             type="object",
	 *             @OA\Property(property="success", type="boolean", example=false),
	 *             @OA\Property(property="message", type="string", example="Something went wrong while generating the report")
	 *         )
	 *     ),
	 *
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function index(Request $request, ExcelRepository $excelRepo)
	{
		/* Validate request data */
		$request->validate([
			'status' => 'string|in:all,draft,published',
			'approved' => 'string|in:0,1',
			'range_from' => 'required|integer|min:1',
			'range_to' => 'required|integer|gte:range_from|max:' . ($request->range_from + 500),
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
		if ($request->status != 'all') {
			$query->where('status', $request->status);
		}
		if ($request->approved != '') {
			$query->where('approved', $request->approved);
		}
		if ($request->type == "Brand") {
			$query->where('brand_id', $request->relational_id);
		} elseif ($request->type == "Category") {
			$category = Category::find($request->relational_id);
			if (!$category) {
				return response()->json([
					'success' => true,
					'message' => 'Category id not found',
				]);
			}
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

			$brands = "";
			if ($product->brand) {
				$brands = Brand::withCount('products')->where('id', $product->brand->id)->first();
			}


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
				'attribute_count' => $product->productAttributes ? $product->productAttributes->count() : null,

			];

			return $data;

		});

		$excelHeaders = ['id', 'name', 'approved', 'sku', 'image', 'brand id', 'brand', 'status', 'category_id', 'category_name', 'category_count', 'product_count', 'attribute_count'];

		$spreadsheet = $excelRepo->newSpreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setTitle('reports');

		/* Set headers */
		$excelRepo->setHeader($sheet, $excelHeaders);

		/* Fill data rows */
		$rowIndex = 2;
		if ($formattedProducts) {
			foreach ($formattedProducts as $firstRow) {
				if ($firstRow) {
					foreach ($firstRow as $recordRow) {

						$excelRepo->writeRow($sheet, $recordRow, $rowIndex++);
					}
				}
			}
		}
		//xlsx
		$fileName = 'product_report_' . now()->format('Y-m-d_H-i-s') . '.xlsx';
		return $excelRepo->downloadFile($fileName, $spreadsheet);
	}
	/**
	 * @OA\Get(
	 *     path="/api/product-benefit-report",
	 *     summary="Product has benefit features",
	 *     description="Report of products display with id, sku, name, benefit features description, attribute count, price and graphics yes no reports published draft products.",
	 *     tags={"Products Report"},
	 *     
	 *     @OA\Parameter(
	 *         name="range_from",
	 *         in="query",
	 *         description="Starting product index (must be >= 1)",
	 *         required=false,
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\Parameter(
	 *         name="range_to",
	 *         in="query",
	 *         description="Ending product index (max range allowed: 500 products)",
	 *         required=false,
	 *         @OA\Schema(type="integer", example=500)
	 *     ),
	 *   @OA\Parameter(
	 *         name="status",
	 *         in="query",
	 *         description="Filter products by status (e.g., published, draft)",
	 *         required=true,
	 *         @OA\Schema(type="string", enum={"all","published","draft"}, example="all")
	 *     ),
	 * 		@OA\Parameter(
	 *         name="type",
	 *         in="query",
	 *         description="Filter type (e.g., Category, Brand)",
	 *         required=false,
	 *         @OA\Schema(type="string", enum={"Category","Brand","Vendor"}, example="Category")
	 *     ),
	 *     @OA\Parameter(
	 *         name="relational_id",
	 *         in="query",
	 *         description="Enter brand id, category id",
	 *         required=false,
	 *         @OA\Schema(type="integer", example=121)
	 *     ),
	 *   
	 *     @OA\Response(
	 *         response=200,
	 *         description="Successful product benefit report",
	 *         @OA\JsonContent(
	 *             type="object",
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(
	 *                 property="data",
	 *                 type="array",
	 *                 @OA\Items(
	 *                     type="object",
	 *                     @OA\Property(property="id", type="integer", example=101),
	 *                     @OA\Property(property="sku", type="string", example="SKU12345"),
	 *                     @OA\Property(property="name", type="string", example="Sample Product"),
	 *                     @OA\Property(property="benefits", type="string", example="Lightweight, Durable"),
	 *                     @OA\Property(property="attribute_count", type="integer", example=5),
	 *                     @OA\Property(property="price", type="number", format="float", example=499.99),
	 *                     @OA\Property(property="graphics", type="string", enum={"yes","no"}, example="yes"),
	 *                     @OA\Property(property="status", type="string", enum={"all","publish","draft"}, example="publish")
	 *                 )
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=400,
	 *         description="Invalid request parameters",
	 *         @OA\JsonContent(
	 *             type="object",
	 *             @OA\Property(property="success", type="boolean", example=false),
	 *             @OA\Property(property="message", type="string", example="Invalid range values")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=401,
	 *         description="Unauthorized - Invalid or missing token",
	 *         @OA\JsonContent(
	 *             type="object",
	 *             @OA\Property(property="success", type="boolean", example=false),
	 *             @OA\Property(property="message", type="string", example="Unauthorized")
	 *         )
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */

	public function exportBenefitReport(Request $request, ExcelRepository $excelRepo)
	{
		/* Validate request data */
		$request->validate([
			'status' => 'string|in:all,draft,published',
			'type' => 'required|string|in:Brand,Category',
			'relational_id' => 'required|integer',
			'range_from' => 'required|integer|min:1',
			'range_to' => 'required|integer|gte:range_from|max:' . ($request->range_from + 500)
		]);

		$query = Product::with([
			'brand:id,name',
			'categories:id,name',
			'slug:id,key,reference_id',
			'productSuppliers',
			'vendors',
			'productAttributes.attributeDetails',
			'latestChildCategoryRelation:id,name',


		])->select(['id', 'name', 'sku', 'images', 'brand_id', 'status', 'gen_type', 'approved', 'benefits_features']);
		/* Apply relational filters */
		if ($request->status != 'all') {
			$query->where('status', $request->status);
		}

		if ($request->type == "Brand") {
			$query->where('brand_id', $request->relational_id);
		} elseif ($request->type == "Category") {
			$category = Category::find($request->relational_id);
			if (!$category) {
				return response()->json([
					'success' => true,
					'message' => 'Category id not found',
				]);
			}
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
			$firstSupplier = $product->productSuppliers->first();
			$data[] = [
				'id' => $product->id,
				'name' => $product->name,
				'approved' => $product->approved,
				'sku' => $product->sku,
				'image' => isset($product->images) ? "Yes" : "No",
				'documents' => isset($product->documents) ? "Yes" : "No",
				'video' => isset($product->video_path) ? "Yes" : "No",
				'status' => $product->status,
				'brand' => optional($product->brand)->name,
				'category_name' => $product->categories->pluck('name')->implode(', '),
				'category_count' => $product->categories->count(),
				'atribute_count' => $product->productAttributes ? $product->productAttributes->count() : null,
				'benefit_count' => $product->benefits_features ? count(json_decode($product->benefits_features, true)) : null,
				'price' => $firstSupplier ? (float) $firstSupplier->price : null,

			];

			return $data;

		});

		$excelHeaders = ['id', 'name', 'approved', 'sku', 'image', 'documents', 'video', 'status', 'brand name', 'category_name', 'category_count', 'atribute_count', 'benefit_count', 'price'];

		$spreadsheet = $excelRepo->newSpreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setTitle('reports');

		/* Set headers */
		$excelRepo->setHeader($sheet, $excelHeaders);

		/* Fill data rows */
		$rowIndex = 2;
		if ($formattedProducts) {
			foreach ($formattedProducts as $firstRow) {
				if ($firstRow) {
					foreach ($firstRow as $recordRow) {

						$excelRepo->writeRow($sheet, $recordRow, $rowIndex++);
					}
				}
			}
		}
		//xlsx
		$fileName = 'product_benefit_' . now()->format('Y-m-d_H-i-s') . '.xlsx';
		return $excelRepo->downloadFile($fileName, $spreadsheet);
	}

}