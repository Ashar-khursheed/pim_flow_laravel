<?php

namespace App\Http\Controllers;

use App\Models\AttributeFamily;
use App\Models\EcProductCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(name="Attribute Families", description="API Endpoints for Attribute Families")
 */
class AttributeFamilyController extends Controller
{
    // public function __construct()
    // {
    //     $this->middleware('auth:sanctum'); // Ensure API is protected
    // }

    /**
     * @OA\Get(
     *     path="/api/attribute-families",
     *     summary="Get all attribute families",
     *     security={{"bearerAuth":{}}},
     *     tags={"Attribute Families"},
     *     @OA\Response(response=200, description="List of attribute families")
     * )
     */
    public function index()
    {
        return response()->json(AttributeFamily::all());
    }

    /**
     * @OA\Post(
     *     path="/api/attribute-families",
     *     summary="Create an attribute family",
     *     security={{"bearerAuth":{}}},
     *     tags={"Attribute Families"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "category_id"},
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="category_id", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Created successfully"),
     *     @OA\Response(response=400, description="Invalid category")
     * )
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:ec_product_categories,id',
        ]);

        // Ensure the category is a last-child category
        $category = EcProductCategory::where('id', $request->category_id)
            ->whereDoesntHave('children') // Must not have children
            ->first();

        if (!$category) {
            return response()->json(['message' => 'Invalid category. Only last-child categories are allowed.'], 400);
        }

        $attributeFamily = AttributeFamily::create($request->all());

        return response()->json($attributeFamily, 201);
    }

    /**
     * @OA\Get(
     *     path="/api/attribute-families/{id}",
     *     summary="Get a specific attribute family",
     *     security={{"bearerAuth":{}}},
     *     tags={"Attribute Families"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Attribute family found"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show($id)
    {
        $attributeFamily = AttributeFamily::find($id);
        if (!$attributeFamily) {
            return response()->json(['message' => 'Not Found'], 404);
        }
        return response()->json($attributeFamily);
    }

    /**
     * @OA\Put(
     *     path="/api/attribute-families/{id}",
     *     summary="Update an attribute family",
     *     security={{"bearerAuth":{}}},
     *     tags={"Attribute Families"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "category_id"},
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="category_id", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Updated successfully"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function update(Request $request, $id)
    {
        $attributeFamily = AttributeFamily::find($id);
        if (!$attributeFamily) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:ec_product_categories,id',
        ]);

        // Ensure the category is a last-child category
        $category = EcProductCategory::where('id', $request->category_id)
            ->whereDoesntHave('children')
            ->first();

        if (!$category) {
            return response()->json(['message' => 'Invalid category. Only last-child categories are allowed.'], 400);
        }

        $attributeFamily->update($request->all());

        return response()->json($attributeFamily);
    }

    /**
     * @OA\Delete(
     *     path="/api/attribute-families/{id}",
     *     summary="Delete an attribute family",
     *     security={{"bearerAuth":{}}},
     *     tags={"Attribute Families"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Deleted successfully"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function destroy($id)
    {
        $attributeFamily = AttributeFamily::find($id);
        if (!$attributeFamily) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $attributeFamily->delete();
        return response()->json(null, 204);
    }

    /**
     * @OA\Get(
     *     path="/api/categories/last-child",
     *     summary="Get all last-child categories (categories with no children)",
     *     security={{"bearerAuth":{}}},
     *     tags={"Categories"},
     *     @OA\Response(response=200, description="List of last-child categories")
     * )
     */
    public function lastChildCategories()
    {
        $categories = EcProductCategory::whereDoesntHave('children')->get();
        return response()->json($categories);
    }
}