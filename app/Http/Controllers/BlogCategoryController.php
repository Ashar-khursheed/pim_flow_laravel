<?php

namespace App\Http\Controllers;

use App\Models\BlogCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="Blog Categories",
 *     description="API endpoints for managing blog categories"
 * )
 */
class BlogCategoryController extends Controller
{
   /**
 * @OA\Get(
 *     path="/api/blog-categories",
 *     tags={"Blog Categories"},
 *     summary="Get list of blog categories with pagination and sorting",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="page",
 *         in="query",
 *         description="Page number",
 *         required=false,
 *         @OA\Schema(type="integer", default=1)
 *     ),
 *     @OA\Parameter(
 *         name="per_page",
 *         in="query",
 *         description="Number of items per page",
 *         required=false,
 *         @OA\Schema(type="integer", default=10)
 *     ),
 *     @OA\Parameter(
 *         name="sort_by",
 *         in="query",
 *         description="Field to sort by",
 *         required=false,
 *         @OA\Schema(type="string", default="id")
 *     ),
 *     @OA\Parameter(
 *         name="sort_order",
 *         in="query",
 *         description="Sort order: asc or desc",
 *         required=false,
 *         @OA\Schema(type="string", enum={"asc","desc"}, default="asc")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Success",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Blog categories retrieved successfully"),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(property="data", type="array",
 *                     @OA\Items(ref="#/components/schemas/BlogCategory")
 *                 ),
 *                 @OA\Property(property="current_page", type="integer"),
 *                 @OA\Property(property="last_page", type="integer"),
 *                 @OA\Property(property="per_page", type="integer"),
 *                 @OA\Property(property="total", type="integer")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=500, description="Server error")
 * )
 */
public function index(Request $request)
{
    try {
        $perPage = (int) $request->query('per_page', 10);
        $page = (int) $request->query('page', 1);
        $sortBy = $request->query('sort_by', 'id');
        $sortOrder = strtolower($request->query('sort_order', 'asc')) === 'desc' ? 'desc' : 'asc';

        // Optional: validate sortBy against allowed fields to prevent SQL injection
        $allowedSortFields = ['id', 'name', 'created_at', 'updated_at', 'order'];
        if (!in_array($sortBy, $allowedSortFields)) {
            $sortBy = 'id';
        }

        $categories = BlogCategory::orderBy($sortBy, $sortOrder)->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'message' => 'Blog categories retrieved successfully',
            'data' => $categories
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch categories',
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
     * @OA\Post(
     *     path="/api/blog-categories",
     *     tags={"Blog Categories"},
     *     summary="Create a new blog category",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="slug", type="string"),
     *             @OA\Property(property="parent_id", type="integer"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="status", type="string", enum={"draft", "published"}),
     *             @OA\Property(property="created_by", type="integer"),
     *             @OA\Property(property="order", type="integer"),
     *             @OA\Property(property="is_featured", type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Created"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'name' => 'required|string|max:255',
                'slug' => 'nullable|string|unique:blog_categories,slug',
                'parent_id' => 'nullable|exists:blog_categories,id',
                'description' => 'nullable|string',
                'status' => 'in:draft,published',
                'created_by' => 'nullable|exists:users,id',
                'order' => 'integer',
                'is_featured' => 'boolean',
            ]);

            if (empty($data['slug'])) {
                $data['slug'] = Str::slug($data['name']);
            }

            $category = BlogCategory::create($data);
            return response()->json($category, 201);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create category',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/blog-categories/{id}",
     *     tags={"Blog Categories"},
     *     summary="Get a specific blog category",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Category ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function show($id)
    {
        try {
            $category = BlogCategory::findOrFail($id);
            return response()->json($category, 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Category not found'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch category',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/blog-categories/{id}",
     *     tags={"Blog Categories"},
     *     summary="Update a blog category",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Category ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="slug", type="string"),
     *             @OA\Property(property="parent_id", type="integer"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="status", type="string", enum={"draft", "published"}),
     *             @OA\Property(property="created_by", type="integer"),
     *             @OA\Property(property="order", type="integer"),
     *             @OA\Property(property="is_featured", type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Updated"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function update(Request $request, $id)
    {
        try {
            $category = BlogCategory::findOrFail($id);

            $data = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'slug' => 'sometimes|string|unique:blog_categories,slug,' . $category->id,
                'parent_id' => 'nullable|exists:blog_categories,id',
                'description' => 'nullable|string',
                'status' => 'in:draft,published',
                'created_by' => 'nullable|exists:users,id',
                'order' => 'integer',
                'is_featured' => 'boolean',
            ]);

            if (isset($data['name']) && empty($data['slug'])) {
                $data['slug'] = Str::slug($data['name']);
            }

            $category->update($data);
            return response()->json($category, 200);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $e->errors()
            ], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Category not found'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update category',
                'details' => $e->getMessage()
            ], 500);
        }
    }
/**
 * @OA\Delete(
 *     path="/api/blog-categories/{id}",
 *     tags={"Blog Categories"},
 *     summary="Delete a blog category",
 *     description="Deletes a specific blog category by its ID.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="Category ID",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Category deleted successfully",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Category deleted successfully.")
 *         )
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Category not found",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="success", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="Category not found.")
 *         )
 *     ),
 *     @OA\Response(
 *         response=500,
 *         description="Server error",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="success", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="Failed to delete category."),
 *             @OA\Property(property="details", type="string", example="Error details here.")
 *         )
 *     )
 * )
 */
public function destroy($id)
{
    try {
        $category = BlogCategory::findOrFail($id);
        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully.'
        ], 200);
    } catch (ModelNotFoundException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Category not found.'
        ], 404);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to delete category.',
            'details' => $e->getMessage()
        ], 500);
    }
}

}
