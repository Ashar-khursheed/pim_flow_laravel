<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\FrontEnd\HorecaPage;

class HorecaPageController extends BaseController
{
	/**
	 * @OA\Post(
	 *     path="/api/horeca-pages",
	 *     tags={"Horeca Pages"},
	 *     summary="Create a new horeca page",
	 *     description="Create a new horeca page with categories and products",
	 *     @OA\RequestBody(
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="multipart/form-data",
	 *             @OA\Schema(
	 *                 required={"name", "description", "link_name", "link_url", "banner", "categories", "products"},
	 *                 @OA\Property(property="name", type="string", example="Premium Coffee Solutions"),
	 *                 @OA\Property(property="description", type="string", example="Complete coffee solutions for hotels and restaurants"),
	 *                 @OA\Property(property="link_name", type="string", example="View Coffee Range"),
	 *                 @OA\Property(property="link_url", type="string", example="/products/coffee"),
	 *                 @OA\Property(property="banner", type="string", format="binary", description="Banner image (webp or png)"),
	 *                 @OA\Property(property="left_para_description", type="string", nullable=true, example="Left side description content"),
	 *                 @OA\Property(property="right_para_description", type="string", nullable=true, example="Right side description content"),
	 *                 @OA\Property(property="faqs", type="string", nullable=true, example="JSON or HTML formatted FAQs"),
	 *                 @OA\Property(property="is_active", type="boolean", example=true),
	 *
	 *                 @OA\Property(
	 *                     property="categories",
	 *                     type="array",
	 *                     description="Array of categories",
	 *                     @OA\Items(
	 *                         required={"category_id", "order"},
	 *                         @OA\Property(property="category_id", type="integer", example=101, description="Category ID"),
	 *                         @OA\Property(property="order", type="integer", example=5, description="Category order"),
	 *                     )
	 *                 ),
	 *
	 *                 @OA\Property(
	 *                     property="products",
	 *                     type="array",
	 *                     description="Array of products",
	 *                     @OA\Items(
	 *                         required={"product_id", "type"},
	 *                         @OA\Property(property="product_id", type="integer", example=101, description="Product ID"),
	 *                         @OA\Property(property="type", type="string", example="Featured", description="Product Type"),
	 *                         @OA\Property(property="description", type="string", example="Our best-selling product", description="Description"),
	 *                         @OA\Property(property="order", type="integer", example=5, description="Product order"),
	 *                     )
	 *                 ),
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(response=201, description="Horeca page created successfully", @OA\MediaType(mediaType="application/json")),
	 *     security={{"bearerAuth":{}}},
	 * )
	 */
	public function store(Request $request)
	{
		if ($request->has('categories') && is_string($request->categories)) {
			$categoryString = $request->categories;
			if (strpos(trim($categoryString), '{') === 0 && strpos(trim($categoryString), '[') !== 0) {
				$categoryString = '[' . $categoryString . ']';
			}
			$categories = json_decode($categoryString, true);
			$request->merge(['categories' => $categories]);
		}

		if ($request->has('products') && is_string($request->products)) {
			$productsString = $request->products;
			if (strpos(trim($productsString), '{') === 0 && strpos(trim($productsString), '[') !== 0) {
				$productsString = '[' . $productsString . ']';
			}
			$products = json_decode($productsString, true);
			$request->merge(['products' => $products]);
		}

		/* Validate the request */
		$data = $request->validate([
			'name' => 'required|string|max:255',
			'description' => 'required|string',
			'link_name' => 'required|string|max:255',
			'link_url' => 'required|string',
			'banner' => 'required|file|mimes:webp,png|max:5120', /* max 5MB */
			'left_para_description' => 'nullable|string',
			'right_para_description' => 'nullable|string',
			'faqs' => 'nullable|string',
			'is_active' => 'nullable|boolean',

			/* Categories validation */
			'categories' => 'required|array|min:1',
			'categories.*.category_id' => 'required|integer|exists:categories,id',
			'categories.*.order' => 'nullable|integer',

			/* Products validation */
			'products' => 'required|array|min:1',
			'products.*.product_id' => 'required|integer|exists:products,id',
			'products.*.type' => 'required|string|max:255',
			'products.*.description' => 'nullable|string',
			'products.*.order' => 'nullable|integer',
		]);

		try {
			DB::beginTransaction();

			/* Handle File Upload to S3 */
			$data['banner_url'] = uploadImageToWebpS3FromFile($request, 'banner', env('STORAGE_ENV') . 'horeca_pages/banners');
			unset($data['banner']);

			/* Add created_by and updated_by */
			$data['created_by'] = auth()->id();
			$data['updated_by'] = auth()->id();

			/* Remove categories and products from data array before creating */
			$categories = $data['categories'];
			$products = $data['products'];
			unset($data['categories'], $data['products']);

			/* Create the horeca page */
			$horecaPage = HorecaPage::create($data);

			/* Attach categories */
			if (!empty($categories)) {
				$categoriesData = [];
				foreach ($categories as $category) {
					$categoriesData[$category['category_id']] = [
						'order' => $category['order'] ?? 0,
					];
				}
				$horecaPage->categories()->attach($categoriesData);
			}

			/* Attach products */
			if (!empty($products)) {
				$productsData = [];
				foreach ($products as $product) {
					$productsData[$product['product_id']] = [
						'type' => $product['type'],
						'description' => $product['description'] ?? null,
						'order' => $product['order'] ?? 0,
					];
				}
				$horecaPage->products()->attach($productsData);
			}

			DB::commit();

			/* Load relationships */
			$horecaPage->load(['categories', 'products']);

			return response()->json([
				'success' => true,
				'message' => 'Horeca page created successfully',
				'data' => $horecaPage
			], 201);

		} catch (\Exception $e) {
			DB::rollBack();

			return response()->json([
				'success' => false,
				'message' => 'Failed to create horeca page',
				'error' => $e->getMessage()
			], 500);
		}
	}
}