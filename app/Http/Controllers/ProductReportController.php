<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Vendor;
use App\Repository\ExcelRepository;
use Illuminate\Support\Facades\Schema;
class ProductReportController extends Controller
{
	/**
	 * @OA\Post(
	 *     path="/api/product-report-export",
	 *     summary="Export product report",
	 *     description="Exports product report as JSON (preview) or as Excel (binary file).",
	 *     tags={"Products Report"},
	 *     @OA\RequestBody(
	 *         required=false,
	 *         @OA\JsonContent(
	 *             @OA\Property(property="status", type="string", enum={"all","published","draft"}, example="all"),
	 *             @OA\Property(property="approved", type="integer", enum={0,1}, example=1),
	 *             @OA\Property(property="type", type="string", enum={"Category","Brand"}, example="Category"),
	 *             @OA\Property(property="relational_id", type="integer", example=14),
	 *             @OA\Property(property="range_from", type="integer", example=1, description="Starting product index (must be >= 1)"),
	 *             @OA\Property(property="range_to", type="integer", example=500, description="Ending product index (max range allowed: 500 products)")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Product report export",
	 *         @OA\MediaType(
	 *             mediaType="application/json",
	 *             @OA\Schema(
	 *                 type="object",
	 *                 @OA\Property(property="success", type="boolean", example=true),
	 *                 @OA\Property(
	 *                     property="data",
	 *                     type="array",
	 *                     @OA\Items(
	 *                         @OA\Property(property="id", type="integer", example=101),
	 *                         @OA\Property(property="sku", type="string", example="SKU12345"),
	 *                         @OA\Property(property="name", type="string", example="Sample Product")
	 *                     )
	 *                 )
	 *             )
	 *         ),
	 *         @OA\MediaType(
	 *             mediaType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
	 *             @OA\Schema(type="string", format="binary")
	 *         )
	 *     ),
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
			$leafCategoryIds = $category->getLeafCategories()->pluck('id')->toArray();
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
	 * @OA\Post(
	 *     path="/api/product-benefit-report",
	 *     summary="Product has benefit features",
	 *     description="Report of products with id, sku, name, benefit features description, attribute count, price and graphics status. Supports filtering by status, category, brand, or vendor.",
	 *     tags={"Products Report"},
	 *
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="application/json",
	 *             @OA\Schema(
	 *                 type="object",
	 *                 required={"status"},
	 *                 @OA\Property(property="range_from", type="integer", example=1, description="Starting product index (must be >= 1)"),
	 *                 @OA\Property(property="range_to", type="integer", example=500, description="Ending product index (max range allowed: 500 products)"),
	 *                 @OA\Property(property="status", type="string", enum={"all","published","draft"}, example="all", description="Filter products by status"),
	 *                 @OA\Property(property="type", type="string", enum={"Category","Brand","Vendor"}, example="Category", description="Filter type"),
	 *                 @OA\Property(property="relational_id", type="integer", example=14, description="Provide brand ID, category ID, or vendor ID depending on type")
	 *             )
	 *         )
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
	 *                     @OA\Property(property="status", type="string", enum={"all","published","draft"}, example="published")
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
			'latestChildCategoryRelation:id,name'

		])->select(['id', 'name', 'sku', 'images', 'brand_id', 'status', 'gen_type', 'approved']);
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
			$leafCategoryIds = $category->getLeafCategories()->pluck('id')->toArray();
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
				'image' => ($imageUrls = json_decode($product->images, true)) && isset($imageUrls[0]) ? "Yes" : "No",
				'status' => $product->status,
				'brand' => optional($product->brand)->name,
				'category_name' => $product->categories->pluck('name')->implode(', '),
				'category_count' => $product->categories->count(),
				'attribute_count' => $product->productAttributes ? $product->productAttributes->count() : null,
				'price' => $firstSupplier ? (float) $firstSupplier->price : null,

			];

			return $data;

		});

		$excelHeaders = ['id', 'name', 'approved', 'sku', 'image', 'status', 'brand name', 'category_name', 'category_count', 'attribute_count', 'price'];

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


	/**
	 * @OA\Post(
	 *     path="/api/vendor-brand-product-export",
	 *     summary="Export product report",
	 *     description="Exports product report as Vendor wise brand and product export.",
	 *     tags={"Vendor wise brand and product export"},
	 *     @OA\RequestBody(
	 *         required=false,
	 *         @OA\JsonContent(
	 *             @OA\Property(property="vendor_id", type="integer", example=40),
	 *   		   @OA\Property(property="range_from", type="integer", example=1, description="Starting product index (must be >= 1)"),
	 *             @OA\Property(property="range_to", type="integer", example=500, description="Ending product index (max range allowed: 500 products)"),
	 * 			   @OA\Property(property="status", type="string", enum={"all","published","draft"}, example="all", description="Filter products by status"),
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Product report export",
	 *         @OA\MediaType(
	 *             mediaType="application/json",
	 *             @OA\Schema(
	 *                 type="object",
	 *                 @OA\Property(property="success", type="boolean", example=true),
	 *                 @OA\Property(
	 *                     property="data",
	 *                     type="array",
	 *                     @OA\Items(
	 *                         @OA\Property(property="vendor_id", type="integer", example=22)
	 *
	 *                     )
	 *                 )
	 *             )
	 *         ),
	 *         @OA\MediaType(
	 *             mediaType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
	 *             @OA\Schema(type="string", format="binary")
	 *         )
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function vendorBrandProductExport(Request $request, ExcelRepository $excelRepo)
	{
		/* Validate request data */
		$request->validate([
			'vendor_id' => 'required|integer',
			'range_from' => 'nullable|integer|min:1',
			'range_to' => 'nullable|integer|min:1',
			'status' => 'string|in:all,draft,published',
		]);

		$query = Product::with([
			'brand' => function ($q) {
				$q->select('id', 'name')->withCount('products');
			},
			'categories:id,name',
			'slug:id,key,reference_id',
			'productSuppliers',
			'vendors:id,name',
			'productAttributes.attributeDetails',
			'latestChildCategoryRelation:id,name'
		])->select(['id', 'name', 'sku', 'images', 'brand_id', 'status', 'gen_type', 'approved']);

		// Filter by vendor_id
		if ($request->filled('vendor_id')) {
			$query->whereHas('vendors', function ($q) use ($request) {
				$q->where('vendors.id', $request->vendor_id);
			});
		}
		if ($request->status != 'all') {
			$query->where('status', $request->status);
		}
		// Apply range filters if provided
		if ($request->range_from && $request->range_to) {
			$offset = $request->range_from - 1;
			$limit = $request->range_to - $request->range_from + 1;
			$query->offset($offset)->limit($limit);
		}

		$products = $query->orderBy('id', 'asc')->get();

		/* Formatting response */
		$formattedProducts = $products->map(function ($product) {
			$firstSupplier = $product->productSuppliers->first();
			return [
				'id' => $product->id,
				'name' => $product->name,
				'sku' => $product->sku,
				'brand' => optional($product->brand)->name,
				'status' => $product->status,
				'category_name' => $product->categories->pluck('name')->implode(', '),
				'vendor_name' => $product->vendors->pluck('name')->first(),
				 'vendor_sku' => $firstSupplier->vendor_sku ?? null,
			];
		});


		$excelHeaders = ['id', 'Product Name', 'sku', 'brand name', 'status','category_name','vendor_name','vendor_sku'];

		$spreadsheet = $excelRepo->newSpreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setTitle('Vendor Wise Products');

		/* Set headers */
		$excelRepo->setHeader($sheet, $excelHeaders);

		/* Fill data rows */
		$rowIndex = 2;
		foreach ($formattedProducts as $recordRow) {
			$excelRepo->writeRow($sheet, $recordRow, $rowIndex++);
		}

		// xlsx
		$fileName = 'vendor_wise_brand_product_' . now()->format('Y-m-d_H-i-s') . '.xlsx';
		return $excelRepo->downloadFile($fileName, $spreadsheet);
	}

}