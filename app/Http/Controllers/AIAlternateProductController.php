<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\FrontEnd\AlternateProduct;
use Illuminate\Support\Facades\DB;
use App\Models\Product;
use App\Models\Category;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
class AIAlternateProductController extends Controller
{
	/**
	 * @OA\Get(
	 *     path="/api/ai-products-alternates",
	 *     summary="Get a list of Products AI alternates",
	 *     description="Report of products display with id, sku, name, and branch name. Can search across product name, SKU, brand, status, and categories.",
	 *     tags={"Products AI alternates"},
	 *      security={{"bearerAuth":{}}},
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
	public function index(Request $request)
	{

		$userId = Auth::id();
		$isUserLoggedIn = $userId !== null;

		Log::info('Fetching alternate products for:', ['product_id' => "", 'user_id' => $userId]);


		$response = [];
		$baseValue = 150;
		$monday = strtotime("last monday midnight");
		$now = strtotime("now");
		$sunday = strtotime("next sunday", $monday);
		$diff = date_diff(date_create(date('Y-m-d', $monday)), date_create(date('Y-m-d', $now)));
		$baseValue *= ($diff->days + 1);
		//$monday  = strtotime($monday.'00:00:00');
		//$sunday  = strtotime($sunday.'23:59:59');
		// FIND WEEKLY Total Suggestions
		$alternateProduct = Product::whereHas('alternateProducts', function ($q) use ($monday, $sunday) {
			$q->whereBetween(DB::raw('DATE(created_at)'), [
				date('Y-m-d', $monday),
				date('Y-m-d', $sunday)
			]);
		});
		$response['weekly_alternative']['total_suggestions'] = $alternateProduct->count();
		$response['weekly_alternative']['total_suggestions_percent'] = round(($alternateProduct->count() / $baseValue) * 100, 2);

		// FIND WEEKLY Total pending review
		$alternateProduct = Product::whereHas('alternateProducts', function ($q) use ($monday, $sunday) {
			$q->where('status', 'like', 'pending')
			->whereBetween(DB::raw('DATE(created_at)'), [
				date('Y-m-d', $monday),
				date('Y-m-d', $sunday)
			]);
		});

		$response['weekly_alternative']['pending_review'] = $alternateProduct->count();
		$response['weekly_alternative']['pending_percent'] = round(($alternateProduct->count() / $baseValue) * 100, 2);

		// FIND WEEKLY Total Approved
		$alternateProduct = Product::whereHas('alternateProducts', function ($q) use ($monday, $sunday) {
			$q->where('status', 'like', 'approved')
			->whereBetween(DB::raw('DATE(created_at)'), [
				date('Y-m-d', $monday),
				date('Y-m-d', $sunday)
			]);
		});
		$response['weekly_alternative']['approved'] = $alternateProduct->count();
		$response['weekly_alternative']['approved_percent'] = round(($alternateProduct->count() / $baseValue) * 100, 2);

		// FIND WEEKLY Total Rejected
		$alternateProduct = Product::whereHas('alternateProducts', function ($q) use ($monday, $sunday) {
			$q->where('status', 'like', 'rejected')
			->whereBetween(DB::raw('DATE(created_at)'), [
				date('Y-m-d', $monday),
				date('Y-m-d', $sunday)
			]);
		});
		$response['weekly_alternative']['rejected '] = $alternateProduct->count();
		$response['weekly_alternative']['rejected_percent'] = round(($alternateProduct->count() / $baseValue) * 100, 2);



		// FIND WEEKLY Total Accuracy
		$alternateProduct = Product::whereHas('alternateProducts', function ($q) use ($monday, $sunday) {
			$q->where('status', 'like', 'accuracy')
			->whereBetween(DB::raw('DATE(created_at)'), [
				date('Y-m-d', $monday),
				date('Y-m-d', $sunday)
			]);
		});
		$response['weekly_alternative']['accuracy '] = $alternateProduct->count();
		$response['weekly_alternative']['accuracy_percent'] = round(($alternateProduct->count() / $baseValue) * 100, 2);
		if (empty($response)) {
			return response()->json([
				'success' => true,
				'message' => 'No alternate products found for this product',
				'data' => [],
			]);
		}

		return response()->json([
			'success' => true,
			'data' => $response,
			'message' => 'Alternate products retrieved successfully',
		]);
	}

	/**
	 * @OA\Post(
	 *     path="/api/ai-alternate-status",
	 *     summary="Update AI Alternate Status",
	 *     tags={"Products AI alternates"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="application/x-www-form-urlencoded",
	 *             @OA\Schema(
	 *                 required={"id","status"},
	 *                 @OA\Property(
	 *                     property="id",
	 *                     type="integer",
	 *                     example=1,
	 *                     description="Alternate product ID"
	 *                 ),
	 *                 @OA\Property(
	 *                     property="status",
	 *                     type="string",
	 *                     enum={"approve", "reject", "pending", "review"},
	 *                     example="approve",
	 *                     description="Status of the alternate product"
	 *                 )
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Status updated successfully",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="status", type="string", example="success"),
	 *             @OA\Property(property="message", type="string", example="Status updated successfully")
	 *         )
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function alternateStatus(Request $request)
	{

		$request->validate([
			'id' => 'required|integer|exists:alternate_products,id',
			'status' => 'required|string|in:approve,reject,pending',
		]);
		$rejected_by = 0;
		if ($request->status == 'reject') {
			$rejected_by = auth()->id();
		}

		AlternateProduct::where('id', $request->id)
		->update([
			'status' => $request->status,
			'updated_at' => now(),
			'updated_by' => auth()->id(),
			'rejected_by' => $rejected_by,
		]);


		return response()->json([
			'status' => 'success',
			'message' => 'Status updated successfully to ' . $request->status,
		], 200);
	}

	/**
	 * @OA\Post(
	 *     path="/api/ai-alternate-priority",
	 *     summary="Update AI Alternate priority",
	 *     tags={"Products AI alternates"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="application/x-www-form-urlencoded",
	 *             @OA\Schema(
	 *                 required={"id","priority"},
	 *                 @OA\Property(
	 *                     property="id",
	 *                     type="integer",
	 *                     example=1,
	 *                     description="Alternate product ID"
	 *                 ),
	 *                 @OA\Property(
	 *                     property="priority",
	 *                     type="string",
	 *                     enum={"1", "2", "3", "4","5","6","7","8","9"},
	 *                     example="1",
	 *                     description="priority of the alternate product"
	 *                 )
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="priority updated successfully",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="priority", type="string", example="success"),
	 *             @OA\Property(property="message", type="string", example="priority updated successfully")
	 *         )
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function alternatePriority(Request $request)
	{

		$request->validate([
			'id' => 'required|integer|exists:alternate_products,id',
			'priority' => 'required|string|in:1,2,3,4,5,6,7,8,9',
		]);


		AlternateProduct::where('id', $request->id)
		->update([
			'priority' => $request->priority,
			'updated_at' => now(),
			'updated_by' => auth()->id(),
		]);


		return response()->json([
			'status' => 'success',
			'message' => 'priority updated successfully to ' . $request->priority,
		], 200);
	}

	/**
	 * @OA\Get(
	 *     path="/api/get-ai-alternates",
	 *     summary="Get a list of Products AI alternates",
	 *     description="Report of products display with id, sku, name, and branch name. Can search across product name, SKU, brand, status, and categories.",
	 *     tags={"Products AI alternates"},
	 *     security={{"bearerAuth":{}}},
	 *
	 *     @OA\Parameter(
	 *         name="search",
	 *         in="query",
	 *         description="Search by SKU",
	 *         required=false,
	 *         @OA\Schema(type="string", example="")
	 *     ),
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
	 *     @OA\Parameter(
	 *         name="rejection",
	 *         in="query",
	 *         description="Search by rejection",
	 *         required=false,
	 *         @OA\Schema(type="string", example="")
	 *     ),
	 *     @OA\Parameter(
	 *         name="reviewers",
	 *         in="query",
	 *         description="Search by reviewers",
	 *         required=false,
	 *         @OA\Schema(type="string", example="")
	 *     ),
	 *     @OA\Parameter(
	 *         name="category",
	 *         in="query",
	 *         description="Filter by Category id",
	 *         required=false,
	 *         @OA\Schema(type="string", example="")
	 *     ),
	 *
	 *     @OA\Response(
	 *         response=200,
	 *         description="Successful response"
	 *     ),
	 *     @OA\Response(
	 *         response=400,
	 *         description="Bad request"
	 *     )
	 * )
	 */
	public function getAiAlternateProducts(Request $request)
	{
		$request->validate([
			'range_from' => 'integer|min:1',
			'range_to' => 'integer|gte:range_from|max:' . ($request->range_from + 500),

		]);
		$locale = $request->locale ?? 'en';

		$perPage = $request->input('per_page', 50);
		$sortBy = $request->input('sort_by', 'id');
		$sortDirection = $request->input('sort_direction', 'desc');

		// Validate sort columns to prevent SQL injection
		$allowedSortColumns = ['id', 'name', 'sku', 'brand_id', 'status', 'gen_type', 'approved'];
		if (!in_array($sortBy, $allowedSortColumns)) {
			$sortBy = 'id'; // Default to id if invalid column
		}

		// Validate sort direction
		if (!in_array(strtolower($sortDirection), ['asc', 'desc'])) {
			$sortDirection = 'desc'; // Default to descending if invalid direction
		}
		$query = DB::table('ec_products as ec')
		->leftJoin('product_translations as pt', function ($join) use ($locale) {
			$join->on('pt.product_id', '=', 'ec.id')
			->where('pt.locale', '=', $locale);
		})
		->join(DB::raw("(
			SELECT m1.id AS alt_id, m1.product_id, m1.status AS alt_status,m1.product_alternate_id,m1.priority,m1.similarity,m1.order,m1.created_at as alt_created,m1.updated_at as alt_updated_by, m1.created_by as alt_created_by,m1.rejected_by as alt_rejected_by,m1.reason
			FROM `alternate_products` m1
			LEFT JOIN alternate_products m2
			ON (m1.product_id = m2.product_id AND m1.id < m2.id)
			WHERE m2.id IS NULL
			AND m1.status IN ('pending', 'approved')
		) as fu"), 'ec.id', '=', DB::raw('fu.product_id'))
		->select([
			'ec.id AS p_id',
				// 'ec.name AS product_name',
			DB::raw("COALESCE(pt.name, ec.name) AS product_name"),
			'ec.sku AS product_sku',
			'ec.status AS product_status',
				// 'ec.images AS product_images',
				DB::raw("COALESCE(pt.images, ec.images) AS product_images"), // ✅ optional image localization
				'fu.alt_id',
				'fu.alt_status',
				'fu.product_alternate_id',
				'fu.priority',
				'fu.similarity',
				'fu.order',
				'fu.alt_created',
				'fu.alt_updated_by',
				'fu.alt_created_by',
				'fu.alt_rejected_by',
				'fu.reason'
			])
		->orderBy('fu.alt_id', 'desc');
		// Apply status filter
		if (!empty($request->input('rejection'))) {
			$query->where('reason', $request->input('rejection'));
		}
		if (!empty($request->input('reviewers'))) {
			$query->where('status', $request->input('reviewers'));
		}


		if ($request->input('category')) {
			$category = Category::find($request->input('category'));
			$leafCategoryIds = $category->getLeafCategories()->pluck('id')->toArray();
			$query->whereHas('categories', function ($q) use ($leafCategoryIds) {
				$q->whereIn('category_id', $leafCategoryIds);
			});
		}

		if ($request->input('search')) {
			$search = $request->input('search');
			$query->where(function ($q) use ($search) {
				$q->where('name', 'like', "%{$search}%")
				->orWhere('sku', 'like', "%{$search}%")
				->orWhereHas('brand', function ($brandQuery) use ($search) {
					$brandQuery->where('name', 'like', "%{$search}%");
				})

				->orWhereHas('categories', function ($categoryQuery) use ($search) {
					$categoryQuery->where('name', 'like', "%{$search}%");
				});
			});
		}

		$products = $query->whereNotNull('status')
		->offset($request->range_from - 1)
		->limit($request->range_to - $request->range_from + 1)
		->paginate($perPage);
		/* Formatting response */
		$formattedProducts = $products->map(function ($product) {

			return [
				'id' => $product->p_id,
				'product_name' => $product->product_name,
				'product_sku' => $product->product_sku,
				'product_status' => $product->product_status,
				'product_images' => $product->product_images,
				'alt_id' => $product->alt_id,
				'alt_status' => $product->alt_status,
				'product_alternate_id' => $product->product_alternate_id,
				'priority' => $product->priority,
				'similarity' => $product->similarity,
				'order' => $product->order,
				'alt_created' => $product->alt_created,
				'alt_updated_by' => $product->alt_updated_by,
				'alt_created_by' => $product->alt_created_by,
				'alt_rejected_by' => $product->alt_rejected_by,
				'reason' => $product->reason

			];
		});


		return response()->json([
			'success' => true,
			'message' => 'Products retrieved successfully',
			'data' => $formattedProducts,
			'pagination' => [
				'total' => $products->total(),
				'per_page' => $products->perPage(),
				'current_page' => $products->currentPage(),
				'last_page' => $products->lastPage(),
				'next_page_url' => $products->nextPageUrl(),
				'prev_page_url' => $products->previousPageUrl(),
			],
		]);
	}

	/**
	 * @OA\Post(
	 *     path="/api/get-product-alternative-comparison",
	 *     summary="Get a list of Products Alternative comparison by product ID",
	 *     description="Report of products display with id, sku, name, and branch name. Can search across product name, SKU, brand, status, and categories",
	 *     tags={"Products AI alternates"},
	 *      security={{"bearerAuth":{}}},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             type="object",
	 *             @OA\Property(
	 *                 property="product_id",
	 *                 type="integer",
	 *                 example=1795,
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
	public function getProdutAlternativeComparison(Request $request)
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

					// alternate table fields (null if main)
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
	 *     path="/api/python/create-one-product-alternate",
	 *     summary="Python create one Alternate Recommendation",
	 *     tags={"Products AI alternates"},
	 *     @OA\Parameter(
	 *         name="product_id",
	 *         in="query",
	 *         required=true,
	 *         description="Enter the Product ID for which to create an alternate recommendation",
	 *         @OA\Schema(type="integer", example=1683)
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Job executed successfully",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="status", type="string", example="success"),
	 *             @OA\Property(property="message", type="string", example="Alternate products saved to DB")
	 *         )
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function createOneAlternateProductsByPthon(Request $request)
	{
		try {
			// Validate input
			$request->validate([
				'product_id' => 'required|integer|min:1'
			]);
			$productIds = $request->input('product_id');

			// Path to Python script
			$scriptPath = base_path('app/Script/one_create_alternate.py');

			if (!file_exists($scriptPath)) {
				return response()->json([
					'success' => false,
					'error' => 'Python script not found',
					'details' => $scriptPath
				], 500);
			}

			$workingDirectory = base_path('app/Script');
			// Pass JSON input to Python script via stdin
			$inputJson = json_encode(['product_id_list' => $productIds]);
			//$pythonCmd = 'C:\Program Files\Python313\python.exe';
			//$pythonCmd = env('PYTHON_PATH', base_path('venv/Scripts/python.exe'));
			$pythonCmd = env('PYTHON_PATH', base_path('venv/bin/python'));
			$process = new Process([$pythonCmd, $scriptPath], $workingDirectory, null, $inputJson, 300);
			$process->run();


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

			return response()->json([
				'success' => true,
				'message' => 'Python script executed successfully',
				'output' => json_decode($process->getOutput(), true),
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
		}catch (ProcessFailedException $exception) {
		// Get standard error
			$error = $exception->getMessage();
			return response()->json([
				'success' => false,
				'error' => $error
			]);
		}
	}

	/**
	 * @OA\Post(
	 *     path="/api/python/create-alternate-recommendation",
	 *     summary="Python all Create Alternate Recommendation",
	 *     tags={"Products AI alternates"},
	 *     @OA\Parameter(
	 *         name="product_id",
	 *         in="query",
	 *         required=true,
	 *         description="Enter Product ID or 'all'",
	 *         @OA\Schema(type="string", example="1683")
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
	public function createAlternateProductsByPthon(Request $request)
	{
		try {
			// Validate input
			$request->validate([
				'product_id' => 'required'
			]);
			$productIds = $request->input('product_id');


			// Path to your Python script
			$scriptPath = base_path('app/Script/create_alternate_products.py');

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
