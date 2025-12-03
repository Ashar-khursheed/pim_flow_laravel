<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProductVariant;
use App\Models\Product;
use App\Models\ProductAttribute;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use OpenApi\Annotations as OA;
use Illuminate\Validation\Rule;
use App\Models\Attribute;
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
     *     @OA\Parameter(name="sort_by", in="query", description="Column name to sort by", @OA\Schema(type="string", enum={"id"})),
     *     @OA\Parameter(name="sort_dir", in="query", description="Sort direction (asc or desc)", example="asc", @OA\Schema(type="string", enum={"asc", "desc"})),
     *     @OA\Response(response=200, description="Success", @OA\MediaType(mediaType="application/json")),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function index(Request $request)
    {
        $recordsQuery = ProductVariant::with([
            'parentProduct:id,name,sku',
            'createdBy:id,username',
            'updatedBy:id,username'
        ]);

        // Searchable columns (only parent + children (JSON) + attributes inside variants)
        $searchableColumns = [
            'parent_products.name',
            'parent_products.sku',
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

        // Join parent + users
        $recordsQuery
            ->leftJoin('ec_products as parent_products', 'product_variants.parent_id', '=', 'parent_products.id')
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

        // 🔥 Decode child_ids + variants JSON and enrich
        $records = $records->map(function ($row) {
            $childIds = json_decode($row->child_ids, true) ?? [];
            $variants = json_decode($row->variants, true) ?? [];

            // Load child products
            $children = \DB::table('ec_products')
                ->whereIn('id', $childIds)
                ->get(['id', 'name', 'sku']);

            // Load attributes for variants
            $attributeIds = collect($variants)->pluck('attribute_id')->filter()->all();
            $attributes = \DB::table('attributes')
                ->whereIn('id', $attributeIds)
                ->pluck('name', 'id');

            $row->children = $children->map(function ($c) {
                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'sku' => $c->sku,
                ];
            });

            $row->variants = collect($variants)->map(function ($v) use ($attributes) {
                return [
                    'attribute_id' => $v['attribute_id'],
                    'attribute_name' => $attributes[$v['attribute_id']] ?? null,
                    'labels' => $v['labels'] ?? null,
                    'type' => $v['type'] ?? null,
                ];
            });

            $data = [

                'id' => $row->id,
                'parent_id' => $row->parent_id,
                'parent_name' => $row->parent_name,
                'parent_sku' => $row->parent_sku,
                'created_by' => $row->created_by_name,
                'updated_by' => $row->updated_by_name,
                'variants' => $row->variants,
                'child' => $row->children,

            ];

            return $data;
        });
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
     *     summary="Create a new product variant with multiple children and variant details",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"parent_id", "child_ids", "variants"},
     *             @OA\Property(property="parent_id", type="integer", example=1683),
     *             @OA\Property(
     *                 property="child_ids",
     *                 type="array",
     *                 description="Array of child product IDs",
     *                 @OA\Items(type="integer", example=1683),
     *                 example={1683, 1795, 1818}
     *             ),
     *             @OA\Property(
     *                 property="variants",
     *                 type="array",
     *                 description="Array of variant details",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"attribute_id","labels","type"},
     *                     @OA\Property(property="attribute_id", type="integer", example=7),
     *                     @OA\Property(property="labels", type="string", example="Red Color"),
     *                     @OA\Property(property="type", type="string", example="radio")
     *                 ),
     *                 example={
     *                     {"attribute_id":7,"labels":"Red Color","type":"radio"},
     *                     {"attribute_id":22,"labels":"Green Color","type":"dropdown"},
     *                     {"attribute_id":48,"labels":"Blue Color","type":"radio"}
     *                 }
     *             )
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
                'parent_id' => 'required|integer|unique:product_variants,parent_id|exists:ec_products,id',

                // child_ids should be an array of product IDs
                'child_ids' => 'required|array|min:1',

                // variants should be array of objects
                'variants' => 'required|array|min:1',
                'variants.*.attribute_id' => 'required|integer|exists:attributes,id',
                'variants.*.labels' => 'required|string|max:255',
                'variants.*.type' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();

            // $createdRecord = ProductVariant::create([
            //     'parent_id' => $data['parent_id'],
            //     'child_ids' => json_encode($data['child_ids']),
            //     'variants' => json_encode($data['variants']),
            //     'created_by' => Auth::id() ?? 1,
            // ]);


            // Normalize child IDs
            $childIds = is_array($data['child_ids'])
                ? $data['child_ids']
                : array_map('intval', explode(',', $data['child_ids']));

            // Remove duplicates and parent ID itself if mistakenly included
            $childIds = array_values(array_unique(array_diff($childIds, [$data['parent_id']])));

            // ✅ Create parent record
            $createdRecord = ProductVariant::updateOrCreate(
                ['parent_id' => $data['parent_id']],
                [
                    'child_ids' => json_encode($childIds),
                    'variants' => json_encode($data['variants'] ?? []),
                    'created_by' => Auth::id() ?? 1,
                ]
            );

            // ✅ Create reciprocal child records
            foreach ($childIds as $childId) {
                $relatedIds = array_diff(array_merge([$data['parent_id']], $childIds), [$childId]);

                ProductVariant::updateOrCreate(
                    ['parent_id' => $childId],
                    [
                        'child_ids' => json_encode(array_values($relatedIds)),
                        'variants' => json_encode($data['variants'] ?? []),
                        'created_by' => Auth::id() ?? 1,
                    ]
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Product Variant created successfully',
                'data' => $createdRecord
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
     *     tags={"Product Variants"},
     *     summary="Update an existing Product Variant",
     *     security={{"bearerAuth":{}}},
     * @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Product Variant ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"parent_id", "child_ids", "variants"},
     *             @OA\Property(property="parent_id", type="integer", example=1683),
     *             @OA\Property(
     *                 property="child_ids",
     *                 type="array",
     *                 description="Array of child product IDs",
     *                 @OA\Items(type="integer", example=1683),
     *                 example={1683, 1795, 1818}
     *             ),
     *             @OA\Property(
     *                 property="variants",
     *                 type="array",
     *                 description="Array of variant details",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"attribute_id","labels","type"},
     *                     @OA\Property(property="attribute_id", type="integer", example=7),
     *                     @OA\Property(property="labels", type="string", example="Red Color"),
     *                     @OA\Property(property="type", type="string", example="radio")
     *                 ),
     *                 example={
     *                     {"attribute_id":7,"labels":"Red Color","type":"radio"},
     *                     {"attribute_id":22,"labels":"Green Color","type":"dropdown"},
     *                     {"attribute_id":48,"labels":"Blue Color","type":"radio"}
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Created"),
     *     @OA\Response(response=400, description="Bad Request"),
     *     @OA\Response(response=422, description="Validation Failed"),
     *     @OA\Response(response=500, description="Server Error")
     * )
     */
    public function update(Request $request, $id)
    {
        try {

            $validator = Validator::make($request->all(), [
                'parent_id' => 'required|integer|unique:product_variants,parent_id,' . $id . ',id|exists:ec_products,id',

                // child_ids should be an array of product IDs
                'child_ids' => 'required|array|min:1',
                // variants should be array of objects
                'variants' => 'required|array|min:1',
                'variants.*.attribute_id' => 'required|integer|exists:attributes,id',
                'variants.*.labels' => 'required|string|max:255',
                'variants.*.type' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            $variant = ProductVariant::find($id);

            if (!$variant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product Variant not found',
                ], 404);
            }


            $data = $validator->validated();


            $parentId = (int) $data['parent_id'];

            $childIds = is_array($data['child_ids'])
                ? $data['child_ids']
                : array_map('intval', explode(',', $data['child_ids']));

            $childIds = array_values(array_unique(array_diff($childIds, [$parentId])));


            $existingVariant = ProductVariant::where('parent_id', $parentId)->first();
            $oldChildIds = is_array($existingVariant?->child_ids)
                ? $existingVariant->child_ids
                : json_decode($existingVariant?->child_ids ?? '[]', true);


            $variant->update([
                'parent_id' => $parentId,
                'child_ids' => json_encode($childIds),
                'variants' => json_encode($data['variants'] ?? []),
                'updated_by' => Auth::id() ?? 1,
            ]);

            foreach ($childIds as $childId) {
                $relatedIds = array_diff(array_merge([$parentId], $childIds), [$childId]);

                ProductVariant::updateOrCreate(
                    ['parent_id' => $childId],
                    [
                        'child_ids' => json_encode(array_values($relatedIds)),
                        'variants' => json_encode($data['variants'] ?? []),
                        'updated_by' => Auth::id() ?? 1,
                    ]
                );
            }

            $removedIds = array_diff($oldChildIds, $childIds);
            if (!empty($removedIds)) {
                foreach ($removedIds as $removedId) {
                    $record = ProductVariant::where('parent_id', $removedId)->first();
                    if ($record) {
                        $updatedChildren = array_diff(
                            is_array($record->child_ids) ? $record->child_ids : json_decode($record->child_ids ?? '[]', true),
                            [$parentId]
                        );
                        $record->update(['child_ids' => json_encode(array_values($updatedChildren))]);
                    }
                }
            }

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
     * @OA\Post(
     *     path="/api/product-variants/getProductAttibute",
     *     tags={"Product Variants"},
     *     summary="Get attributes by product IDs",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"product_ids"},
     *                 @OA\Property(
     *                     property="product_ids",
     *                     type="array",
     *                     description="Array of product IDs",
     *                     @OA\Items(type="integer", example=1),
     *                     example={1683,1795,1818}
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Attributes fetched successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="product_id", type="integer", example=1),
     *                     @OA\Property(
     *                         property="attributes",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="attribute_id", type="integer", example=7),
     *                             @OA\Property(property="attribute_name", type="string", example="Color")
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not Found"),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function getProductAttibute(Request $request)
    {
        try {
            

                        
                        
            $validator = Validator::make($request->all(), [
                'product_ids' => 'required|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $productIds = $request->product_ids;

            if (empty($productIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No product IDs provided'
                ], 422);
            }

            $countId = count($productIds);

            // Fetch attributes
            $attributes = ProductAttribute::whereIn('product_id', $productIds)
                ->join('attributes', 'attributes.id', '=', 'product_attributes.attribute_id')
                ->select(
                    'product_attributes.attribute_id',
                    'product_attributes.attribute_value', // ADDED THIS
                    'attributes.name as attribute_name',
                    \DB::raw('GROUP_CONCAT(DISTINCT product_attributes.product_id ORDER BY product_attributes.product_id) as product_ids'),
                    \DB::raw('COUNT(DISTINCT product_attributes.product_id) as product_count')
                )
                ->groupBy('product_attributes.attribute_id', 'product_attributes.attribute_value', 'attributes.name')
                ->having('product_count', '=', $countId)
                ->get();

            $attributeList = $attributes->map(function ($attr) {
                return [
                    'attribute_id' => $attr->attribute_id,
                    'attribute_name' => $attr->attribute_name,                    
                    'attribute_value' => $attr->attribute_value,                    
                    'group_id' => $attr->product_ids,
                ];
            });

            if($attributeList->isEmpty())
            {
                    return response()->json([
                            'success' => true,
                            'message' => 'No attributes found with all these products',
                            'data' => $attributeList
                        ], 200);
            }            
            return response()->json([
                'success' => true,
                'message' => 'Attributes fetched successfully',
                'data' => $attributeList
            ], 200);
  

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch attributes',
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

            $parentVariant = ProductVariant::findOrFail($id);

            $childIds = json_decode($parentVariant->child_ids ?? '[]', true);
            if (!is_array($childIds)) {
                $childIds = [];
            }


            foreach ($childIds as $childId) {
                $childVariant = ProductVariant::where('parent_id', $childId)->first();

                if ($childVariant) {
                    $updatedChildIds = json_decode($childVariant->child_ids ?? '[]', true) ?? [];
                    $updatedChildIds = array_diff($updatedChildIds, [$parentVariant->parent_id]);


                    $childVariant->update([
                        'child_ids' => json_encode(array_values($updatedChildIds)),
                        'updated_by' => Auth::id() ?? 1,
                    ]);

                    if (empty($updatedChildIds)) {
                        $childVariant->delete();
                    }
                }
            }
            $parentVariant->delete();

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

    /**
     * @OA\Post(
     *     path="/api/product-variants/show",
     *     summary="Get list of child with attribute by product ID",
     *     tags={"Product Variants"},
     *     @OA\Parameter(
     *         name="variant_id",
     *         in="query",
     *         required=false,
     *         description="Filter by product variants ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product Variants retrieved successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *      security={{"bearerAuth":{}}}
     * )
     */
    public function show(Request $request)
    {
        try {

            $validator = Validator::make($request->all(), [
                'variant_id' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            // Ensure variant_id is always an array
            $productIds = $request->variant_id;

            if (empty($productIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No product IDs provided'
                ], 422);
            }
            $variant = ProductVariant::where('id', $productIds)->with([
                'parentProduct:id,name,sku',
                'createdBy:id,username',
                'updatedBy:id,username'
            ])->first();

            $childIds = json_decode($variant->child_ids, true) ?? [];
            $variants = json_decode($variant->variants, true) ?? [];

            // Load child products
            $children = \DB::table('ec_products')
                ->whereIn('id', $childIds)
                ->get(['id', 'name', 'sku']);

            // Load attributes for variants
            $attributeIds = collect($variants)->pluck('attribute_id')->filter()->all();
            $attributes = \DB::table('attributes')
                ->whereIn('id', $attributeIds)
                ->pluck('name', 'id');

            $variant->children = $children->map(function ($c) {
                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'sku' => $c->sku,
                ];
            });

            $variant->variants = collect($variants)->map(function ($v) use ($attributes) {
                return [
                    'attribute_id' => $v['attribute_id'],
                    'attribute_name' => $attributes[$v['attribute_id']] ?? null,
                    'labels' => $v['labels'] ?? null,
                    'type' => $v['type'] ?? null,
                ];
            });

            $data = [

                'id' => $variant->id,
                'parent_id' => $variant->parent_id,
                'parent_name' => $variant->parentProduct?->name,
                'parent_sku' => $variant->parentProduct?->sku,
                'created_by' => $variant->createdBy?->username,
                'updated_by' => $variant->updatedBy?->username,
                'variants' => $variant->variants,
                'child_ids' => $variant->children,

            ];



            return response()->json([
                'success' => true,
                'message' => "Fetch Product variant",
                'data' => $data
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch Product variant',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}