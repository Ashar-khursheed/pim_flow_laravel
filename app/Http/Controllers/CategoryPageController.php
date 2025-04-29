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
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="Unauthorized"
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="Forbidden"
 *     )
 * )
 */
public function index()
{
    // Check if user is authenticated
    if (!auth()->check()) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized access. Authentication required.',
        ], 401);
    }
    
    // Check permissions if authenticated
    if (!auth()->user()->can('list category page')) {
        return response()->json([
            'success' => false,
            'message' => "You don't have permission to access this module.",
        ], 403);
    }
    
    try {
        $pages = CategoryPage::all();
        
        return response()->json([
            'success' => true,
            'message' => 'Pages retrieved successfully.',
            'categories' => $pages
        ]);
    } catch (\Exception $e) {
        // Return detailed error in development
        if (env('APP_DEBUG', false)) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving category pages',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
        
        // Generic error in production
        return response()->json([
            'success' => false,
            'message' => 'An error occurred while retrieving category pages'
        ], 500);
    }
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
 *                 @OA\Property(property="banner_image_alt", type="string", example="Banner image alt text"),
 *                 @OA\Property(property="banner_link", type="string", example="https://example.com"),
 *                 @OA\Property(property="inner_categories", type="string", example="3,4,5", description="Comma-separated IDs"),
 *                 @OA\Property(property="six_images[]", type="array", @OA\Items(type="string", format="binary")),
 *                 @OA\Property(property="six_images_alt[]", type="array", @OA\Items(type="string", example="Alt text for six image")),
 *                 @OA\Property(property="four_banners[]", type="array", @OA\Items(type="string", format="binary")),
 *                 @OA\Property(property="four_banners_alt[]", type="array", @OA\Items(type="string", example="Alt text for four banner")),
 *                 @OA\Property(property="twelve_images[]", type="array", @OA\Items(type="string", format="binary")),
 *                 @OA\Property(property="twelve_images_alt[]", type="array", @OA\Items(type="string", example="Alt text for twelve image")),
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
    if (!auth()->user()->can('add category page')) {
        return response()->json([
            'success' => false,
            'message' => "You don't have permission to access this module.",
        ]);
    }
    try {
        // Validate input
        $request->validate([
            'category_id' => 'required|exists:ec_product_categories,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'banner_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp,,svg|max:2048',
            'banner_image_alt' => 'nullable|string|max:255',
            'banner_link' => 'nullable|string|url',
            'inner_categories' => 'nullable|string',
            'six_images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp,svg|max:2048',
            'six_images_alt.*' => 'nullable|string|max:255',
            'four_banners.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp,svg|max:2048',
            'four_banners_alt.*' => 'nullable|string|max:255',
            'twelve_images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp,svg|max:2048',
            'twelve_images_alt.*' => 'nullable|string|max:255',
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

        // Process six images with their corresponding alt texts
        $sixImages = [];
        $sixImagesAlt = [];
        if ($request->hasFile('six_images')) {
            foreach ($request->file('six_images') as $key => $file) {
                $imageUrl = Storage::disk($disk)->url($file->store("$folder/six", $disk));
                $sixImages[] = $imageUrl;
                // Store the corresponding alt text if available
                $sixImagesAlt[] = $request->has('six_images_alt') && isset($request->six_images_alt[$key])
                    ? $request->six_images_alt[$key]
                    : null;
            }
        }

        // Process four banners with their corresponding alt texts
        $fourBanners = [];
        $fourBannersAlt = [];
        if ($request->hasFile('four_banners')) {
            foreach ($request->file('four_banners') as $key => $file) {
                $imageUrl = Storage::disk($disk)->url($file->store("$folder/four", $disk));
                $fourBanners[] = $imageUrl;
                // Store the corresponding alt text if available
                $fourBannersAlt[] = $request->has('four_banners_alt') && isset($request->four_banners_alt[$key])
                    ? $request->four_banners_alt[$key]
                    : null;
            }
        }

        // Process twelve images with their corresponding alt texts
        $twelveImages = [];
        $twelveImagesAlt = [];
        if ($request->hasFile('twelve_images')) {
            foreach ($request->file('twelve_images') as $key => $file) {
                $imageUrl = Storage::disk($disk)->url($file->store("$folder/twelve", $disk));
                $twelveImages[] = $imageUrl;
                // Store the corresponding alt text if available
                $twelveImagesAlt[] = $request->has('twelve_images_alt') && isset($request->twelve_images_alt[$key])
                    ? $request->twelve_images_alt[$key]
                    : null;
            }
        }

        // JSON encode the arrays before storing in database
        $topPicksInSantos = !empty($request->top_picks_in_santos) ? explode(',', $request->top_picks_in_santos) : [];
        $topDealsFromOurSellers = !empty($request->top_deals_from_our_sellers) ? explode(',', $request->top_deals_from_our_sellers) : [];
        $exploreTopPicks = !empty($request->explore_top_picks) ? explode(',', $request->explore_top_picks) : [];
        $hotNewReleases = !empty($request->hot_new_releases) ? explode(',', $request->hot_new_releases) : [];
        $productsYouMayAlsoLike = !empty($request->products_you_may_also_like) ? explode(',', $request->products_you_may_also_like) : [];
        $inspiredByYourBrowsingHistory = !empty($request->inspired_by_your_browsing_history) ? explode(',', $request->inspired_by_your_browsing_history) : [];

        $page = CategoryPage::updateOrCreate(
            ['category_id' => $request->category_id],
            [
                'title' => $request->title,
                'description' => $request->description,
                'banner_image' => $filePath,
                'banner_image_alt' => $request->banner_image_alt,
                'banner_link' => $request->banner_link,
                'inner_categories' => json_encode($innerCategories),
                'six_images' => json_encode($sixImages),
                'six_images_alt' => json_encode($sixImagesAlt),
                'four_banners' => json_encode($fourBanners),
                'four_banners_alt' => json_encode($fourBannersAlt),
                'twelve_images' => json_encode($twelveImages),
                'twelve_images_alt' => json_encode($twelveImagesAlt),
                'related_products' => json_encode($relatedProducts),
                'section_title' => $request->section_title,
                'section_description' => $request->section_description,
                'extra_heading' => $request->extra_heading,
                'extra_description' => $request->extra_description,
                'top_picks_in_santos' => json_encode($topPicksInSantos),
                'top_deals_from_our_sellers' => json_encode($topDealsFromOurSellers),
                'explore_top_picks' => json_encode($exploreTopPicks),
                'hot_new_releases' => json_encode($hotNewReleases),
                'products_you_may_also_like' => json_encode($productsYouMayAlsoLike),
                'inspired_by_your_browsing_history' => json_encode($inspiredByYourBrowsingHistory),
            ]
        );

        // For response, decode the JSON-encoded arrays
        return response()->json([
            'success' => true,
            'message' => 'Category page updated successfully',
            'data' => [
                'id' => $page->id,
                'category_id' => $page->category_id,
                'title' => $page->title,
                'description' => $page->description,
                'banner_image' => $page->banner_image,
                'banner_image_alt' => $page->banner_image_alt,
                'banner_link' => $page->banner_link,
                'inner_categories' => json_decode($page->inner_categories),
                'six_images' => json_decode($page->six_images),
                'six_images_alt' => json_decode($page->six_images_alt),
                'four_banners' => json_decode($page->four_banners),
                'four_banners_alt' => json_decode($page->four_banners_alt),
                'twelve_images' => json_decode($page->twelve_images),
                'twelve_images_alt' => json_decode($page->twelve_images_alt),
                'related_products' => json_decode($page->related_products),
                'section_title' => $page->section_title,
                'section_description' => $page->section_description,

                // Rename here
                'brand_heading' => $page->extra_heading,
                'brand_description' => $page->extra_description,

                'top_picks_in_santos' => json_decode($page->top_picks_in_santos),
                'top_deals_from_our_sellers' => json_decode($page->top_deals_from_our_sellers),
                'explore_top_picks' => json_decode($page->explore_top_picks),
                'hot_new_releases' => json_decode($page->hot_new_releases),
                'products_you_may_also_like' => json_decode($page->products_you_may_also_like),
                'inspired_by_your_browsing_history' => json_decode($page->inspired_by_your_browsing_history),
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
 *                 @OA\Property(property="banner_image_alt", type="string", example="Banner image alt text"),
 *                 @OA\Property(property="banner_link", type="string", example="https://example.com"),
 *                 @OA\Property(property="inner_categories", type="string", example="3,4,5"),
 *                 @OA\Property(property="six_images[]", type="array", @OA\Items(type="string", format="binary")),
 *                 @OA\Property(property="six_images_alt[]", type="array", @OA\Items(type="string", example="Alt text for six image")),
 *                 @OA\Property(property="four_banners[]", type="array", @OA\Items(type="string", format="binary")),
 *                 @OA\Property(property="four_banners_alt[]", type="array", @OA\Items(type="string", example="Alt text for four banner")),
 *                 @OA\Property(property="twelve_images[]", type="array", @OA\Items(type="string", format="binary")),
 *                 @OA\Property(property="twelve_images_alt[]", type="array", @OA\Items(type="string", example="Alt text for twelve image")),
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
    if (!auth()->user()->can('update category page')) {
        return response()->json([
            'success' => false,
            'message' => "You don't have permission to access this module.",
        ]);
    }
    try {
        // Validate input
        $request->validate([
            'category_id' => 'required|exists:ec_product_categories,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'banner_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp,svg|max:2048',
            'banner_image_alt' => 'nullable|string|max:255',
            'banner_link' => 'nullable|string|url',
            'inner_categories' => 'nullable|string',
            'six_images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp,svg|max:2048',
            'six_images_alt.*' => 'nullable|string|max:255',
            'four_banners.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp,svg|max:2048',
            'four_banners_alt.*' => 'nullable|string|max:255',
            'twelve_images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp,svg|max:2048',
            'twelve_images_alt.*' => 'nullable|string|max:255',
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

        // Process six images with their corresponding alt texts
        $sixImages = json_decode($page->six_images ?? '[]');
        $sixImagesAlt = json_decode($page->six_images_alt ?? '[]');
        if ($request->hasFile('six_images')) {
            $sixImages = [];
            $sixImagesAlt = [];
            foreach ($request->file('six_images') as $key => $file) {
                $imageUrl = Storage::disk($disk)->url($file->store("$folder/six", $disk));
                $sixImages[] = $imageUrl;
                // Store the corresponding alt text if available
                $sixImagesAlt[] = $request->has('six_images_alt') && isset($request->six_images_alt[$key])
                    ? $request->six_images_alt[$key]
                    : null;
            }
        } else if ($request->has('six_images_alt')) {
            // Update alt texts without changing images
            foreach ($request->six_images_alt as $key => $alt) {
                if (isset($sixImagesAlt[$key])) {
                    $sixImagesAlt[$key] = $alt;
                }
            }
        }

        // Process four banners with their corresponding alt texts
        $fourBanners = json_decode($page->four_banners ?? '[]');
        $fourBannersAlt = json_decode($page->four_banners_alt ?? '[]');
        if ($request->hasFile('four_banners')) {
            $fourBanners = [];
            $fourBannersAlt = [];
            foreach ($request->file('four_banners') as $key => $file) {
                $imageUrl = Storage::disk($disk)->url($file->store("$folder/four", $disk));
                $fourBanners[] = $imageUrl;
                // Store the corresponding alt text if available
                $fourBannersAlt[] = $request->has('four_banners_alt') && isset($request->four_banners_alt[$key])
                    ? $request->four_banners_alt[$key]
                    : null;
            }
        } else if ($request->has('four_banners_alt')) {
            // Update alt texts without changing images
            foreach ($request->four_banners_alt as $key => $alt) {
                if (isset($fourBannersAlt[$key])) {
                    $fourBannersAlt[$key] = $alt;
                }
            }
        }

        // Process twelve images with their corresponding alt texts
        $twelveImages = json_decode($page->twelve_images ?? '[]');
        $twelveImagesAlt = json_decode($page->twelve_images_alt ?? '[]');
        if ($request->hasFile('twelve_images')) {
            $twelveImages = [];
            $twelveImagesAlt = [];
            foreach ($request->file('twelve_images') as $key => $file) {
                $imageUrl = Storage::disk($disk)->url($file->store("$folder/twelve", $disk));
                $twelveImages[] = $imageUrl;
                // Store the corresponding alt text if available
                $twelveImagesAlt[] = $request->has('twelve_images_alt') && isset($request->twelve_images_alt[$key])
                    ? $request->twelve_images_alt[$key]
                    : null;
            }
        } else if ($request->has('twelve_images_alt')) {
            // Update alt texts without changing images
            foreach ($request->twelve_images_alt as $key => $alt) {
                if (isset($twelveImagesAlt[$key])) {
                    $twelveImagesAlt[$key] = $alt;
                }
            }
        }

        $innerCategories = !empty($request->inner_categories) ? explode(',', $request->inner_categories) : [];
        $relatedProducts = !empty($request->related_products) ? explode(',', $request->related_products) : [];
        $topPicksInSantos = !empty($request->top_picks_in_santos) ? explode(',', $request->top_picks_in_santos) : [];
        $topDealsFromOurSellers = !empty($request->top_deals_from_our_sellers) ? explode(',', $request->top_deals_from_our_sellers) : [];
        $exploreTopPicks = !empty($request->explore_top_picks) ? explode(',', $request->explore_top_picks) : [];
        $hotNewReleases = !empty($request->hot_new_releases) ? explode(',', $request->hot_new_releases) : [];
        $productsYouMayAlsoLike = !empty($request->products_you_may_also_like) ? explode(',', $request->products_you_may_also_like) : [];
        $inspiredByYourBrowsingHistory = !empty($request->inspired_by_your_browsing_history) ? explode(',', $request->inspired_by_your_browsing_history) : [];

        $page->update([
            'category_id' => $request->category_id,
            'title' => $request->title,
            'description' => $request->description,
            'banner_image' => $filePath,
            'banner_image_alt' => $request->banner_image_alt ?? $page->banner_image_alt,
            'banner_link' => $request->banner_link,
            'inner_categories' => json_encode($innerCategories),
            'six_images' => json_encode($sixImages),
            'six_images_alt' => json_encode($sixImagesAlt),
            'four_banners' => json_encode($fourBanners),
            'four_banners_alt' => json_encode($fourBannersAlt),
            'twelve_images' => json_encode($twelveImages),
            'twelve_images_alt' => json_encode($twelveImagesAlt),
            'related_products' => json_encode($relatedProducts),
            'section_title' => $request->section_title,
            'section_description' => $request->section_description,
            'extra_heading' => $request->extra_heading,
            'extra_description' => $request->extra_description,
            'top_picks_in_santos' => json_encode($topPicksInSantos),
            'top_deals_from_our_sellers' => json_encode($topDealsFromOurSellers),
            'explore_top_picks' => json_encode($exploreTopPicks),
            'hot_new_releases' => json_encode($hotNewReleases),
            'products_you_may_also_like' => json_encode($productsYouMayAlsoLike),
            'inspired_by_your_browsing_history' => json_encode($inspiredByYourBrowsingHistory),
        ]);

        // For response, decode the JSON-encoded arrays for better readability
        return response()->json([
            'success' => true,
            'message' => 'Category page updated successfully',
            'data' => [
                'id' => $page->id,
                'category_id' => $page->category_id,
                'title' => $page->title,
                'description' => $page->description,
                'banner_image' => $page->banner_image,
                'banner_image_alt' => $page->banner_image_alt,
                'banner_link' => $page->banner_link,
                'inner_categories' => json_decode($page->inner_categories),
                'six_images' => json_decode($page->six_images),
                'six_images_alt' => json_decode($page->six_images_alt),
                'four_banners' => json_decode($page->four_banners),
                'four_banners_alt' => json_decode($page->four_banners_alt),
                'twelve_images' => json_decode($page->twelve_images),
                'twelve_images_alt' => json_decode($page->twelve_images_alt),
                'related_products' => json_decode($page->related_products),
                'section_title' => $page->section_title,
                'section_description' => $page->section_description,
                
                // Renamed fields for response consistency with store method
                'brand_heading' => $page->extra_heading,
                'brand_description' => $page->extra_description,
                
                'top_picks_in_santos' => json_decode($page->top_picks_in_santos),
                'top_deals_from_our_sellers' => json_decode($page->top_deals_from_our_sellers),
                'explore_top_picks' => json_decode($page->explore_top_picks),
                'hot_new_releases' => json_decode($page->hot_new_releases),
                'products_you_may_also_like' => json_decode($page->products_you_may_also_like),
                'inspired_by_your_browsing_history' => json_decode($page->inspired_by_your_browsing_history),
            ]
        ]);

    } catch (\Exception $e) {
        // Return detailed error in development
        if (env('APP_DEBUG', false)) {
            return response()->json([
                'message' => 'An error occurred while updating the category page',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }

        // Generic error in production
        return response()->json([
            'message' => 'An error occurred while updating the category page'
        ], 500);
    }
}

/**
 * @OA\Delete(
 *     path="/api/category-pages/{id}",
 *     tags={"Category Pages"},
 *     summary="Delete a category page",
 *     security={{"bearerAuth":{}}},
 *     description="Delete a specific category page by its ID.",
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="ID of the category page to delete",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(response=200, description="Category page deleted successfully"),
 *     @OA\Response(response=404, description="Category page not found"),
 *     @OA\Response(response=403, description="Unauthorized action")
 * )
 */
public function destroy($id)
{
    if (!auth()->user()->can('delete category page')) {
        return response()->json([
            'success' => false,
            'message' => "You don't have permission to access this module.",
        ]);
    }
    
    $page = CategoryPage::find($id);
    if (!$page) {
        return response()->json(['message' => 'Category page not found'], 404);
    }

    // Delete the page
    $page->delete();
    
    return response()->json([
        'success' => true,
        'message' => 'Category page deleted successfully'
    ]);
}
}