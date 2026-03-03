<?php

namespace App\Http\Controllers;

use Doctrine\Common\Annotations\Annotation\Required;
use Illuminate\Http\Request;
use App\Models\ProductAccessory;
use App\Models\Product;
use App\Models\AccessoryItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

use App\Services\ExcelImporterService;
use App\Repository\ExcelRepository;
use App\Jobs\ImportProductAccessoryJob;

class ProductAccessoriesController extends Controller
{
	/**
	 * @OA\Get(
	 *     path="/api/product-accessories",
	 *     summary="Get list of product accessories",
	 *     tags={"Product Accessories"},
	 *     @OA\Parameter(
	 *         name="product_id",
	 *         in="query",
	 *         required=false,
	 *         description="Filter by product ID",
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\Parameter(
	 *         name="isapproved",
	 *         in="query",
	 *         required=false,
	 *         description="Filter by approver status (0 or 1)",
	 *         @OA\Schema(type="string", enum={"false", "true","all"}, example="all")
	 *     ),
	 *     @OA\Parameter(
	 *         name="search",
	 *         in="query",
	 *         required=false,
	 *         description="Search by id, name,sku",
	 *         @OA\Schema(type="string", example="")
	 *     ),
	 *     @OA\Parameter(
	 *         name="page",
	 *         in="query",
	 *         required=false,
	 *         description="Page number for pagination",
	 *         @OA\Schema(type="integer", example=1)
	 *     ),
	 *     @OA\Parameter(
	 *         name="per_page",
	 *         in="query",
	 *         required=false,
	 *         description="Number of records per page",
	 *         @OA\Schema(type="integer", minimum=1, example=10)
	 *     ),
	 *     @OA\Parameter(
	 *         name="sort_by",
	 *         in="query",
	 *         required=false,
	 *         description="Column to sort by (id, name, isapprover)",
	 *         @OA\Schema(type="string", enum={"id", "name", "isapprover"}, example="id")
	 *     ),
	 *     @OA\Parameter(
	 *         name="sort_direction",
	 *         in="query",
	 *         required=false,
	 *         description="Sort direction (asc or desc)",
	 *         @OA\Schema(type="string", enum={"asc", "desc"}, example="desc")
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Successful operation",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="message", type="string", example="Product accessories retrieved successfully"),
	 *             @OA\Property(property="data", type="object")
	 *         )
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function index(Request $request): JsonResponse
	{
		try {
			$query = ProductAccessory::with(['items', 'product', 'createdBy', 'updatedBy', 'approvedBy']);

			// Filter by product_id if provided
			if ($request->input('product_id') != "" && $request->input('product_id') != null) {
				$query->where('product_id', $request->input('product_id'));
			}

			// Filter by approval status if not 'all'
			if ($request->input('isapproved') != "all") {
				$query->where('isapproved', $request->input('isapproved'));
			}

			// Enhanced search logic for name, SKU, or ID
			if ($request->filled('search')) {
				$search = $request->input('search');
				$query->where(function ($q) use ($search) {
					// Search by accessory name
					$q->where('name', 'like', "%{$search}%")
					->orWhere('id', 'like', "%{$search}%")
					->orWhereHas('product', function ($q2) use ($search) {
							$q2->where('sku', 'like', "%{$search}%"); // ✅ Search by product SKU
						});
				});
			}

			// Define searchable and sortable columns
			$searchableColumns = ['id', 'product_id', 'name', 'sku', 'isapproved'];
			$sortableColumns = array_merge($searchableColumns, ['created_at', 'updated_at']);
			$sortBy = in_array($request->input('sort_by'), $sortableColumns) ? $request->input('sort_by') : 'id';
			$sortDir = strtolower($request->input('sort_direction', 'desc')) === 'asc' ? 'asc' : 'desc';

			// Pagination parameters
			$perPage = $request->get('per_page', 10);
			$page = $request->get('page', 1);

			$totalRecords = (clone $query)->count();
			$totalPages = (int) ceil($totalRecords / $perPage);

			if ($page > $totalPages && $totalPages > 0) {
				$page = 1;
			}

			$accessories = $query->orderBy($sortBy, $sortDir)
			->offset(($page - 1) * $perPage)
			->limit($perPage)
			->get();

			// Format the response data
			$formattedProducts = $accessories->map(function ($accessory) {
				$accessoryItems = $accessory->items->map(function ($item) {
					return [
						'id' => $item->id,
						'name' => $item->name,
						'cost_price' => $item->cost_price,
						'price' => $item->price,
					];
				});

				return [
					'product_id' => $accessory->product_id,
					'sku' => $accessory->product?->sku ?? null,
					'accessory_id' => $accessory->id,
					'name' => $accessory->name,
					'product_name' => $accessory->product->name,
					'isapproved' => $accessory->isapproved,
					'isRequired' => $accessory->isRequired,
					'approved_by' => $accessory->approvedBy?->username ?? null,
					'created_by' => $accessory->createdBy?->username ?? null,
					'updated_by' => $accessory->updatedBy?->username ?? null,
					'created_at' => date('d-m-Y', strtotime($accessory->created_at)),
					'updated_at' => date('d-m-Y', strtotime($accessory->updated_at)),
					'accessory_item' => $accessoryItems,
				];
			});

			return response()->json([
				'success' => true,
				'message' => __("msg_rec_list"),
				'data' => [
					'current_page' => (int) $page,
					'per_page' => (int) $perPage,
					'total_pages' => $totalPages,
					'total_records' => $totalRecords,
					'data' => $formattedProducts,
				]
			]);
		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Failed to retrieve product accessories',
				'error' => $e->getMessage()
			], 500);
		}
	}


	/**
	 * @OA\Post(
	 *     path="/api/product-accessories",
	 *     summary="Create a new product accessory",
	 *     tags={"Product Accessories"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="application/json",
	 *             @OA\Schema(
	 *                 required={"product_id", "name", "accessories"},
	 *                 @OA\Property(property="product_id", type="integer", example=1),
	 *                 @OA\Property(property="name", type="string", example="Door"),
	 *                 @OA\Property(
	 *                     property="accessories",
	 *                     type="array",
	 *                     @OA\Items(
	 *                         type="object",
	 *                         required={"name","price"},
	 *                         @OA\Property(property="name", type="string", example="left"),
	 *                         @OA\Property(property="price", type="number", format="float", example=44),
	 *                         @OA\Property(property="cost_price", type="number", format="float", example=40)
	 *                     ),
	 *                     example={
	 *                         {"name":"left","price":44,"cost_price":40},
	 *                         {"name":"right","price":45,"cost_price":41},
	 *                         {"name":"top","price":50,"cost_price":45},
	 *                         {"name":"button","price":52,"cost_price":48}
	 *                     }
	 *                 ),
	 *                 @OA\Property(property="isapproved", type="boolean", example=0),
	 *                 @OA\Property(property="isRequired", type="boolean", example=0),
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=201,
	 *         description="Success",
	 *          @OA\MediaType(
	 *              mediaType="application/json",
	 *          )
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function store(Request $request)
	{
		try {

			$validator = Validator::make($request->all(), [
				'product_id' => 'required|integer|exists:ec_products,id',
				'name' => 'required|string|max:255',
				'accessories' => 'required|array',
				'isapproved' => 'sometimes|boolean'

			]);

			if ($validator->fails()) {
				return response()->json([
					'success' => false,
					'message' => 'Validation failed',
					'errors' => $validator->errors()
				], 422);
			}
			// dd($request->all());
			$accessory = ProductAccessory::create([
				'product_id' => $request->product_id,
				'name' => $request->name,
				'isapproved' => $request->get('isapproved', 0),
				'isRequired' => $request->get('isRequired', 0),
				'created_by' => Auth::id() ?? 1
			]);

			$accessories = collect($request->accessories)->map(function ($item) {
				$item['name'] = trim($item['name']);
				return $item;
			})->toArray();

			// Save all accessories at once
			$accessory->items()->createMany($accessories);

			return response()->json([
				'success' => true,
				'message' => 'Product accessory created successfully',
				'data' => $accessory
			], 201);
		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Failed to create product accessory',
				'error' => $e->getMessage()
			], 500);
		}
	}

	/**
	 * @OA\Get(
	 *     path="/api/product-accessories/{id}",
	 *     summary="Show product accessory with item details",
	 *     tags={"Product Accessories"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         required=true,
	 *         description="Product accessory ID",
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Successful operation",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="message", type="string", example="Product accessory retrieved successfully"),
	 *             @OA\Property(property="data", type="object")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=404,
	 *         description="Product accessory not found"
	 *     ),
	 *      security={{"bearerAuth":{}}}
	 * )
	 */
	public function show($id)
	{
		try {
			$accessory = ProductAccessory::with(['items', 'product', 'approvedBy', 'createdBy', 'updatedBy'])->findOrFail($id);

			// Map items properly
			$accessoryItems = $accessory->items->map(function ($item) {
				return [
					'id' => $item->id,
					'name' => $item->name,
					'cost_price' => $item->cost_price,
					'price' => $item->price,
				];
			});

			// Format response
			$formattedProduct = [
				'product_id' => $accessory->product_id,
				'accessory_id' => $accessory->id,
				'name' => $accessory->name,
				'product_name' => $accessory->product->name,
				'sku' => $accessory->product->sku,
				'isapproved' => $accessory->isapproved,
				'isRequired' => $accessory->isRequired,
				'approved_by' => $accessory->approved_by,
				'created_by' => $accessory->created_by,
				'updated_by' => $accessory->updated_by,
				'accessory_item' => $accessoryItems,
			];
			return response()->json([
				'success' => true,
				'message' => 'Product accessory retrieved successfully',
				'data' => $formattedProduct
			]);
		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Product accessory not found',
				'error' => $e->getMessage()
			], 404);
		}

	}

	/**
	 * @OA\Get(
	 *     path="/api/product-accessories/{id}/edit",
	 *     summary="Fetch a product accessory for editing",
	 *     tags={"Product Accessories"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         required=true,
	 *         description="Product accessory ID",
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Product accessory data fetched successfully",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="id", type="integer", example=1),
	 *             @OA\Property(property="product_id", type="integer", example=1),
	 *             @OA\Property(property="name", type="string", example="Color"),
	 *             @OA\Property(
	 *                 property="accessories",
	 *                 type="array",
	 *                 @OA\Items(
	 *                     type="object",
	 *                     @OA\Property(property="name", type="string", example="left"),
	 *                     @OA\Property(property="price", type="integer", example=44),
	 *                     @OA\Property(property="cost_price", type="integer", example=40)
	 *                 )
	 *             ),
	 *             @OA\Property(property="isapproved", type="integer", example=1),
	 *             @OA\Property(property="approved_by", type="integer", example=2)
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=404,
	 *         description="Product accessory not found"
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */

	public function edit(Request $request, $id): JsonResponse
	{
		try {

			$accessory = ProductAccessory::with(['items', 'approvedBy', 'updatedBy'])->findOrFail($id);

			return response()->json([
				'success' => true,
				'message' => 'Product accessory updated successfully',
				'data' => $accessory
			]);
		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Failed to update product accessory',
				'error' => $e->getMessage()
			], 500);
		}
	}
	/**
	 * @OA\Put(
	 *     path="/api/product-accessories/{id}",
	 *     summary="Update a existing product accessory",
	 *     tags={"Product Accessories"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         required=true,
	 *         description="Product accessory ID",
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             @OA\Property(property="product_id", type="integer", example=1),
	 *             @OA\Property(property="name", type="string", example="Color"),
	 *             @OA\Property(
	 *                 property="accessories",
	 *                 type="array",
	 *                 @OA\Items(
	 *                     type="object",
	 *                     @OA\Property(property="name", type="string", example="left"),
	 *                     @OA\Property(property="price", type="integer", example=44),
	 *                     @OA\Property(property="cost_price", type="integer", example=40)
	 *                 ),
	 *                 example={
	 *                     {"name":"left","price":44,"cost_price":40},
	 *                     {"name":"right","price":45,"cost_price":41},
	 *                     {"name":"top","price":50,"cost_price":45},
	 *                     {"name":"button","price":52,"cost_price":48}
	 *                 }
	 *             ),
	 *             @OA\Property(property="isapproved", type="integer", example=1),
	 *             @OA\Property(property="isRequired", type="integer", example=0),
	 *             @OA\Property(property="approved_by", type="integer", example=2)
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Product accessory updated successfully",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="message", type="string", example="Product accessory updated successfully"),
	 *             @OA\Property(property="data", type="object")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=404,
	 *         description="Product accessory not found"
	 *     ),
	 *     @OA\Response(
	 *         response=422,
	 *         description="Validation error"
	 *     ),
	 *      security={{"bearerAuth":{}}}
	 * )
	 */
	public function update(Request $request, $id): JsonResponse
	{
		try {
			$validator = Validator::make($request->all(), [
				'product_id' => 'required|integer|exists:ec_products,id',
				'name' => 'required|string|max:255',
				'accessories' => 'required|array',
				'isapproved' => 'sometimes|boolean'
			]);

			if ($validator->fails()) {
				return response()->json([
					'success' => false,
					'message' => 'Validation failed',
					'errors' => $validator->errors()
				], 422);
			}

			$accessory = ProductAccessory::findOrFail($id);

			// Update main accessory
			$accessory->update([
				'product_id' => $request->product_id,
				'name' => $request->name,
				'isapproved' => $request->get('isapproved'),
				'isRequired' => $request->get('isRequired'),
				'updated_by' => Auth::id() ?? 1
			]);

			// Refresh accessory items (delete old and insert new)
			$accessory->items()->delete();
			$accessories = collect($request->accessories)->map(function ($item) {
				$item['name'] = trim($item['name']);
				return $item;
			})->toArray();
			$accessory->items()->createMany($accessories);

			$accessory->load(['product', 'createdBy', 'items']);

			return response()->json([
				'success' => true,
				'message' => 'Product accessory updated successfully',
				'data' => $accessory
			]);
		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Failed to update product accessory',
				'error' => $e->getMessage()
			], 500);
		}
	}

	/**
	 * @OA\Delete(
	 *     path="/api/product-accessories/{id}",
	 *     summary="Delete a product accessory",
	 *     tags={"Product Accessories"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         required=true,
	 *         description="Product accessory ID",
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Product accessory deleted successfully",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="message", type="string", example="Product accessory deleted successfully")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=404,
	 *         description="Product accessory not found"
	 *     ),
	 *      security={{"bearerAuth":{}}}
	 * )
	 */
	public function destroy($id): JsonResponse
	{
		try {
			$accessory = ProductAccessory::with('items')->findOrFail($id);

			// Delete related items first
			$accessory->items()->delete();

			// Delete the accessory itself
			$accessory->delete();

			return response()->json([
				'success' => true,
				'message' => 'Product accessory and related items deleted successfully'
			]);
		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Failed to delete product accessory',
				'error' => $e->getMessage()
			], 404);
		}
	}

	/**
	 * @OA\Delete(
	 *     path="/api/product-accessories/item/{item_id}",
	 *     summary="Delete a product accessory item",
	 *     tags={"Product Accessories"},
	 *     @OA\Parameter(
	 *         name="item_id",
	 *         in="path",
	 *         required=true,
	 *         description="Accessory Item ID",
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Product accessory item deleted successfully",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="message", type="string", example="Product accessory item deleted successfully")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=404,
	 *         description="Accessory item not found"
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */

	public function deleteItem($item_id): JsonResponse
	{
		$item = AccessoryItem::find($item_id);

		if (!$item) {
			return response()->json([
				'success' => false,
				'message' => 'Accessory item not found'
			], 404);
		}

		$item->delete();

		return response()->json([
			'success' => true,
			'message' => 'Accessory item deleted successfully'
		], 200);
	}


	/**
	 * @OA\Post(
	 *     path="/api/product-accessories/status/{id}",
	 *     summary="Approve/Disapprove a product accessory",
	 *     tags={"Product Accessories"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         required=true,
	 *         description="Product accessory ID",
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"isapproved"},
	 *             @OA\Property(property="isapproved", type="integer", example=1, description="1 for approve, 0 for disapprove")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Approval status updated successfully"
	 *     ),
	 *     @OA\Response(
	 *         response=404,
	 *         description="Product accessory not found"
	 *     ),
	 *      security={{"bearerAuth":{}}}
	 * )
	 */
	public function updateStatus(Request $request, $id): JsonResponse
	{
		try {

			$accessory = ProductAccessory::findOrFail($id);

			$validator = Validator::make($request->all(), [
				'isapproved' => 'sometimes|boolean'
			]);

			if ($validator->fails()) {
				return response()->json([
					'success' => false,
					'message' => 'Validation failed',
					'errors' => $validator->errors()
				], 422);
			}

			$accessory->update([
				'isapproved' => $request->isapproved,
				'approved_by' => $request->isapproved ? Auth::id() : null,
				'updated_by' => Auth::id()
			]);

			// $accessory->load(['product', 'approvedBy']);

			return response()->json([
				'success' => true,
				'message' => 'Approval status updated successfully',
			]);
		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Failed to update approval status',
				'error' => $e->getMessage()
			], 404);
		}
	}

	/**
	 * @OA\Post(
	 *     path="/api/product-accessories/isRequired/{id}",
	 *     summary="isRequired a product accessory",
	 *     tags={"Product Accessories"},
	 *     @OA\Parameter(
	 *         name="id",
	 *         in="path",
	 *         required=true,
	 *         description="Product accessory ID",
	 *         @OA\Schema(type="integer")
	 *     ),
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"isRequired"},
	 *             @OA\Property(property="isRequired", type="integer", example=1, description="1 for required, 0 for not required")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="isRequired updated successfully"
	 *     ),
	 *     @OA\Response(
	 *         response=404,
	 *         description="is Required not found"
	 *     ),
	 *      security={{"bearerAuth":{}}}
	 * )
	 */
	public function updateIsRequired(Request $request, $id): JsonResponse
	{
		try {

			$accessory = ProductAccessory::findOrFail($id);

			$validator = Validator::make($request->all(), [
				'isRequired' => 'sometimes|boolean'
			]);

			if ($validator->fails()) {
				return response()->json([
					'success' => false,
					'message' => 'Validation failed',
					'errors' => $validator->errors()
				], 422);
			}

			$accessory->update([
				'isRequired' => $request->isRequired

			]);



			return response()->json([
				'success' => true,
				'message' => 'Is Required updated successfully',
			]);
		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Failed to update is required',
				'error' => $e->getMessage()
			], 404);
		}
	}

	/**
	 * @OA\Get(
	 *     path="/api/get-product-list",
	 *     summary="Get list of product list",
	 *     tags={"Product List"},
	 *      @OA\Parameter(
	 *         name="search",
	 *         in="query",
	 *         description="Search term for filtering products by name",
	 *         required=false,
	 *         @OA\Schema(type="string", example="samsung")
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Successful operation",
	 *         @OA\JsonContent(
	 *             @OA\Property(property="success", type="boolean", example=true),
	 *             @OA\Property(property="message", type="string", example="Product accessories retrieved successfully"),
	 *             @OA\Property(property="data", type="object")
	 *         )
	 *     ),
	 *      security={{"bearerAuth":{}}}
	 * )
	 */
	public function getProductList(Request $request)
	{
		try {
			$product_list = [];

			// Only search if a search term is provided
			if ($request->search) {
				$search = trim($request->search);
				$products = Product::select('id', 'name', 'sku','images')
				->where(function ($q) use ($search) {
					$q->where('name', 'like', "%{$search}%")
					->orWhere('id', 'like', "%{$search}%")
					->orWhere('sku', 'like', "%{$search}%");
				})
				->orderBy('name', 'asc')
				->limit(25)
				->get();

				// Map the products
				$product_list = $products->map(function ($val) {

					$image  = is_array($val->images)
					? $val->images
					: (is_array($decoded = json_decode($val->images, true)) ? $decoded : null);

					return [
						'id' => $val->id,
						'name' => $val->name,
						'sku' => $val->sku,
						'img' => $image,
					];
				});
			}

			return response()->json([
				'success' => true,
				'message' => 'Product list',
				'data' => ['product_list' => $product_list],
			]);

		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Failed to retrieve product accessories',
				'error' => $e->getMessage()
			], 500);
		}
	}

	/**
	 * @OA\Post(
	 *     path="/api/product-accessories/import",
	 *     summary="Import product accessories details from an Excel file",
	 *     tags={"Product Accessories"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="multipart/form-data",
	 *             @OA\Schema(
	 *                 required={"upload_file"},
	 *                 @OA\Property(property="upload_file", type="string", format="binary", description="xlsx file (.xlsx) max 2MB"),
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function import(Request $request, ExcelImporterService $excelImporter)
	{
		/* Validate request data */
		$request->validate([
			'upload_file' => 'required|file|mimes:xlsx|max:2048',
		]);
		try {
			$accessoriesFileFormatArray = accessories_import_constants('ALL_FIELDS');

			$excelImporter->processExcelImport(
				$request->file('upload_file'),
				$accessoriesFileFormatArray,
				'Product Accessory', /* Module name */
				config('app.website') . '_ACCSRY', /* Job name */
				'Import Product Accessory', /* Batch name */
				ImportProductAccessoryJob::class
			);

			return response()->json([
				'success' => true,
				'message' => 'The import process has been scheduled successfully. Please track it under import log.'
			]);
		} catch (\Exception $exception) {
			$error[] = 'Error: ' . $exception->getMessage();
			$error[] = 'File: ' . $exception->getFile();
			$error[] = 'Line: ' . $exception->getLine();
			return response()->json([
				'success' => false,
				'message' => $error
			]);
		}
	}

	/**
	 * @OA\Post(
	 *     path="/api/product-accessories/export",
	 *     summary="Export product accessories data to Excel",
	 *     tags={"Product Accessories"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"range_from", "range_to"},
	 *             @OA\Property(property="range_from", type="integer", minimum=1, example=1, description="Starting range (must be >= 1)"),
	 *             @OA\Property(property="range_to", type="integer", example=50, description="Ending range (must be >= range_from and at most 500 more)")
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description="Success",
	 *         @OA\MediaType(mediaType="application/json")
	 *     ),
	 *     security={{"bearerAuth":{}}}
	 * )
	 */
	public function export(Request $request, ExcelRepository $excelRepo)
	{
		/* Validate request data */
		$request->validate([
			'range_from' => 'required|integer|min:1',
			'range_to' => 'required|integer|gte:range_from|max:' . ($request->range_from + 500),
		]);

		/* Fetch records with related secondary keywords */
		$records = Product::with([
			'productAccessories:id,product_id,name,isapproved,isRequired',
			'productAccessories.accessoryTypes:id,product_accessory_id,name,price,cost_price'
		])
		->whereHas('productAccessories')
		->offset($request->range_from - 1)
		->limit($request->range_to - $request->range_from + 1)
		->orderBy('id', 'asc')
		->get(['id', 'sku']);

		$spreadsheet = $excelRepo->newSpreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setTitle('Product Accessories Data');

		$accessoriesFileFormatArray = accessories_import_constants('ALL_FIELDS');

		/* Define headers */
		$headers = array_values($accessoriesFileFormatArray);
		$excelRepo->setHeader($sheet, $headers);

		$rowIndex = 2;
		foreach ($records as $record) {
			/* Fetch the relational name based on relational_type and relational_id */
			$productID = $record->id;
			$productSku = $record->sku;

			/* Process secondary keywords */
			if ($record->productAccessories->isNotEmpty()) {
				foreach ($record->productAccessories as $productAccessory) {
					$productAccessoryID = $productAccessory->id;
					$accessory_name = $productAccessory->name;
					$isApproved = $productAccessory->isapproved;
					$isRequired = $productAccessory->isRequired;

					if ($productAccessory->accessoryTypes->isNotEmpty()) {
						foreach ($productAccessory->accessoryTypes as $accessoryType) {
							$row = [
								$productID,
								$productSku,

								$productAccessoryID,
								$accessory_name,
								$isApproved,
								$isRequired,

								$accessoryType->id,
								$accessoryType->name,
								$accessoryType->price,
								$accessoryType->cost_price,
							];
							$excelRepo->writeRow($sheet, $row, $rowIndex++);
						}
					} else {
						/* If no secondary keywords, write a single line with primary data */
						$row = [
							$productID,
							$productSku,

							$productAccessoryID,
							$accessory_name,
							$isApproved,
							$isRequired,
							'',
							'',
							'',
							'',
						];
						$excelRepo->writeRow($sheet, $row, $rowIndex++);
					}
				}
			} else {
				/* If no secondary keywords, write a single line with primary data */
				$row = [
					$productID,
					$productSku,
					'',
					'',
					'',
					'',

					'',
					'',
					'',
					'',
				];
				$excelRepo->writeRow($sheet, $row, $rowIndex++);
			}
		}

		$fileName = 'product_accessories_' . $request->range_from . '-' . $request->range_to . '_' . now()->format('Y-m-d_H-i-s') . '.xlsx';

		return $excelRepo->downloadFile($fileName, $spreadsheet);
	}
}
