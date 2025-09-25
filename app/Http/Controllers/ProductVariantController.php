<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProductVariant;
use App\Models\Product;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use OpenApi\Annotations as OA;
use Illuminate\Validation\Rule;
class ProductVariantController extends Controller
{

    /**
     * @OA\Get(
     *     path="/api/product-variants",
     *     summary="Get Product Variants List",
     *     description="Fetches a list of product-variants.",
     *     tags={"Product Variants"}, 
     *      
     *     @OA\Parameter(name="page", in="query", description="Page number for pagination", example=1, @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="length", in="query", description="Number of records per page.", example=20, @OA\Schema(type="integer", minimum=1)),
     *
     *     @OA\Parameter(name="global", in="query", description="Global search for All field", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"published", "draft", "pending"})),
     *     @OA\Parameter(name="sort_by", in="query", description="Column name to sort by", @OA\Schema(type="string", enum={"id", "name", "created_at", "updated_at"})),
     *     @OA\Parameter(name="sort_dir", in="query", description="Sort direction (asc or desc)", example="asc", @OA\Schema(type="string", enum={"asc", "desc"})),
     *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function index(Request $request)
    {
        $recordsQuery = ProductVariant::with([
            'parentProduct:id,name,sku',
            'childProduct:id,name,sku',
            'attribute:id,name',
            'createdBy:id,username',
            'updatedBy:id,username'
        ]);

        // Searchable columns
        $searchableColumns = [
            'parent_products.name',
            'parent_products.sku',
            'child_products.name',
            'child_products.sku',
            'attributes.name',
            'created_users.username',
            'updated_users.username'
        ];

        $sortableColumns = array_merge(
            $searchableColumns,
            ['product_variants.created_at', 'product_variants.updated_at']
        );

        $sortBy = in_array($request->input('sort_by'), $sortableColumns)
            ? $request->input('sort_by')
            : 'product_variants.id';

        $sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        // Joins for searching/sorting
        $recordsQuery
            ->leftJoin('ec_products as parent_products', 'product_variants.parent_id', '=', 'parent_products.id')
            ->leftJoin('ec_products as child_products', 'product_variants.child_id', '=', 'child_products.id')
            ->leftJoin('attributes', 'product_variants.attribute_id', '=', 'attributes.id')
            ->leftJoin('users as created_users', 'product_variants.created_by', '=', 'created_users.id')
            ->leftJoin('users as updated_users', 'product_variants.updated_by', '=', 'updated_users.id');

        // Status filter
        if ($request->has('status') && in_array($request->status, ['published', 'draft', 'pending'])) {
            $recordsQuery->where('product_variants.status', $request->status);
        }

        // Global search
        if ($request->filled('global')) {
            $search = $request->input('global');
            $recordsQuery->where(function ($q) use ($searchableColumns, $search) {
                foreach ($searchableColumns as $col) {
                    $q->orWhere($col, 'LIKE', '%' . $search . '%');
                }
            });
        }

        // Sorting
        $recordsQuery->orderBy($sortBy, $sortDir);

        // Select columns
        $recordsQuery->addSelect([
            'product_variants.*',
            'parent_products.name as parent_name',
            'parent_products.sku as parent_sku',
            'child_products.name as child_name',
            'child_products.sku as child_sku',
            'attributes.name as attribute_name',
            'created_users.username as created_by_name',
            'updated_users.username as updated_by_name',
        ]);

        // Pagination
        if ($request->filled('page') && $request->filled('length')) {
            $totalRecords = (clone $recordsQuery)->count();
            $length = (int) $request->input('length');
            $totalPages = $totalRecords > 0 ? (int) ceil($totalRecords / $length) : 1;
            $page = (int) $request->input('page', 1);

            if ($page > $totalPages && $totalPages > 0) {
                $page = 1;
            }

            $records = $recordsQuery->offset(($page - 1) * $length)
                ->limit($length)
                ->get();
        } else {
            $records = $recordsQuery->get();
            $totalRecords = $records->count();
            $totalPages = 1;
            $page = 1;
        }

        return response()->json([
            'success' => true,
            'message' => __("msg_rec_list"),
            'total_pages' => $totalPages,
            'total_records' => $totalRecords,
            'data' => $records,
        ]);


    }
    /**
     * @OA\Post(
     *     path="/api/product-variants",
     *     tags={"Product Variants"},
     *     summary="Create a new product variant",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"label"},
     *             @OA\Property(property="parent_id", type="integer", example=1683),
     *             @OA\Property(property="child_id", type="integer", example=1683),
     *             @OA\Property(property="attribute_id", type="integer", example=9),
     *             @OA\Property(property="label", type="string", example="Red Color"),
     *             @OA\Property(property="type", type="string", example="Color")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Created"),
     *     @OA\Response(response=400, description="Bad Request"),
     *     @OA\Response(response=422, description="Validation Failed"),
     *     @OA\Response(response=500, description="Server Error")
     * )
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'parent_id' => [
                    'required',
                    'integer',
                    'exists:ec_products,id',
                ],
                'child_id' => [
                    'required',
                    'integer',
                    'exists:ec_products,id',
                    function ($attribute, $value, $fail) use ($request) {
                        if ($value == $request->parent_id) {
                            $fail('Parent and child cannot be the same.');
                        }
                    }
                ],
                'attribute_id' => [
                    'nullable',
                    'integer',
                    Rule::unique('product_variants')->where(function ($query) use ($request) {
                        return $query->where('parent_id', $request->parent_id)
                            ->where('child_id', $request->child_id)
                            ->where('attribute_id', $request->attribute_id);
                    }),
                ],
                'label' => 'required|string|max:255',
                'type' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            $data = $validator->validated();

            $data['created_by'] = Auth::id() ?? 1;
            // Create variant
            $variant = ProductVariant::create($data);
            return response()->json([
                'success' => true,
                'message' => 'Product Variant created successfully',
                'data' => $variant
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create Product Variant',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * @OA\Put(
     *     path="/api/product-variants/{id}",
     *     summary="Update an existing Product Variant",
     *     tags={"Product Variants"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Product Variant ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"label"},
     *             @OA\Property(property="parent_id", type="integer", example=1683),
     *             @OA\Property(property="child_id", type="integer", example=21191),
     *             @OA\Property(property="attribute_id", type="integer", example=9),
     *             @OA\Property(property="label", type="string", example="Red Color"),
     *             @OA\Property(property="type", type="string", example="Color")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Product Variant updated successfully"),
     *     @OA\Response(response=400, description="Bad Request"),
     *     @OA\Response(response=404, description="Product Variant not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Server Error"),
     *     security={{"bearerAuth":{}}}
     * )
     */





    public function update(Request $request, $id)
    {
        try {
            $variant = ProductVariant::find($id);

            if (!$variant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product Variant not found',
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'parent_id' => [
                    'required',
                    'integer',
                    'exists:ec_products,id',
                ],
                'child_id' => [
                    'required',
                    'integer',
                    'exists:ec_products,id',
                    function ($attribute, $value, $fail) use ($request) {
                        if ($value == $request->parent_id) {
                            $fail('Parent and child cannot be the same.');
                        }
                    }
                ],
                'attribute_id' => [
                    'nullable',
                    'integer',
                    Rule::unique('product_variants')->where(function ($query) use ($request) {
                        return $query->where('parent_id', $request->parent_id)
                            ->where('child_id', $request->child_id)
                            ->where('attribute_id', $request->attribute_id);
                    })->ignore($id),
                ],
                'label' => 'required|string|max:255',
                'type' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();
            $data['updated_by'] = Auth::id() ?? 1;

            $variant->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Product Variant updated successfully',
                'data' => $variant
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update Product Variant',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    /**
     * @OA\Delete(
     *     path="/api/product-variants/{id}",
     *     tags={"Product Variants"},
     *     summary="Delete a product variant",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=204, description="Deleted"),
     *     @OA\Response(response=404, description="Not Found"),
     * security={{"bearerAuth":{}}}
     * )
     */
    public function destroy($id)
    {
        try {
            $variant = ProductVariant::findOrFail($id);
            $variant->delete();



            return response()->json([
                'success' => true,
                'message' => 'product variant deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete product variant',
                'error' => $e->getMessage()
            ], 404);
        }
    }
}