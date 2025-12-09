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
     *     @OA\Response(response=200, description="Successful response with category data", @OA\MediaType(mediaType="application/json")),
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

    /**
     * @OA\Get(
     *     path="/api/frontend/menu-categories",
     *     summary="Get menu categories with children",
     *     tags={"Frontend-Menu Categories"},
     *     @OA\Parameter(
     *         name="id",
     *         in="query",
     *         description="Filter categories by parent or category ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="List of categories in hierarchical structure", @OA\MediaType(mediaType="application/json")),
     * )
     */
    public function menuCategories(Request $request)
    {
        $filterId = $request->get('id');

        $records = Category::select([
            'id', 'name', 'slug', 'parent_id',
            'image', 'order', 'last_child'
        ])
        ->with([
        'translations',
        'seoUrl:id,relational_id,relational_type,url',
        'publishedChildren'
        ])
        ->withCount('products')
        ->where('status', 'published');


        if ($filterId) {
            $records->where(function ($q) use ($filterId) {
                $q->where('id', $filterId)->orWhere('parent_id', $filterId);
            });
        }

        $records = $records->orderBy('order');

        $cacheKey = $filterId ? "categories_menu_$filterId" : "categories_menu__all";

        $categoriesMenus = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($records) {
            return $records->get();
        });

        return response()->json($categoriesMenus)->header('Cache-Control', 'public, max-age=86400');
    }

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
     *     @OA\Response(response=200, description="List of categories in hierarchical structure", @OA\MediaType(mediaType="application/json")),
     * )
     */
    public function getCategoriesWithChildren(Request $request)
    {
        $filterId = $request->get('id');

        $query = Category::select(['id', 'name', 'parent_id', 'image', 'order', 'last_child'])
            ->withCount('products')
            ->with(['seoUrl'])
            ->where('status', 'published')
            ->orderByRaw('`order` ASC'); // 👈 escape reserved column

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

        return response()->json($categoriesTree)
            ->header('Cache-Control', 'public, max-age=86400');
    }

    private function buildCategoryTree($categories)
    {
        // Sort all categories by order first
        $categories = $categories->sortBy(function ($category) {
            return $category->order ?? 99999;
        })->values();

        $categoriesByParent = $categories->groupBy('parent_id');

        $buildTree = function ($parentId) use (&$buildTree, $categoriesByParent) {
            $tree = [];
            if (isset($categoriesByParent[$parentId])) {
                // Sort this group by order again to ensure proper ordering
                $sortedCategories = $categoriesByParent[$parentId]->sortBy(function ($cat) {
                    return $cat->order ?? 99999;
                });

                foreach ($sortedCategories as $category) {
                    $seoSlug = $category->seoUrl?->url ?? null;


                    $lastChildIds = !empty($category->last_child)
                        ? array_map('intval', explode(',', $category->last_child))
                        : [];

                    if (!empty($lastChildIds)) {
                        $last_children = Category::with('seoUrl')
                            ->whereIn('id', $lastChildIds)
                            ->get(['id', 'name', 'slug', 'parent_id', 'image', 'order'])
                            ->map(function ($child) {
                                return [
                                    'id' => $child->id,
                                    'name' => $child->name,
                                    'slug' => $child->seoUrl?->url ?? $child->slug ?? null,
                                    'parent_id' => $child->parent_id,
                                    'image' => $child->image,
                                    'order' => $child->order,
                                ];
                            });
                    } else {
                        $last_children = collect();
                    }

                    $tree[] = [
                        'id' => $category->id,
                        'name' => $category->name,
                        'slug' => $seoSlug,
                        'parent_id' => $category->parent_id,
                        'productCount' => $category->products_count,
                        'image' => $category->image,
                        'order' => $category->order,
                        'children' => $buildTree($category->id),
                        'last_children' => $last_children,
                    ];
                }
            }
            return $tree;
        };

        return $buildTree(0);
    }
}
