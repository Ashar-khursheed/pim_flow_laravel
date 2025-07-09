<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Category;
use OpenApi\Annotations as OA;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

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

    public function showCategoryBySlug($slug)
    {
        // Fetch the category by slug
        $category = Category::where('slug', $slug)
                        ->where('status', 'published')
                        ->first();

        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        // Fetch children of this category recursively
        $categoryWithChildren = $this->getCategoryWithChildren($category);

        // Return the category with children and their respective children
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
        // Get the children of the category
        $children = Category::where('parent_id', $category->id)
        ->where('status', 'published')
        ->get();

        // Iterate through each child and fetch its children recursively
        foreach ($children as $child) {
            // Add image URL
            // $child->image = $child->image;

            // Prevent the 'children' attribute from causing recursion in JSON
            $child->setRelation('children', $this->getCategoryWithChildren($child));
        }

        // Add image URL for the current category
        $category->image = $category->image;

        // Add the children to the current category
        $category->children = $children;

        // Return the category with its children
        return $category->only(['id', 'name', 'slug', 'parent_id', 'image', 'children']);
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

        $query = Category::select(['id', 'name', 'slug', 'parent_id', 'image'])
            ->withCount('products')
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


    /**
     * Build a hierarchical category tree efficiently.
     */
    private function buildCategoryTree($categories)
    {
        $tree = [];
        $categoryMap = [];

        // Create a lookup table for fast access
        foreach ($categories as $category) {
            $categoryMap[$category->id] = [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'parent_id' => $category->parent_id,
                'productCount' => $category->products_count, // Eager-loaded product count
                'image' =>  $category->image,
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
