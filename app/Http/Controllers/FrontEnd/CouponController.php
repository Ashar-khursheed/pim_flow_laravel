<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Models\FrontEnd\Coupon;
use App\Models\FrontEnd\CouponUsage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;
class CouponController extends Controller
{
#[OA\Get(
    path: '/api/coupons',
    summary: 'Get all coupons',
    security: [['bearerAuth' => []]], 
    tags: ['Coupons'],
    parameters: [
        new OA\Parameter(name: 'status', in: 'query', description: 'Filter by status', schema: new OA\Schema(type: 'string', enum: ['pending', 'approved', 'rejected'])),
        new OA\Parameter(name: 'basis', in: 'query', description: 'Filter by basis', schema: new OA\Schema(type: 'string', enum: ['customer', 'category', 'product', 'promotional'])),
        new OA\Parameter(name: 'is_active', in: 'query', description: 'Filter by active status', schema: new OA\Schema(type: 'boolean')),
        new OA\Parameter(name: 'per_page', in: 'query', description: 'Items per page', schema: new OA\Schema(type: 'integer', default: 15)),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Successful operation',
            content: new OA\JsonContent(
                properties: [
                    'success' => new OA\Property(property: 'success', type: 'boolean'),
                    'data' => new OA\Property(property: 'data', type: 'object'),
                    'message' => new OA\Property(property: 'message', type: 'string'),
                ]
            )
        )
    ]
)]
public function index(Request $request): JsonResponse
{
    $query = Coupon::with(['creator', 'approver', 'customers', 'categories', 'products']);

    if ($request->has('status')) {
        $query->where('status', $request->status);
    }

    if ($request->has('basis')) {
        $query->byBasis($request->basis);
    }

    if ($request->has('is_active')) {
        $query->where('is_active', $request->boolean('is_active'));
    }

    $coupons = $query->paginate($request->get('per_page', 15));

    return response()->json([
        'success' => true,
        'data' => $coupons,
        'message' => 'Coupons retrieved successfully'
    ]);
}


    #[OA\Post(
        path: '/api/coupons',
        summary: 'Create a new coupon',
        tags: ['Coupons'],
         security: [['bearerAuth' => []]], 
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['code', 'name', 'type', 'value', 'basis', 'start_date', 'expire_date'],
                properties: [
                    'code' => new OA\Property(property: 'code', type: 'string', maxLength: 255),
                    'name' => new OA\Property(property: 'name', type: 'string', maxLength: 255),
                    'description' => new OA\Property(property: 'description', type: 'string'),
                    'type' => new OA\Property(property: 'type', type: 'string', enum: ['fixed', 'percentage']),
                    'value' => new OA\Property(property: 'value', type: 'number', format: 'float'),
                    'basis' => new OA\Property(property: 'basis', type: 'string', enum: ['customer', 'category', 'product', 'promotional']),
                    'min_order_value' => new OA\Property(property: 'min_order_value', type: 'number', format: 'float'),
                    'max_order_value' => new OA\Property(property: 'max_order_value', type: 'number', format: 'float'),
                    'usage_type' => new OA\Property(property: 'usage_type', type: 'string', enum: ['once', 'multiple']),
                    'usage_limit' => new OA\Property(property: 'usage_limit', type: 'integer'),
                    'usage_limit_per_customer' => new OA\Property(property: 'usage_limit_per_customer', type: 'integer'),
                    'start_date' => new OA\Property(property: 'start_date', type: 'string', format: 'date-time'),
                    'expire_date' => new OA\Property(property: 'expire_date', type: 'string', format: 'date-time'),
                    'is_active' => new OA\Property(property: 'is_active', type: 'boolean'),
                    'customer_ids' => new OA\Property(property: 'customer_ids', type: 'array', items: new OA\Items(type: 'integer')),
                    'category_ids' => new OA\Property(property: 'category_ids', type: 'array', items: new OA\Items(type: 'integer')),
                    'product_ids' => new OA\Property(property: 'product_ids', type: 'array', items: new OA\Items(type: 'integer')),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Coupon created successfully',
                content: new OA\JsonContent(
                    properties: [
                        'success' => new OA\Property(property: 'success', type: 'boolean'),
                        'data' => new OA\Property(property: 'data', type: 'object'),
                        'message' => new OA\Property(property: 'message', type: 'string'),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Validation errors')
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:255|unique:coupons,code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:fixed,percentage',
            'value' => 'required|numeric|min:0',
            'basis' => 'required|in:customer,category,product,promotional',
            'min_order_value' => 'nullable|numeric|min:0',
            'max_order_value' => 'nullable|numeric|min:0|gte:min_order_value',
            'usage_type' => 'required|in:once,multiple',
            'usage_limit' => 'nullable|integer|min:1',
            'usage_limit_per_customer' => 'nullable|integer|min:1',
            'start_date' => 'required|date|after_or_equal:today',
            'expire_date' => 'required|date|after:start_date',
            'is_active' => 'boolean',
            'customer_ids' => 'nullable|array',
            'customer_ids.*' => 'exists:users,id',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'exists:ec_products,id',
        ]);

        $validated['created_by'] = auth()->id();

        $coupon = Coupon::create($validated);

        // Attach relationships based on basis
        if ($validated['basis'] === 'customer' && !empty($validated['customer_ids'])) {
            $coupon->customers()->attach($validated['customer_ids']);
        }

        if ($validated['basis'] === 'category' && !empty($validated['category_ids'])) {
            $coupon->categories()->attach($validated['category_ids']);
        }

        if ($validated['basis'] === 'product' && !empty($validated['product_ids'])) {
            $coupon->products()->attach($validated['product_ids']);
        }

        $coupon->load(['creator', 'customers', 'categories', 'products']);

        return response()->json([
            'success' => true,
            'data' => $coupon,
            'message' => 'Coupon created successfully'
        ], 201);
    }

    #[OA\Get(
        path: '/api/coupons/{id}',
        summary: 'Get coupon by ID',
        tags: ['Coupons'],
         security: [['bearerAuth' => []]], 
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Coupon ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation',
                content: new OA\JsonContent(
                    properties: [
                        'success' => new OA\Property(property: 'success', type: 'boolean'),
                        'data' => new OA\Property(property: 'data', type: 'object'),
                        'message' => new OA\Property(property: 'message', type: 'string'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Coupon not found')
        ]
    )]
    public function show(Coupon $coupon): JsonResponse
    {
        $coupon->load(['creator', 'approver', 'customers', 'categories', 'products', 'usages.customer']);

        return response()->json([
            'success' => true,
            'data' => $coupon,
            'message' => 'Coupon retrieved successfully'
        ]);
    }

    #[OA\Put(
        path: '/api/coupons/{id}',
        summary: 'Update coupon',
        tags: ['Coupons'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Coupon ID', schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    'code' => new OA\Property(property: 'code', type: 'string', maxLength: 255),
                    'name' => new OA\Property(property: 'name', type: 'string', maxLength: 255),
                    'description' => new OA\Property(property: 'description', type: 'string'),
                    'type' => new OA\Property(property: 'type', type: 'string', enum: ['fixed', 'percentage']),
                    'value' => new OA\Property(property: 'value', type: 'number', format: 'float'),
                    'basis' => new OA\Property(property: 'basis', type: 'string', enum: ['customer', 'category', 'product', 'promotional']),
                    'min_order_value' => new OA\Property(property: 'min_order_value', type: 'number', format: 'float'),
                    'max_order_value' => new OA\Property(property: 'max_order_value', type: 'number', format: 'float'),
                    'usage_type' => new OA\Property(property: 'usage_type', type: 'string', enum: ['once', 'multiple']),
                    'usage_limit' => new OA\Property(property: 'usage_limit', type: 'integer'),
                    'usage_limit_per_customer' => new OA\Property(property: 'usage_limit_per_customer', type: 'integer'),
                    'start_date' => new OA\Property(property: 'start_date', type: 'string', format: 'date-time'),
                    'expire_date' => new OA\Property(property: 'expire_date', type: 'string', format: 'date-time'),
                    'is_active' => new OA\Property(property: 'is_active', type: 'boolean'),
                    'customer_ids' => new OA\Property(property: 'customer_ids', type: 'array', items: new OA\Items(type: 'integer')),
                    'category_ids' => new OA\Property(property: 'category_ids', type: 'array', items: new OA\Items(type: 'integer')),
                    'product_ids' => new OA\Property(property: 'product_ids', type: 'array', items: new OA\Items(type: 'integer')),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Coupon updated successfully',
                content: new OA\JsonContent(
                    properties: [
                        'success' => new OA\Property(property: 'success', type: 'boolean'),
                        'data' => new OA\Property(property: 'data', type: 'object'),
                        'message' => new OA\Property(property: 'message', type: 'string'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Coupon not found'),
            new OA\Response(response: 422, description: 'Validation errors')
        ]
    )]
    public function update(Request $request, Coupon $coupon): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('coupons')->ignore($coupon->id)],
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'sometimes|required|in:fixed,percentage',
            'value' => 'sometimes|required|numeric|min:0',
            'basis' => 'sometimes|required|in:customer,category,product,promotional',
            'min_order_value' => 'nullable|numeric|min:0',
            'max_order_value' => 'nullable|numeric|min:0|gte:min_order_value',
            'usage_type' => 'sometimes|required|in:once,multiple',
            'usage_limit' => 'nullable|integer|min:1',
            'usage_limit_per_customer' => 'nullable|integer|min:1',
            'start_date' => 'sometimes|required|date',
            'expire_date' => 'sometimes|required|date|after:start_date',
            'is_active' => 'boolean',
            'customer_ids' => 'nullable|array',
            'customer_ids.*' => 'exists:users,id',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'exists:products,id',
        ]);

        $coupon->update($validated);

        // Update relationships based on basis
        if (isset($validated['basis']) || isset($validated['customer_ids'])) {
            $coupon->customers()->detach();
            if ($coupon->basis === 'customer' && !empty($validated['customer_ids'])) {
                $coupon->customers()->attach($validated['customer_ids']);
            }
        }

        if (isset($validated['basis']) || isset($validated['category_ids'])) {
            $coupon->categories()->detach();
            if ($coupon->basis === 'category' && !empty($validated['category_ids'])) {
                $coupon->categories()->attach($validated['category_ids']);
            }
        }

        if (isset($validated['basis']) || isset($validated['product_ids'])) {
            $coupon->products()->detach();
            if ($coupon->basis === 'product' && !empty($validated['product_ids'])) {
                $coupon->products()->attach($validated['product_ids']);
            }
        }

        $coupon->load(['creator', 'approver', 'customers', 'categories', 'products']);

        return response()->json([
            'success' => true,
            'data' => $coupon,
            'message' => 'Coupon updated successfully'
        ]);
    }

    #[OA\Delete(
        path: '/api/coupons/{id}',
        summary: 'Delete coupon',
        tags: ['Coupons'],
         security: [['bearerAuth' => []]], 
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Coupon ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Coupon deleted successfully',
                content: new OA\JsonContent(
                    properties: [
                        'success' => new OA\Property(property: 'success', type: 'boolean'),
                        'message' => new OA\Property(property: 'message', type: 'string'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Coupon not found')
        ]
    )]
    public function destroy(Coupon $coupon): JsonResponse
    {
        $coupon->delete();

        return response()->json([
            'success' => true,
            'message' => 'Coupon deleted successfully'
        ]);
    }

    #[OA\Post(
        path: '/api/coupons/{id}/approve',
        summary: 'Approve coupon',
        tags: ['Coupons'],
         security: [['bearerAuth' => []]], 
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Coupon ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Coupon approved successfully',
                content: new OA\JsonContent(
                    properties: [
                        'success' => new OA\Property(property: 'success', type: 'boolean'),
                        'data' => new OA\Property(property: 'data', type: 'object'),
                        'message' => new OA\Property(property: 'message', type: 'string'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Coupon not found'),
            new OA\Response(response: 400, description: 'Coupon already approved or rejected')
        ]
    )]
    public function approve(Coupon $coupon): JsonResponse
    {
        if ($coupon->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Coupon is already ' . $coupon->status
            ], 400);
        }

        $coupon->approve(auth()->id());
        $coupon->load(['creator', 'approver']);

        return response()->json([
            'success' => true,
            'data' => $coupon,
            'message' => 'Coupon approved successfully'
        ]);
    }

    #[OA\Post(
        path: '/api/coupons/{id}/reject',
        summary: 'Reject coupon',
        tags: ['Coupons'],
         security: [['bearerAuth' => []]], 
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Coupon ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Coupon rejected successfully',
                content: new OA\JsonContent(
                    properties: [
                        'success' => new OA\Property(property: 'success', type: 'boolean'),
                        'data' => new OA\Property(property: 'data', type: 'object'),
                        'message' => new OA\Property(property: 'message', type: 'string'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Coupon not found'),
            new OA\Response(response: 400, description: 'Coupon already approved or rejected')
        ]
    )]
    public function reject(Coupon $coupon): JsonResponse
    {
        if ($coupon->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Coupon is already ' . $coupon->status
            ], 400);
        }

        $coupon->reject();

        return response()->json([
            'success' => true,
            'data' => $coupon,
            'message' => 'Coupon rejected successfully'
        ]);
    }

    #[OA\Post(
        path: '/api/coupons/validate',
        summary: 'Validate coupon code',
        tags: ['Coupons'],
         security: [['bearerAuth' => []]], 
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['code', 'customer_id', 'order_value'],
                properties: [
                    'code' => new OA\Property(property: 'code', type: 'string'),
                    'customer_id' => new OA\Property(property: 'customer_id', type: 'integer'),
                    'order_value' => new OA\Property(property: 'order_value', type: 'number', format: 'float'),
                    'category_ids' => new OA\Property(property: 'category_ids', type: 'array', items: new OA\Items(type: 'integer')),
                    'product_ids' => new OA\Property(property: 'product_ids', type: 'array', items: new OA\Items(type: 'integer')),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Coupon validation result',
                content: new OA\JsonContent(
                    properties: [
                        'success' => new OA\Property(property: 'success', type: 'boolean'),
                        'data' => new OA\Property(
                            property: 'data',
                            properties: [
                                'valid' => new OA\Property(property: 'valid', type: 'boolean'),
                                'discount_amount' => new OA\Property(property: 'discount_amount', type: 'number'),
                                'coupon' => new OA\Property(property: 'coupon', type: 'object'),
                                'message' => new OA\Property(property: 'message', type: 'string'),
                            ],
                            type: 'object'
                        ),
                        'message' => new OA\Property(property: 'message', type: 'string'),
                    ]
                )
            )
        ]
    )]
    public function validate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string',
            'customer_id' => 'required|exists:users,id',
            'order_value' => 'required|numeric|min:0',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'exists:products,id',
        ]);

        $coupon = Coupon::where('code', $validated['code'])->first();

        if (!$coupon) {
            return response()->json([
                'success' => false,
                'data' => [
                    'valid' => false,
                    'discount_amount' => 0,
                    'message' => 'Coupon code not found'
                ],
                'message' => 'Coupon validation failed'
            ]);
        }

        // Check if coupon is valid
        if (!$coupon->canBeUsedByCustomer($validated['customer_id'])) {
            $message = 'Coupon cannot be used';
            if (!$coupon->isValid()) {
                $message = 'Coupon is inactive, expired, or not approved';
            } elseif ($coupon->hasReachedUsageLimit()) {
                $message = 'Coupon usage limit reached';
            } elseif ($coupon->usage_type === 'once' && $coupon->usages()->where('customer_id', $validated['customer_id'])->exists()) {
                $message = 'Coupon already used by this customer';
            }

            return response()->json([
                'success' => false,
                'data' => [
                    'valid' => false,
                    'discount_amount' => 0,
                    'coupon' => $coupon,
                    'message' => $message
                ],
                'message' => 'Coupon validation failed'
            ]);
        }

        // Check basis-specific validations
        if ($coupon->basis === 'customer') {
            if (!$coupon->customers()->where('customer_id', $validated['customer_id'])->exists()) {
                return response()->json([
                    'success' => false,
                    'data' => [
                        'valid' => false,
                        'discount_amount' => 0,
                        'coupon' => $coupon,
                        'message' => 'Coupon not applicable for this customer'
                    ],
                    'message' => 'Coupon validation failed'
                ]);
            }
        }

        if ($coupon->basis === 'category' && !empty($validated['category_ids'])) {
            $validCategories = $coupon->categories()->pluck('categories.id')->toArray();
            if (empty(array_intersect($validated['category_ids'], $validCategories))) {
                return response()->json([
                    'success' => false,
                    'data' => [
                        'valid' => false,
                        'discount_amount' => 0,
                        'coupon' => $coupon,
                        'message' => 'Coupon not applicable for these categories'
                    ],
                    'message' => 'Coupon validation failed'
                ]);
            }
        }

        if ($coupon->basis === 'product' && !empty($validated['product_ids'])) {
            $validProducts = $coupon->products()->pluck('products.id')->toArray();
            if (empty(array_intersect($validated['product_ids'], $validProducts))) {
                return response()->json([
                    'success' => false,
                    'data' => [
                        'valid' => false,
                        'discount_amount' => 0,
                        'coupon' => $coupon,
                        'message' => 'Coupon not applicable for these products'
                    ],
                    'message' => 'Coupon validation failed'
                ]);
            }
        }

        // Calculate discount
        $discountAmount = $coupon->calculateDiscount($validated['order_value']);

        if ($discountAmount <= 0) {
            return response()->json([
                'success' => false,
                'data' => [
                    'valid' => false,
                    'discount_amount' => 0,
                    'coupon' => $coupon,
                    'message' => 'Order value does not meet coupon requirements'
                ],
                'message' => 'Coupon validation failed'
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'valid' => true,
                'discount_amount' => $discountAmount,
                'coupon' => $coupon,
                'message' => 'Coupon is valid'
            ],
            'message' => 'Coupon validated successfully'
        ]);
    }

    #[OA\Post(
        path: '/api/coupons/apply',
        summary: 'Apply coupon to order',
        tags: ['Coupons'],
         security: [['bearerAuth' => []]], 
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['code', 'customer_id', 'order_value'],
                properties: [
                    'code' => new OA\Property(property: 'code', type: 'string'),
                    'customer_id' => new OA\Property(property: 'customer_id', type: 'integer'),
                    'order_value' => new OA\Property(property: 'order_value', type: 'number', format: 'float'),
                    'order_id' => new OA\Property(property: 'order_id', type: 'integer'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Coupon applied successfully',
                content: new OA\JsonContent(
                    properties: [
                        'success' => new OA\Property(property: 'success', type: 'boolean'),
                        'data' => new OA\Property(property: 'data', type: 'object'),
                        'message' => new OA\Property(property: 'message', type: 'string'),
                    ]
                )
            )
        ]
    )]
    public function apply(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string',
            'customer_id' => 'required|exists:users,id',
            'order_value' => 'required|numeric|min:0',
            'order_id' => 'nullable|exists:orders,id',
        ]);

        $coupon = Coupon::where('code', $validated['code'])->first();

        if (!$coupon || !$coupon->canBeUsedByCustomer($validated['customer_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid coupon or cannot be used'
            ], 400);
        }

        $discountAmount = $coupon->calculateDiscount($validated['order_value']);

        if ($discountAmount <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Order does not meet coupon requirements'
            ], 400);
        }

        $coupon->markAsUsed(
            $validated['customer_id'],
            $validated['order_value'],
            $discountAmount,
            $validated['order_id'] ?? null
        );

        return response()->json([
            'success' => true,
            'data' => [
                'discount_amount' => $discountAmount,
                'coupon' => $coupon,
                'final_amount' => $validated['order_value'] - $discountAmount
            ],
            'message' => 'Coupon applied successfully'
        ]);
    }

    #[OA\Get(
        path: '/api/coupons/{id}/usage-report',
        summary: 'Get coupon usage report',
        tags: ['Coupons'],
         security: [['bearerAuth' => []]], 
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Coupon ID', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'start_date', in: 'query', description: 'Start date for report', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'end_date', in: 'query', description: 'End date for report', schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Usage report generated successfully',
                content: new OA\JsonContent(
                    properties: [
                        'success' => new OA\Property(property: 'success', type: 'boolean'),
                        'data' => new OA\Property(property: 'data', type: 'object'),
                        'message' => new OA\Property(property: 'message', type: 'string'),
                    ]
                )
            )
        ]
    )]
    public function usageReport(Request $request, Coupon $coupon): JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $query = $coupon->usages()->with('customer');

        if ($request->start_date) {
            $query->where('used_at', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->where('used_at', '<=', $request->end_date);
        }

        $usages = $query->get();

        $report = [
            'coupon' => $coupon,
            'total_usage_count' => $usages->count(),
            'total_discount_given' => $usages->sum('discount_amount'),
            'total_order_value' => $usages->sum('order_value'),
            'unique_customers' => $usages->unique('customer_id')->count(),
            'usage_details' => $usages->groupBy(function ($usage) {
                return $usage->used_at->format('Y-m-d');
            })->map(function ($dayUsages) {
                return [
                    'date' => $dayUsages->first()->used_at->format('Y-m-d'),
                    'usage_count' => $dayUsages->count(),
                    'discount_given' => $dayUsages->sum('discount_amount'),
                    'order_value' => $dayUsages->sum('order_value'),
                ];
            })->values(),
        ];

        return response()->json([
            'success' => true,
            'data' => $report,
            'message' => 'Usage report generated successfully'
        ]);
    }
}