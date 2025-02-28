<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends BaseController
{
	/**
	 * Display a listing of the resource.
	 */
	public function index()
	{
		//
	}

	/**
	 * Show the form for creating a new resource.
	 */
	public function create()
	{
	}

	/**
	 * Store a newly created resource in storage.
	 */
	/**
	 * @OA\Post(
	 *     path="/api/products",
	 *     summary="Create a new product",
	 *     description="Creates a new product with the required details.",
	 *     tags={"Products"},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\JsonContent(
	 *             required={"name", "product_family", "sku", "website"},
	 *             @OA\Property(property="name", type="string", example="Sample Product", description="Name of the product"),
	 *             @OA\Property(property="product_family", type="integer", example=1, description="ID of the product family"),
	 *             @OA\Property(property="sku", type="string", example="PROD-123", description="Stock Keeping Unit (SKU) of the product"),
	 *             @OA\Property(
	 *                 property="websites",
	 *                 type="array",
	 *                 description="Array of website IDs where the product is available",
	 *                 @OA\Items(type="integer", example=1)
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
		/* Validate request data */
		$request->validate([
			'name' => "required|string",
			'product_family' => "required|integer",
			'sku' => "required|unique:ec_products,sku",
			'websites'=> "required|array",
		]);

		$product = new Product();
		$product->name = $request->name;
		$product->sku = $request->sku;
		$product->website_ids = implode(',', $request->websites);
		$product->status = 'draft';
		$product->created_at = now();
		$product->updated_at = now();
		$product->created_by_id = auth()->id();
		$product->created_by_type = User::class;
		$product->save();
		$this->saveProductCategory($product, $request->product_family);

		return response()->json([
			'message' => 'Product created successfully',
			'user' => $product
		]);
	}

	private function saveProductCategory($product, $categoryId)
	{
		/* Step 1: Fetch existing pivot data for the product */
		$existingCategories = $product->categories()->pluck('category_id')->toArray();

		if (!in_array($categoryId, $existingCategories)) {
			/* Clear existing specs if the category is different */
			$product->specifications()->delete();
		}

		/* Step 2: Prepare the category for syncing */
		$categoryWithTimestamp = in_array($categoryId, $existingCategories)
		? [$categoryId => []]
		: [$categoryId => ['created_at' => now()]];

		/* Step 3: Sync the single category */
		$product->categories()->sync($categoryWithTimestamp);
	}

	/**
	 * Display the specified resource.
	 */
	public function show(Product $product)
	{
		//
	}

	/**
	 * Show the form for editing the specified resource.
	 */
	public function edit(Product $product)
	{
		//
	}

	/**
	 * Update the specified resource in storage.
	 */
	public function update(Request $request, Product $product)
	{
		//
	}

	/**
	 * Remove the specified resource from storage.
	 */
	public function destroy(Product $product)
	{
		//
	}
}
