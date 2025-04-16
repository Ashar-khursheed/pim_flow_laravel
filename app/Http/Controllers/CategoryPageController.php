<?php
namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\CategoryPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;


class CategoryPageController extends Controller
{

    /**
     * @OA\Get(
     *     path="/api/category-pages",
     *     tags={"Category Pages"},
     *     summary="Get all category pages",
     *     security={{"bearerAuth":{}}},
     *     description="Fetch a list of all category pages.",
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/CategoryPage")
     *         )
     *     )
     * )
     */
    public function index()
    {
        $pages = CategoryPage::all();
        return response()->json($pages);
    }

    /**
     * @OA\Get(
     *     path="/api/category-pages/{category_id}",
     *     tags={"Category Pages"},
     *     summary="Get category page data",
     *     security={{"bearerAuth":{}}},
     *     description="Fetch dynamic category page details by category ID.",
     *     @OA\Parameter(
     *         name="category_id",
     *         in="path",
     *         required=true,
     *         description="Category ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=404, description="Category page not found")
     * )
     */
    public function show(Category $category)
    {
        $page = CategoryPage::where('category_id', $category->id)->first();
        if (!$page) {
            return response()->json(['message' => 'Category page not found'], 404);
        }
        return response()->json($page);
    }

 /**
 * @OA\Post(
 *     path="/api/category-pages",
 *     tags={"Category Pages"},
 *     summary="Create or update a category page",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="multipart/form-data",
 *             @OA\Schema(
 *                 required={"category_id", "title"},
 *                 @OA\Property(property="category_id", type="integer", example=1),
 *                 @OA\Property(property="title", type="string", example="Electronics"),
 *                 @OA\Property(property="description", type="string", example="Best electronics category"),
 *                 @OA\Property(property="banner_image", type="string", format="binary"),
 *                 @OA\Property(property="banner_link", type="string", example="https://example.com"),
 *                 @OA\Property(property="inner_categories", type="array", @OA\Items(type="integer"), example={3,4,5}),
 *                 @OA\Property(property="six_images[]", type="array", @OA\Items(type="string", format="binary")),
 *                 @OA\Property(property="four_banners[]", type="array", @OA\Items(type="string", format="binary")),
 *                 @OA\Property(property="twelve_images[]", type="array", @OA\Items(type="string", format="binary")),
 *                 @OA\Property(property="related_products", type="array", @OA\Items(type="integer"), example={101, 102, 103}),
 *                 @OA\Property(property="section_title", type="string", example="Best electronics category"),
 *                 @OA\Property(property="section_description", type="string", example="Best electronics category"),
 *                 @OA\Property(property="extra_heading", type="string", example="Best electronics category"),
 *                 @OA\Property(property="extra_description", type="string", example="Best electronics category")
 *             )
 *         )
 *     ),
 *     security={{"bearerAuth":{}}}, 
 *     @OA\Response(response=201, description="Category page created"),
 *     @OA\Response(response=400, description="Validation error")
 * )
 */

 public function store(Request $request)
 {
     // Validate input
     $request->validate([
         'category_id' => 'required|exists:ec_product_categories,id',
         'title' => 'required|string|max:255',
         'banner_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
         'six_images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
         'four_banners.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
         'twelve_images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
     ]);
 
     // Handle banner image
     if ($request->hasFile('banner_image')) {
         $filePath = $request->file('banner_image')->store('uploads/category-banners', 'public');
     } else {
         $filePath = null;
     }
 
     // Handle multiple images uploads
     $sixImages = [];
     if ($request->hasFile('six_images')) {
         foreach ($request->file('six_images') as $file) {
             $sixImages[] = $file->store('uploads/category-pages', 'public');
         }
     }
 
     $fourBanners = [];
     if ($request->hasFile('four_banners')) {
         foreach ($request->file('four_banners') as $file) {
             $fourBanners[] = $file->store('uploads/category-pages', 'public');
         }
     }
 
     $twelveImages = [];
     if ($request->hasFile('twelve_images')) {
         foreach ($request->file('twelve_images') as $file) {
             $twelveImages[] = $file->store('uploads/category-pages', 'public');
         }
     }
 
     // Save category page
     $page = CategoryPage::updateOrCreate(
         ['category_id' => $request->category_id],
         array_merge(
             $request->except(['banner_image', 'six_images', 'four_banners', 'twelve_images']), 
             [
                 'banner_image' => $filePath,
                 'six_images' => json_encode($sixImages),
                 'four_banners' => json_encode($fourBanners),
                 'twelve_images' => json_encode($twelveImages),
             ]
         )
     );
 
     return response()->json($page, 201);
 }
 


    /**
     * @OA\Put(
     *     path="/api/category-pages/{category_id}",
     *     tags={"Category Pages"},
     *     summary="Update category page details",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="category_id",
     *         in="path",
     *         required=true,
     *         description="Category ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", example="Updated Category"),
     *             @OA\Property(property="description", type="string", example="Updated description")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Category page updated"),
     *     @OA\Response(response=404, description="Category page not found")
     * )
     */
    public function update(Request $request, Category $category)
    {
        $page = CategoryPage::where('category_id', $category->id)->first();
        if (!$page) {
            return response()->json(['message' => 'Category page not found'], 404);
        }

        $page->update($request->all());

        return response()->json($page);
    }

    /**
     * @OA\Delete(
     *     path="/api/category-pages/{category_id}",
     *     tags={"Category Pages"},
     *     summary="Delete a category page",
     *     security={{"bearerAuth":{}}},        
     *     @OA\Parameter(
     *         name="category_id",
     *         in="path",
     *         required=true,
     *         description="Category ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Category page deleted"),
     *     @OA\Response(response=404, description="Category page not found")
     * )
     */
    public function destroy(Category $category)
    {
        $page = CategoryPage::where('category_id', $category->id)->first();
        if (!$page) {
            return response()->json(['message' => 'Category page not found'], 404);
        }

        $page->delete();
        return response()->json(['message' => 'Category page deleted successfully']);
    }
}
