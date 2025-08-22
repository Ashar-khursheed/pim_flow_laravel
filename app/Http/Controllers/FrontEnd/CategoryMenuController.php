<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Category;
use OpenApi\Annotations as OA;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\SeoManagement;

class CategoryMenuController extends Controller
{


    /**
     * @OA\Get(
     *     path="/api/frontend/category-with-slug/{slug}",
     *     operationId="getCategoryBySlug",
     *     tags={"Frontend-Menu Categories"},
     *     summary="Get category and its children by slug",
     *     description="Fetch a category by its slug and return it with all its nested children recursively, including image URLs.",
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         description="Slug of the category",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response with category data",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Electronics"),
     *             @OA\Property(property="slug", type="string", example="electronics"),
     *             @OA\Property(property="parent_id", type="integer", nullable=true, example=null),
     *             @OA\Property(property="image", type="string", format="url", example="http://yourdomain.com/storage/categories/electronics.jpg"),
     *             @OA\Property(
     *                 property="children",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Category")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Category not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Category not found")
     *         )
     *     )
     * )
     */

    public function showCategoryBySlug($seoUrl)
{
    // Find the SEO record by URL
    $seoRecord = SeoManagement::where('url', $seoUrl)
        ->where('relational_type', 'category') // if you use a type column
        ->first();

    if (!$seoRecord) {
        return response()->json(['message' => 'SEO URL not found'], 404);
    }

    // Find the category using the relational_id
    $category = Category::where('id', $seoRecord->relational_id)
        ->where('status', 'published')
        ->first();

    if (!$category) {
        return response()->json(['message' => 'Category not found'], 404);
    }

    // Fetch children of this category recursively
    $categoryWithChildren = $this->getCategoryWithChildren($category);

    return response()->json($categoryWithChildren);
}
   
    /**
     * Recursive function to fetch category and its children recursively.
     *
     * @param  \Botble\Ecommerce\Models\ProductCategory  $category
     * @return array
     */
   
//     private function getCategoryWithChildren($category)
// {
//     // Get the children of the category
//     $children = Category::where('parent_id', $category->id)
//         ->where('status', 'published')
//           ->with('seoUrl')
//         ->get();

//     // Iterate through each child and fetch its children recursively
//     foreach ($children as $child) {
//         // Recursively get children for the child category
//         $child->setRelation('children', $this->getCategoryWithChildren($child));

//         // Attach SEO URL instead of slug
//         $seo = $child->seoUrl; // Assumes you have the seoUrl() relationship
//         $child->seo_slug = $seo?->url ?? null;
//     }

//     // Add image URL for the current category
//     $category->image = $category->image;

//     // Add the children to the current category
//     $category->children = $children;

//     // Attach SEO URL instead of slug for the current category
//     $seo = $category->seoUrl; // Assumes you have the seoUrl() relationship
//     $category->seo_slug = $seo?->url ?? null;

//     // Return the modified structure
//     return [
//         'id' => $category->id,
//         'name' => $category->name,
//         'slug' => $category->seo_slug, // Replace original slug with SEO URL
//         'parent_id' => $category->parent_id,
//         'image' => $category->image,
//         'children' => $category->children,
//     ];
// }
private function getCategoryWithChildren($category)
{
    // Ensure SEO is loaded for the current category
    $category->loadMissing('seoUrl');

    // Get children with SEO eager loaded
    $children = Category::where('parent_id', $category->id)
        ->where('status', 'published')
        ->with('seoUrl')
        ->get();

    // Iterate through each child and fetch its children recursively
    foreach ($children as $child) {
        // Recursively get children for the child category
        $child->setRelation('children', $this->getCategoryWithChildren($child));

        // Replace slug with SEO URL if available
        $seo = $child->seoUrl;
        $child->slug = $seo?->url ?? $child->slug;
    }

    // Replace slug for the current category with SEO URL if available
    $seo = $category->seoUrl;
    $category->slug = $seo?->url ?? $category->slug;

    // Set image and children
    $category->image = $category->image;
    $category->children = $children;

    // Return the model structure with transformed slug
    return [
        'id' => $category->id,
        'name' => $category->name,
        'slug' => $category->slug,
        'parent_id' => $category->parent_id,
        'image' => $category->image,
        'children' => $category->children,
    ];
}

//    private function getCategoryWithChildren1($category)
// {
//     // Eager load SEO
//     $category->loadMissing('seoUrl');

//     // Get SEO slug for current category
//     $seo = $category->seoUrl;
//     $seoSlug = $seo?->url ?? $category->slug;

//     // Get children with SEO eager loaded
//     $children = Category::where('parent_id', $category->id)
//         ->where('status', 'published')
//         ->with('seoUrl')
//         ->get();

//     // Transform children recursively
//     $childrenArray = [];
//     foreach ($children as $child) {
//         $childSeo = $child->seoUrl;
//         $childSeoSlug = $childSeo?->url ?? $child->slug;

//         $childrenArray[] = [
//             'id' => $child->id,
//             'name' => $child->name,
//             'slug' => $childSeoSlug,
//             'parent_id' => $child->parent_id,
//             'image' => $child->image,
//             'children' => $this->getCategoryWithChildren($child), // recursive call
//         ];
//     }

//     return [
//         'id' => $category->id,
//         'name' => $category->name,
//         'slug' => $seoSlug,
//         'parent_id' => $category->parent_id,
//         'image' => $category->image,
//         'children' => $childrenArray,
//     ];
// }




    /**
     * @OA\Get(
     *     path="/api/frontend/categories-with-children",
     *     summary="Get categories with children",
     *     tags={"Frontend-Menu Categories"},
     *     @OA\Parameter(
     *         name="id",
     *         in="query",
     *         description="Filter categories by parent or category ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of categories in hierarchical structure",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="slug", type="string"),
     *                 @OA\Property(property="parent_id", type="integer", nullable=true),
     *                 @OA\Property(property="productCount", type="integer"),
     *                 @OA\Property(property="image", type="string", format="url"),
     *                 @OA\Property(
     *                     property="children",
     *                     type="array",
     *                     @OA\Items(ref="#/components/schemas/Category")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
   
 public function getCategoriesWithChildren(Request $request)
{
    $filterId = $request->get('id');

    $query = Category::select(['id', 'name', 'parent_id', 'image'])
        ->withCount('products')
        ->with(['seoUrl']) // Eager load seoUrl relationship
        ->where('status', 'published');

    if ($filterId) {
        $query->where(function ($q) use ($filterId) {
            $q->where('id', $filterId)->orWhere('parent_id', $filterId);
        });
    }

    $cacheKey = $filterId ? "categories_tree_$filterId" : "categories_tree_all";

    $categoriesTree = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($query) {
        $categories = $query->get();
        return $this->buildCategoryTree($categories);
    });

    return response()->json($categoriesTree);
}

private function buildCategoryTree($categories)
{
    $tree = [];
    $categoryMap = [];

    // Create a lookup table for fast access
    foreach ($categories as $category) {
        // Get the slug from seoUrl relationship
        $seoSlug = $category->seoUrl?->url ?? null;
        
        $categoryMap[$category->id] = [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $seoSlug, // Use the slug from seoUrl relationship
            'parent_id' => $category->parent_id,
            'productCount' => $category->products_count,
            'image' => $category->image,
            'children' => [],
        ];
    }

    // Build the tree using the lookup table
    foreach ($categoryMap as &$category) {
        if ($category['parent_id'] && isset($categoryMap[$category['parent_id']])) {
            $categoryMap[$category['parent_id']]['children'][] = &$category;
        } else {
            $tree[] = &$category;
        }
    }

    return $tree;
}

    /**
 * @OA\Schema(
 *     schema="Category",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="slug", type="string"),
 *     @OA\Property(property="parent_id", type="integer", nullable=true),
 *     @OA\Property(property="productCount", type="integer"),
 *     @OA\Property(property="image", type="string", format="url"),
 *     @OA\Property(
 *         property="children",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/Category")
 *     )
 * )
 */

}
