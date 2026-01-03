<?php

namespace App\Http\Controllers\FrontEnd;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use App\Models\FrontEnd\AlternateProduct;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
class CompareProductController extends Controller
{
	/**
	 * @OA\Post(
	 *     path="/api/frontend/compare-table-product",
	 *     summary="Fetch product compare by ID",
	 *     description="Accepts a single product ID and returns its details",
	 *     tags={"Front Compare Products"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             type="object",
	 *             @OA\Property(
	 *                 property="product_id",
	 *                 type="integer",
	 *                 example=101,
	 *                 description="Single product ID to compare"
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Successful response",
	 *         @OA\JsonContent(
	 *             type="object",
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="message", type="string", example="Product retrieved successfully"),
	 *             @OA\Property(
	 *                 property="data",
	 *                 type="object",
	 *                 @OA\Property(property="id", type="integer", example=101),
	 *                 @OA\Property(property="sku", type="string", example="SKU12345"),
	 *                 @OA\Property(property="name", type="string", example="Test Product")
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=400,
	 *         description="Bad request"
	 *     )
	 * )
	 */
	public function getCompareTableProduct(Request $request)
	{
		$request->validate([
			'product_id' => "required|integer"
		]);

		$mainProductId = trim($request->input('product_id'));

		// get alternates
		$alternateProduct = AlternateProduct::where('product_id', $mainProductId)
		->orderBy('priority', 'asc')
		->get();

		// always include the main product
		$allProducts = collect();
		$allProducts->push((object) [
			'id' => null, // alt table id not relevant for main
			'status' => null,
			'product_alternate_id' => $mainProductId,
			'priority' => 0,
			'similarity' => null,
			'order' => 0,
			'created_at' => null,
			'updated_by' => null,
			'created_by' => null,
			'rejected_by' => null,
			'reason' => null,
			'brand' => null,
		]);

		// add alternates
		if ($alternateProduct->count() > 0) {
			$allProducts = $allProducts->merge($alternateProduct);
		}

		if ($allProducts->count() > 0) {
			$formattedProducts = $allProducts->map(function ($product) use ($mainProductId) {
				$products = Product::with([
					'brand:id,name',
					'categories:id,name',
					'productAttributes.attributeDetails',
					'productAttributes.measurementUnit',
					'reviews:id,product_id,star',
					'productSuppliers',
				])
				->where('id', $product->product_alternate_id ?? $mainProductId)
				->select([
					'id',
					'name',
					'sku',
					'status',
					'images',
					'currency_id',
					'barcode',
				])
				->first();

				if (!$products) {
					return null;
				}

				$firstSupplier = $products->productSuppliers->first();
				$product_attributes = [];
				foreach ($products->productAttributes as $attr) {
					$product_attributes[] = [
						'attribute_id' => $attr->attribute_id,
						'attribute_name' => $attr->attributeDetails->name ?? null,
						'attribute_value' => $attr->attribute_value,
						'measurement_unit_id' => $attr->measurement_unit_id,
						'measurement_unit_name' => $attr->measurementUnit->name ?? null,
					];
				}

				return [
					'id' => $products->id,
					'product_name' => $products->name,
					'product_sku' => $products->sku,
					'product_status' => $products->status,
					'product_images' => is_array($products->images)
					? $products->images
					: (is_array($decoded = json_decode($products->images, true)) ? $decoded : null),

					'vendor_sku' => $firstSupplier->vendor_sku ?? null,
					'price' => $firstSupplier ? (float) $firstSupplier->price : null,
					'sale_price' => $firstSupplier ? (float) $firstSupplier->sale_price : null,
					'original_price' => $firstSupplier ? (float) $firstSupplier->price : null,
					'front_sale_price' => $firstSupplier ? (float) $firstSupplier->sale_price : null,
					'best_price' => $firstSupplier ? (float) $firstSupplier->price : null,
					'per_unit_price' => $products->per_unit_price ?? null,
					'vendor_id' => $firstSupplier->vendor_id ?? null,
					'map' => $firstSupplier ? (float) $firstSupplier->map : null,
					'inventory' => $firstSupplier->inventory ?? null,
					'in_stock' => $firstSupplier->in_stock ?? null,
					'delivery_days' => $firstSupplier->delivery_days ?? null,
					'return_policy' => $firstSupplier->return_policy ?? null,
					'free_shipping' => $firstSupplier->free_shipping ?? null,
					'totalReviews' => $products->reviews?->count() ?? 0,
					'avgRating' => $products->reviews?->count() > 0 ? $products->reviews->avg('star') : null,
					'warranty_information' => $firstSupplier->warranty_information ?? null,
					'min_quantity' => $firstSupplier->min_quantity ?? 0,
					'is_fixed' => $firstSupplier->is_fixed ?? 0,
					'quote_available' => $product->quote_available ?? null,
					'isRequired' => $product->isRequired,
					'alt_id' => $product->id,
					'alt_status' => $product->status,
					'product_alternate_id' => $product->product_alternate_id ?? $mainProductId,
					'priority' => $product->priority,
					'similarity' => $product->similarity,
					'order' => $product->order,
					'alt_created' => $product->created_at,
					'alt_updated_by' => $product->updated_by,
					'alt_created_by' => $product->created_by,
					'alt_rejected_by' => $product->rejected_by,
					'reason' => $product->reason,

					'brand' => $products->brand ? $products->brand->name : null,
					'product_attributes' => $product_attributes,
					'categories' => $products->categories->pluck('name'),
				];
			})->filter();

			return response()->json([
				'success' => true,
				'message' => 'Product & alternates fetched successfully',
				'data' => $formattedProducts->values(),
			]);
		}

		return response()->json([
			'success' => false,
			'message' => 'Product not found',
			'data' => [],
		]);
	}

	/**
	 * @OA\Post(
	 *     path="/api/frontend/python/compare-alternate-products",
	 *     summary="Run Python Alternate Product Generator",
	 *     tags={"Alternate Create Products Python"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             @OA\Property(
	 *                 property="product_id_list",
	 *                 type="array",
	 *                 @OA\Items(type="integer", example=1)
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Job executed",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="status", type="string", example="success"),
	 *             @OA\Property(property="message", type="string", example="Alternate products saved to DB")
	 *         )
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function compareAlternateProducts(Request $request)
	{
		try {
			// Validate input
			$request->validate([
				'product_id_list' => 'required|array|min:1'
			]);
			$productIds = $request->input('product_id_list');


			// Path to your Python script
			$scriptPath = base_path('app/Script/alternate_official_US.py');

			if (!file_exists($scriptPath)) {
				return response()->json([
					'success' => false,
					'error' => 'Python script not found',
					'details' => $scriptPath
				], 500);
			}

			// Python executable

			//  $pythonCmd = "C:\Program Files\Python313\python.exe";
			$workingDirectory = base_path('app/Script');
			// Pass JSON input to Python script via stdin
			$inputJson = json_encode(['product_id_list' => $productIds]);
			$pythonCmd = env('PYTHON_PATH', base_path('venv/bin/python'));

			$process = new Process([$pythonCmd, $scriptPath], $workingDirectory, null, $inputJson, 300);
			$process->run();

			// Check if Python script ran successfully
			if (!$process->isSuccessful()) {
				$errorOutput = $process->getErrorOutput();
				Log::error("Python script execution failed", [
					'error' => $errorOutput,
					'command' => $process->getCommandLine()
				]);

				return response()->json([
					'success' => false,
					'error' => 'Python script execution failed',
					'details' => $errorOutput
				], 500);
			}

			// Success message (Python handles DB updates)
			return response()->json([
				'success' => true,
				'message' => 'Python script executed successfully',
				'output' => json_decode($process->getOutput()),
			]);

		} catch (\Exception $e) {
			Log::error("Failed to run Python script", [
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			]);

			return response()->json([
				'success' => false,
				'error' => 'Internal server error',
				'details' => $e->getMessage()
			], 500);
		}
	}
}
