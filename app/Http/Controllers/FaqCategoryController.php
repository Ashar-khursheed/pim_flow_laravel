<?php

namespace App\Http\Controllers;

use App\Models\FaqCategory;
use Illuminate\Http\Request;

class FaqCategoryController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/faq-categories",
     *     summary="Get all FAQ categories",
     *     security={{"bearerAuth":{}}},
     *     tags={"FAQ Categories"},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/FaqCategory"))
     *     )
     * )
     */
    public function index()
    {
        if (!auth()->user()->can('list faq category')) {
            return response()->json([
                'success' => false,
                'message' => "You don't have permission to access this module.",
            ]);
        }
        return response()->json(FaqCategory::with('faqs')->where('status', 'published')->get());
    }

    /**
     * @OA\Post(
     *     path="/api/faq-categories",
     *     summary="Create a new FAQ category",
     *     operationId="createFaqCategory",
     *     tags={"FAQ Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "order", "status"},
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="order", type="integer"),
     *             @OA\Property(property="status", type="string", enum={"published", "draft"}),
     *             @OA\Property(property="description", type="string", maxLength=400)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="FAQ category created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/FaqCategory")
     *     )
     * )
     */
    public function store(Request $request)
    {
        if (!auth()->user()->can('add faq category')) {
            return response()->json([
                'success' => false,
                'message' => "You don't have permission to access this module.",
            ]);
        }
        $request->validate([
            'name' => 'required|string',
            'order' => 'required|integer',
            'status' => 'required|string|in:published,draft',
            'description' => 'nullable|string|max:400',
        ]);

        $category = FaqCategory::create($request->all());

        return response()->json($category, 201);
    }

    /**
     * @OA\Get(
     *     path="/api/faq-categories/{id}",
     *     summary="Get a specific FAQ category by ID",
     *     operationId="getFaqCategoryById",
     *     tags={"FAQ Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The ID of the FAQ category to retrieve",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="FAQ category details",
     *         @OA\JsonContent(ref="#/components/schemas/FaqCategory")
     *     )
     * )
     */
    public function show(FaqCategory $category)
    {
        if (!auth()->user()->can('view faq category')) {
            return response()->json([
                'success' => false,
                'message' => "You don't have permission to access this module.",
            ]);
        }
        return response()->json($category->load('faqs'));
    }

    /**
     * @OA\Put(
     *     path="/api/faq-categories/{id}",
     *     summary="Update an existing FAQ category",
     *     operationId="updateFaqCategory",
     *     tags={"FAQ Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="FAQ category ID to update",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="order", type="integer"),
     *             @OA\Property(property="status", type="string", enum={"published", "draft"}),
     *             @OA\Property(property="description", type="string", maxLength=400)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="FAQ category updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/FaqCategory")
     *     )
     * )
     */
    public function update(Request $request, FaqCategory $category)
    {
        if (!auth()->user()->can('update faq category')) {
            return response()->json([
                'success' => false,
                'message' => "You don't have permission to access this module.",
            ]);
        }
        $request->validate([
            'name' => 'sometimes|string',
            'order' => 'sometimes|integer',
            'status' => 'sometimes|string|in:published,draft',
            'description' => 'nullable|string|max:400',
        ]);

        $category->update($request->all());

        return response()->json($category);
    }

    /**
     * @OA\Delete(
     *     path="/api/faq-categories/{id}",
     *     summary="Delete an FAQ category",
     *     operationId="deleteFaqCategory",
     *     tags={"FAQ Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="FAQ category ID to delete",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="FAQ category deleted successfully",
     *         @OA\JsonContent(type="object", @OA\Property(property="message", type="string"))
     *     )
     * )
     */
    public function destroy(FaqCategory $category)
    {
        if (!auth()->user()->can('delete faq category')) {
            return response()->json([
                'success' => false,
                'message' => "You don't have permission to access this module.",
            ]);
        }
        $category->delete();
        return response()->json(['message' => 'Category deleted successfully']);
    }
}

/**
 * @OA\Schema(
 *     schema="FaqCategory",
 *     type="object",
 *     title="FAQ Category",
 *     description="Schema for an FAQ category",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="General"),
 *     @OA\Property(property="order", type="integer", example=1),
 *     @OA\Property(property="status", type="string", enum={"published", "draft"}, example="published"),
 *     @OA\Property(property="description", type="string", example="General questions category"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
