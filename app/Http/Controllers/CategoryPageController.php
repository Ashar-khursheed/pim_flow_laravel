<?php
namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\CategoryPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
/**
 * @OA\Schema(
 *     schema="CategoryPage",
 *     type="object",
 *     title="Category Page",
 *     required={"id", "category_id", "title"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="category_id", type="integer", example=12),
 *     @OA\Property(property="title", type="string", example="Electronics"),
 *     @OA\Property(property="description", type="string", example="All about electronics..."),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */


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
    
        return response()->json([
            'success' => true,
            'message' => 'Pages retrieved successfully.',
            'categories' => $pages
        ]);
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
 *                 @OA\Property(property="inner_categories", type="string", example="3,4,5", description="Comma-separated IDs"),
 *                 @OA\Property(property="six_images[]", type="array", @OA\Items(type="string", format="binary")),
 *                 @OA\Property(property="four_banners[]", type="array", @OA\Items(type="string", format="binary")),
 *                 @OA\Property(property="twelve_images[]", type="array", @OA\Items(type="string", format="binary")),
 *                 @OA\Property(property="related_products", type="string", example="101,102,103", description="Comma-separated IDs"),
 *                 @OA\Property(property="top_picks_in_santos", type="string", example="101,102,103", description="Comma-separated IDs"),
 *                 @OA\Property(property="top_deals_from_our_sellers", type="string", example="101,102,103", description="Comma-separated IDs"),
 *                 @OA\Property(property="explore_top_picks", type="string", example="101,102,103", description="Comma-separated IDs"),
 *                 @OA\Property(property="hot_new_releases", type="string", example="101,102,103", description="Comma-separated IDs"),
 *                 @OA\Property(property="products_you_may_also_like", type="string", example="101,102,103", description="Comma-separated IDs"),
 *                 @OA\Property(property="inspired_by_your_browsing_history", type="string", example="101,102,103", description="Comma-separated IDs"),
 *                 @OA\Property(property="section_title", type="string", example="Best electronics category"),
 *                 @OA\Property(property="section_description", type="string", example="Best electronics category"),
 *                 @OA\Property(property="brand_heading", type="string", example="Best electronics category"),
 *                 @OA\Property(property="brand_description", type="string", example="Best electronics category")
 *             )
 *         )
 *     ),
 *     security={{"bearerAuth":{}}}, 
 *     @OA\Response(response=201, description="Category page created/updated successfully"),
 *     @OA\Response(response=404, description="Category not found"),
 *     @OA\Response(response=400, description="Validation error"),
 *     @OA\Response(response=500, description="Server error")
 * )
 */
public function store(Request $request)
{
    try {
        // Validate input
        $request->validate([
            'category_id' => 'required|exists:ec_product_categories,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'banner_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'banner_link' => 'nullable|string|url',
            'inner_categories' => 'nullable|string',
            'six_images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'four_banners.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'twelve_images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'related_products' => 'nullable|string',
            'section_title' => 'nullable|string|max:255',
            'section_description' => 'nullable|string',
            'extra_heading' => 'nullable|string|max:255',
            'extra_description' => 'nullable|string',
            'top_picks_in_santos' => 'nullable|string',
            'top_deals_from_our_sellers' => 'nullable|string',
            'explore_top_picks' => 'nullable|string',
            'hot_new_releases' => 'nullable|string',
            'products_you_may_also_like' => 'nullable|string',
            'inspired_by_your_browsing_history' => 'nullable|string',

        ]);
        
        $disk = 's3'; // Use S3 disk for storage
        $category = \DB::table('ec_product_categories')->where('id', $request->category_id)->first();
        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        // Process arrays
        $innerCategories = !empty($request->inner_categories) ? explode(',', $request->inner_categories) : [];
        $relatedProducts = !empty($request->related_products) ? explode(',', $request->related_products) : [];

        // Use tanuj_local prefix for S3 storage
        $folder = 'tanuj_local/category-pages/' . Str::slug($category->name);
        $filePath = null;

        if ($request->hasFile('banner_image')) {
            $filePath = Storage::disk($disk)->url(
                $request->file('banner_image')->store("$folder/banner", $disk)
            );
            
        }

        $sixImages = [];
        if ($request->hasFile('six_images')) {
            foreach ($request->file('six_images') as $file) {
                $sixImages[] = Storage::disk($disk)->url($file->store("$folder/six", $disk));
            }
        }
        
        $fourBanners = [];
        if ($request->hasFile('four_banners')) {
            foreach ($request->file('four_banners') as $file) {
                $fourBanners[] = Storage::disk($disk)->url($file->store("$folder/four", $disk));
            }
        }
        
        $twelveImages = [];
        if ($request->hasFile('twelve_images')) {
            foreach ($request->file('twelve_images') as $file) {
                $twelveImages[] = Storage::disk($disk)->url($file->store("$folder/twelve", $disk));
            }
        }
        
     
        $page = CategoryPage::updateOrCreate(
            ['category_id' => $request->category_id],
            [
                'title' => $request->title,
                'description' => $request->description,
                'banner_image' => $filePath,
                'banner_link' => $request->banner_link,
                'inner_categories' => ($innerCategories),
                'six_images' => ($sixImages),
                'four_banners' => ($fourBanners),
                'twelve_images' => ($twelveImages),
                'related_products' => ($relatedProducts),
                'section_title' => $request->section_title,
                'section_description' => $request->section_description,
                'extra_heading' => $request->extra_heading,
                'extra_description' => $request->extra_description,
               'top_picks_in_santos' => !empty($request->top_picks_in_santos) ? explode(',', $request->top_picks_in_santos) : [],
                'top_deals_from_our_sellers' => !empty($request->top_deals_from_our_sellers) ? explode(',', $request->top_deals_from_our_sellers) : [],
                'explore_top_picks' => !empty($request->explore_top_picks) ? explode(',', $request->explore_top_picks) : [],
                'hot_new_releases' => !empty($request->hot_new_releases) ? explode(',', $request->hot_new_releases) : [],
                'products_you_may_also_like' => !empty($request->products_you_may_also_like) ? explode(',', $request->products_you_may_also_like) : [],
                'inspired_by_your_browsing_history' => !empty($request->inspired_by_your_browsing_history) ? explode(',', $request->inspired_by_your_browsing_history) : [],
            ]
        );

        return response()->json([
                'success' => true,
                'message' => 'Category page updated successfully',
                'data' => [
                    'id' => $page->id,
                    'category_id' => $page->category_id,
                    'title' => $page->title,
                    'description' => $page->description,
                    'banner_image' => $page->banner_image,
                    'banner_link' => $page->banner_link,
                    'inner_categories' => $page->inner_categories,
                    'six_images' => $page->six_images,
                    'four_banners' => $page->four_banners,
                    'twelve_images' => $page->twelve_images,
                    'related_products' => $page->related_products,
                    'section_title' => $page->section_title,
                    'section_description' => $page->section_description,
                
                    // Rename here
                    'brand_heading' => $page->extra_heading,
                    'brand_description' => $page->extra_description,
                
                    'top_picks_in_santos' => $page->top_picks_in_santos,
                    'top_deals_from_our_sellers' => $page->top_deals_from_our_sellers,
                    'explore_top_picks' => $page->explore_top_picks,
                    'hot_new_releases' => $page->hot_new_releases,
                    'products_you_may_also_like' => $page->products_you_may_also_like,
                    'inspired_by_your_browsing_history' => $page->inspired_by_your_browsing_history,
                ]
            ], 201);
           
            } catch (\Exception $e) {
        // Return detailed error in development
        if (env('APP_DEBUG', false)) {
            return response()->json([
                'message' => 'An error occurred while creating the category page',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
        
        // Generic error in production
        return response()->json([
            'message' => 'An error occurred while creating the category page'
        ], 500);
    }
}
  /**
 * @OA\Post(
 *     path="/api/category-pages/{id}",
 *     tags={"Category Pages"},
 *     summary="Update an existing category page",
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="ID of the category page",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="multipart/form-data",
 *             @OA\Schema(
 *                 required={"category_id", "title"},
 *                 @OA\Property(property="_method", type="string", example="PUT"),
 *                 @OA\Property(property="category_id", type="integer", example=1),
 *                 @OA\Property(property="title", type="string", example="Updated Electronics"),
 *                 @OA\Property(property="description", type="string"),
 *                 @OA\Property(property="banner_image", type="string", format="binary"),
 *                 @OA\Property(property="banner_link", type="string", example="https://example.com"),
 *                 @OA\Property(property="inner_categories", type="string", example="3,4,5"),
 *                 @OA\Property(property="six_images[]", type="array", @OA\Items(type="string", format="binary")),
 *                 @OA\Property(property="four_banners[]", type="array", @OA\Items(type="string", format="binary")),
 *                 @OA\Property(property="twelve_images[]", type="array", @OA\Items(type="string", format="binary")),
 *                 @OA\Property(property="related_products", type="string", example="101,102"),
 *                 @OA\Property(property="top_picks_in_santos", type="string", example="101,102,103", description="Comma-separated IDs"),
 *                 @OA\Property(property="top_deals_from_our_sellers", type="string", example="101,102,103", description="Comma-separated IDs"),
 *                 @OA\Property(property="explore_top_picks", type="string", example="101,102,103", description="Comma-separated IDs"),
 *                 @OA\Property(property="hot_new_releases", type="string", example="101,102,103", description="Comma-separated IDs"),
 *                 @OA\Property(property="products_you_may_also_like", type="string", example="101,102,103", description="Comma-separated IDs"),
 *                 @OA\Property(property="inspired_by_your_browsing_history", type="string", example="101,102,103", description="Comma-separated IDs"),
 *                 @OA\Property(property="section_title", type="string"),
 *                 @OA\Property(property="section_description", type="string"),
 *                 @OA\Property(property="extra_heading", type="string"),
 *                 @OA\Property(property="extra_description", type="string")
 *             )
 *         )
 *     ),
 *     security={{"bearerAuth":{}}}, 
 *     @OA\Response(response=200, description="Category page updated successfully"),
 *     @OA\Response(response=404, description="Category page not found"),
 *     @OA\Response(response=400, description="Validation error"),
 *     @OA\Response(response=500, description="Server error")
 * )
 */
public function update(Request $request, $id)
{
    try {
        // Validate input
        $request->validate([
            'category_id' => 'required|exists:ec_product_categories,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'banner_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'banner_link' => 'nullable|string|url',
            'inner_categories' => 'nullable|string',
            'six_images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'four_banners.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'twelve_images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'related_products' => 'nullable|string',
            'section_title' => 'nullable|string|max:255',
            'section_description' => 'nullable|string',
            'extra_heading' => 'nullable|string|max:255',
            'extra_description' => 'nullable|string',
            'top_picks_in_santos' => 'nullable|string',
            'top_deals_from_our_sellers' => 'nullable|string',
            'explore_top_picks' => 'nullable|string',
            'hot_new_releases' => 'nullable|string',
            'products_you_may_also_like' => 'nullable|string',
            'inspired_by_your_browsing_history' => 'nullable|string',
        ]);

        $disk = 's3';
        $category = \DB::table('ec_product_categories')->where('id', $request->category_id)->first();
        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        $page = CategoryPage::find($id);
        if (!$page) {
            return response()->json(['message' => 'Category page not found'], 404);
        }

        $folder = 'tanuj_local/category-pages/' . Str::slug($category->name);

        // Handle uploads
        $filePath = $page->banner_image;
        if ($request->hasFile('banner_image')) {
            $filePath = Storage::disk($disk)->url(
                $request->file('banner_image')->store("$folder/banner", $disk)
            );
        }

        $sixImages = $page->six_images ?? [];
        if ($request->hasFile('six_images')) {
            $sixImages = [];
            foreach ($request->file('six_images') as $file) {
                $sixImages[] = Storage::disk($disk)->url($file->store("$folder/six", $disk));
            }
        }

        $fourBanners = $page->four_banners ?? [];
        if ($request->hasFile('four_banners')) {
            $fourBanners = [];
            foreach ($request->file('four_banners') as $file) {
                $fourBanners[] = Storage::disk($disk)->url($file->store("$folder/four", $disk));
            }
        }

        $twelveImages = $page->twelve_images ?? [];
        if ($request->hasFile('twelve_images')) {
            $twelveImages = [];
            foreach ($request->file('twelve_images') as $file) {
                $twelveImages[] = Storage::disk($disk)->url($file->store("$folder/twelve", $disk));
            }
        }

        $innerCategories = !empty($request->inner_categories) ? explode(',', $request->inner_categories) : [];
        $relatedProducts = !empty($request->related_products) ? explode(',', $request->related_products) : [];

        $page->update([
            'category_id' => $request->category_id,
            'title' => $request->title,
            'description' => $request->description,
            'banner_image' => $filePath,
            'banner_link' => $request->banner_link,
            'inner_categories' => $innerCategories,
            'six_images' => $sixImages,
            'four_banners' => $fourBanners,
            'twelve_images' => $twelveImages,
            'related_products' => $relatedProducts,
            'section_title' => $request->section_title,
            'section_description' => $request->section_description,
            'extra_heading' => $request->extra_heading,
            'extra_description' => $request->extra_description,
            'top_picks_in_santos' => !empty($request->top_picks_in_santos) ? explode(',', $request->top_picks_in_santos) : [],
            'top_deals_from_our_sellers' => !empty($request->top_deals_from_our_sellers) ? explode(',', $request->top_deals_from_our_sellers) : [],
            'explore_top_picks' => !empty($request->explore_top_picks) ? explode(',', $request->explore_top_picks) : [],
            'hot_new_releases' => !empty($request->hot_new_releases) ? explode(',', $request->hot_new_releases) : [],
            'products_you_may_also_like' => !empty($request->products_you_may_also_like) ? explode(',', $request->products_you_may_also_like) : [],
            'inspired_by_your_browsing_history' => !empty($request->inspired_by_your_browsing_history) ? explode(',', $request->inspired_by_your_browsing_history) : [],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Category page updated successfully',
            'data' => [
                'id' => $page->id,
                'category_id' => $page->category_id,
                'title' => $page->title,
                'description' => $page->description,
                'banner_image' => $page->banner_image,
                'banner_link' => $page->banner_link,
                'inner_categories' => $page->inner_categories,
                'six_images' => $page->six_images,
                'four_banners' => $page->four_banners,
                'twelve_images' => $page->twelve_images,
                'related_products' => $page->related_products,
                'section_title' => $page->section_title,
                'section_description' => $page->section_description,
                'brand_heading' => $page->extra_heading,
                'brand_description' => $page->extra_description,
                'top_picks_in_santos' => $page->top_picks_in_santos,
                'top_deals_from_our_sellers' => $page->top_deals_from_our_sellers,
                'explore_top_picks' => $page->explore_top_picks,
                'hot_new_releases' => $page->hot_new_releases,
                'products_you_may_also_like' => $page->products_you_may_also_like,
                'inspired_by_your_browsing_history' => $page->inspired_by_your_browsing_history,
            ]
        ], 200);
            } catch (\Exception $e) {
        if (env('APP_DEBUG')) {
            return response()->json([
                'message' => 'Update error',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }

        return response()->json(['message' => 'An error occurred while updating the category page'], 500);
    }
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
