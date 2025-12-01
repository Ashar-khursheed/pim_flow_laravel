<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Models\FrontEnd\Coupon;
use App\Models\FrontEnd\Customers;
use App\Models\Product;
use App\Models\Category;
use App\Models\FinancesPayment;
use App\Models\FrontEnd\CouponCustomer;
use App\Models\FrontEnd\CouponCategory;
use App\Models\FrontEnd\CouponProduct;
use App\Models\FrontEnd\CouponUsage;
use App\Models\FrontEnd\Order;
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
            new OA\Parameter(name: 'status', in: 'query', description: 'Filter by status', schema: new OA\Schema(type: 'string', enum: ['all','pending', 'approved', 'rejected'])),
            new OA\Parameter(name: 'basis', in: 'query', description: 'Filter by basis', schema: new OA\Schema(type: 'string', enum: ['all','customer', 'category', 'product', 'promotional'])),
            new OA\Parameter(name: 'global', in: 'query', description: 'Global search across all coupon fields', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', description: 'Items per page', schema: new OA\Schema(type: 'integer', default: 10)),
            new OA\Parameter(name: 'page', in: 'query', description: 'Page number', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'sort_by', in: 'query', description: 'Column to sort by', schema: new OA\Schema(type: 'string', enum: ['id', 'code', 'title', 'discount_amount', 'created_at', 'updated_at', 'expires_at'])),
            new OA\Parameter(name: 'sort_dir', in: 'query', description: 'Sort direction', schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'], default: 'desc')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation',
                content: new OA\JsonContent(
                    properties: [
                        'success' => new OA\Property(property: 'success', type: 'boolean'),
                        'message' => new OA\Property(property: 'message', type: 'string'),
                        'total_pages' => new OA\Property(property: 'total_pages', type: 'integer'),
                        'total_records' => new OA\Property(property: 'total_records', type: 'integer'),
                        'data' => new OA\Property(property: 'data', type: 'object'),


                    ]
                )
            )
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = Coupon::with(['creator', 'approver', 'customers', 'categories', 'products']);

        //Coupon expire automatic is_active false
        $today = now()->toDateString();        
        Coupon::whereDate('expire_date', '<', $today)
        ->where('is_active', '1')           
        ->update(['is_active' => 0]);

        // Global Search Implementation
        if ($request->filled('global')) {
            $searchTerm = $request->input('global');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('code', 'LIKE', '%' . $searchTerm . '%')
                    ->orWhere('name', 'LIKE', '%' . $searchTerm . '%')
                    ->orWhere('description', 'LIKE', '%' . $searchTerm . '%')
                    ->orWhere('value', 'LIKE', '%' . $searchTerm . '%')
                    ->orWhere('min_order_value', 'LIKE', '%' . $searchTerm . '%')
                    ->orWhere('max_order_value', 'LIKE', '%' . $searchTerm . '%')
                    ->orWhere('usage_limit', 'LIKE', '%' . $searchTerm . '%')
                    ->orWhere('status', 'LIKE', '%' . $searchTerm . '%')
                    ->orWhere('basis', 'LIKE', '%' . $searchTerm . '%')
                    ->orWhere('usage_type', 'LIKE', '%' . $searchTerm . '%')
                    // Search in related models
                    ->orWhereHas('creator', function ($creatorQuery) use ($searchTerm) {
                        $creatorQuery->where('name', 'LIKE', '%' . $searchTerm . '%')
                            ->orWhere('email', 'LIKE', '%' . $searchTerm . '%');
                    })
                    ->orWhereHas('approver', function ($approverQuery) use ($searchTerm) {
                        $approverQuery->where('name', 'LIKE', '%' . $searchTerm . '%')
                            ->orWhere('email', 'LIKE', '%' . $searchTerm . '%');
                    })
                    ->orWhereHas('customers', function ($customerQuery) use ($searchTerm) {
                        $customerQuery->where('name', 'LIKE', '%' . $searchTerm . '%')
                            ->orWhere('email', 'LIKE', '%' . $searchTerm . '%')
                            ->orWhere('mobile_number', 'LIKE', '%' . $searchTerm . '%');
                    })
                    ->orWhereHas('categories', function ($categoryQuery) use ($searchTerm) {
                        $categoryQuery->where('name', 'LIKE', '%' . $searchTerm . '%')
                            ->orWhere('slug', 'LIKE', '%' . $searchTerm . '%');
                    })
                    ->orWhereHas('products', function ($productQuery) use ($searchTerm) {
                        $productQuery->where('name', 'LIKE', '%' . $searchTerm . '%')
                            ->orWhere('sku', 'LIKE', '%' . $searchTerm . '%')
                            ->orWhere('description', 'LIKE', '%' . $searchTerm . '%');
                    });
            });
        }

        // Existing Filters

        if ($request->filled('status') && $request->input('status') !== "all") {
           $query->where('status', $request->status);
        }

        if ($request->filled('basis') && $request->input('basis') !== "all") {
            $query->byBasis($request->basis);
        }

        // Sorting Implementation
        if ($request->filled('sort_by')) {
            $sortBy = $request->input('sort_by');
            $sortDir = $request->input('sort_dir', 'desc');

            // Validate sort direction
            if (!in_array($sortDir, ['asc', 'desc'])) {
                $sortDir = 'desc';
            }

            // Validate sort column
            $allowedSortColumns = ['id', 'code', 'title', 'discount_amount', 'created_at', 'updated_at', 'expires_at'];
            if (in_array($sortBy, $allowedSortColumns)) {
                $query->orderBy($sortBy, $sortDir);
            } else {
                // Default sort
                $query->orderBy('created_at', 'desc');
            }
        } else {
            // Default sort
            $query->orderBy('created_at', 'desc');
        }

        // Pagination Logic
        $perPage =  $request->input('per_page', 10);
        $page = (int) $request->input('page', 1);

        // Ensure minimum values

        $page = max(1, $page);

        $totalRecords = (clone $query)->count();
        $totalPages = (int) ceil($totalRecords / $perPage);

        // Adjust page if it exceeds total pages
        if ($page > $totalPages && $totalPages > 0) {
            $page = $totalPages;
        }

        $coupons = $query
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();
           


        return response()->json([
            'success' => true,
            'message' => __("msg_rec_list"),
            'current_page' => (int) $page,
            'per_page' => (int) $perPage,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords,
            'data' => $coupons,
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
                    'usage_type' => new OA\Property(property: 'usage_type', type: 'string', enum: ['once', 'multiple','unlimited']),
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
    // public function store(Request $request)
    // {
    //     $validated = $request->validate([
    //         'code' => 'string|max:255|unique:coupons,code',
    //         'name' => 'string|max:255',
    //         'description' => 'nullable|string',
    //         'type' => 'in:fixed,percentage',
    //         'value' => 'required|numeric|min:0',
    //         'basis' => 'required|in:customer,category,product,promotional',
    //         'min_order_value' => 'nullable|numeric|min:0',
    //         'max_order_value' => 'nullable|numeric|min:0|gte:min_order_value',
    //         'usage_type' => 'required|in:once,multiple,unlimited',
    //         'usage_limit' => 'nullable|integer|min:1',
    //         'usage_limit_per_customer' => 'nullable|integer|min:1',
    //         'start_date' => 'required|date|after_or_equal:today',
    //         'expire_date' => 'required|date|after:start_date',
    //         'is_active' => 'boolean',

    //         // 👇 Conditional validation
    //         'customer_ids' => 'required_if:basis,customer|array',
    //         'customer_ids.*' => 'required_if:basis,customer|exists:customers,id',

    //         'category_ids' => 'required_if:basis,category|array',
    //         'category_ids.*' => 'required_if:basis,category|exists:categories,id',

    //         'product_ids' => 'required_if:basis,product|array',
    //         'product_ids.*' => 'required_if:basis,product|exists:ec_products,id',
    //     ]);

    //     $validated['created_by'] = auth()->id();

    //     $coupon = Coupon::create($validated);

    //     // Attach relationships based on basis
    //     if ($validated['basis'] === 'customer' && !empty($validated['customer_ids'])) {
    //         $coupon->customers()->attach($validated['customer_ids']);
    //     }

    //     if ($validated['basis'] === 'category' && !empty($validated['category_ids'])) {
    //         $coupon->categories()->attach($validated['category_ids']);
    //     }

    //     if ($validated['basis'] === 'product' && !empty($validated['product_ids'])) {
    //         $coupon->products()->attach($validated['product_ids']);
    //     }

    //     $coupon->load(['creator', 'customers', 'categories', 'products']);

    //     return response()->json([
    //         'success' => true,
    //         'data' => $coupon,
    //         'message' => 'Coupon created successfully'
    //     ], 201);
    // }
    public function store(Request $request)
    {
     $validated = $request->validate([
        'code' => 'string|max:255|unique:coupons,code',
        'name' => 'string|max:255',
        'description' => 'nullable|string',
        'type' => 'in:fixed,percentage',      
        'value' => 'sometimes|required|numeric|min:0|lte:min_order_value',             
        'basis' => 'required|in:customer,category,product,promotional',
        'min_order_value' => 'nullable|numeric|min:0',
        'max_order_value' => 'nullable|numeric|min:0|gte:min_order_value',
        'usage_type' => 'required|in:once,multiple,unlimited',
        'usage_limit' => 'nullable|integer|min:1',
        'usage_limit_per_customer' => 'nullable|integer|min:1',
        
        // More lenient date validation
       // 'start_date' => [
       //      'required',
       //      'date',
       //      function ($attribute, $value, $fail) {
       //          $startDate = \Carbon\Carbon::parse($value)->format('Y-m-d');
       //          $today = \Carbon\Carbon::today()->format('Y-m-d');
                
       //          if ($startDate < $today) {
       //              $fail('The start date cannot be in the past.');
       //          }
       //      }
       //  ],
        'start_date' => [
                'required',
                'date',
            ],
        'expire_date' => [
            'required',
            'date',
            function ($attribute, $value, $fail) use ($request) {
                $expireDate = \Carbon\Carbon::parse($value)->startOfDay();
                $startDate = \Carbon\Carbon::parse($request->start_date)->startOfDay();
                
                if ($expireDate->lt($startDate)) {
                    $fail('The expire date must be after the start date.');
                }
            }
        ],
        
        'is_active' => 'boolean',

        // Conditional validation
        'customer_ids' => 'required_if:basis,customer|array',
        'customer_ids.*' => 'required_if:basis,customer|exists:customers,id',

        'category_ids' => 'required_if:basis,category|array',
        'category_ids.*' => 'required_if:basis,category|exists:categories,id',

        'product_ids' => 'required_if:basis,product|array',
        'product_ids.*' => 'required_if:basis,product|exists:ec_products,id',
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
        security: [['bearerAuth' => []]],
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
                    'usage_type' => new OA\Property(property: 'usage_type', type: 'string', enum: ['once', 'multiple','unlimited']),
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
            'value' => 'sometimes|required|numeric|min:0|lte:min_order_value',
            'basis' => 'sometimes|required|in:customer,category,product,promotional',
            'min_order_value' => 'nullable|numeric|min:0',
            'max_order_value' => 'nullable|numeric|min:0|gte:min_order_value',
            'usage_type' => 'sometimes|required|in:once,multiple,unlimited',
            'usage_limit' => 'nullable|integer|min:1',
            'usage_limit_per_customer' => 'nullable|integer|min:1',
            'start_date' => 'sometimes|required|date',
            'expire_date' => 'sometimes|required|date|after:start_date',
            'is_active' => 'boolean',
            'customer_ids' => 'nullable|array',
            'customer_ids.*' => 'exists:customers,id',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'exists:ec_products,id',
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
            'customer_id' => 'required|exists:customers,id',
            'order_value' => 'required|numeric|min:0',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'exists:ec_products,id',
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
            $validProducts = $coupon->products()->pluck('ec_products.id')->toArray();
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
            'customer_id' => 'required|exists:customers,id',
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



    #[OA\Post(
        path: '/api/customer/apply-coupon',
        summary: 'Apply coupon code for customer and get discount',
        tags: ['Customer Coupons'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['coupon_code', 'cart_items'],
                properties: [
                    'coupon_code' => new OA\Property(property: 'coupon_code', type: 'string', description: 'Coupon code to apply'),
                    'cart_items' => new OA\Property(
                        property: 'cart_items',
                        type: 'array',
                        description: 'Array of cart items',
                        items: new OA\Items(
                            properties: [
                                'product_id' => new OA\Property(property: 'product_id', type: 'integer'),
                                'category_id' => new OA\Property(property: 'category_id', type: 'integer'),
                                'quantity' => new OA\Property(property: 'quantity', type: 'integer'),
                                'price' => new OA\Property(property: 'price', type: 'number', format: 'float'),
                                'subtotal' => new OA\Property(property: 'subtotal', type: 'number', format: 'float'),
                            ],
                            type: 'object'
                        )
                    ),
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
                        'data' => new OA\Property(
                            property: 'data',
                            properties: [
                                'coupon_valid' => new OA\Property(property: 'coupon_valid', type: 'boolean'),
                                'coupon_code' => new OA\Property(property: 'coupon_code', type: 'string'),
                                'coupon_name' => new OA\Property(property: 'coupon_name', type: 'string'),
                                'discount_type' => new OA\Property(property: 'discount_type', type: 'string'),
                                'discount_value' => new OA\Property(property: 'discount_value', type: 'number'),
                                'original_total' => new OA\Property(property: 'original_total', type: 'number'),
                                'discount_amount' => new OA\Property(property: 'discount_amount', type: 'number'),
                                'final_total' => new OA\Property(property: 'final_total', type: 'number'),
                                'savings' => new OA\Property(property: 'savings', type: 'number'),
                                'applicable_items' => new OA\Property(
                                    property: 'applicable_items',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            'product_id' => new OA\Property(property: 'product_id', type: 'integer'),
                                            'quantity' => new OA\Property(property: 'quantity', type: 'integer'),
                                            'price' => new OA\Property(property: 'price', type: 'number', format: 'float'),
                                            'subtotal' => new OA\Property(property: 'subtotal', type: 'number', format: 'float'),
                                        ],
                                        type: 'object'
                                    )
                                ),
                            ],
                            type: 'object'
                        ),
                        'message' => new OA\Property(property: 'message', type: 'string'),
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Invalid coupon or cannot be applied',
                content: new OA\JsonContent(
                    properties: [
                        'success' => new OA\Property(property: 'success', type: 'boolean'),
                        'data' => new OA\Property(
                            property: 'data',
                            properties: [
                                'coupon_valid' => new OA\Property(property: 'coupon_valid', type: 'boolean'),
                                'reason' => new OA\Property(property: 'reason', type: 'string'),
                            ],
                            type: 'object'
                        ),
                        'message' => new OA\Property(property: 'message', type: 'string'),
                    ]
                )
            )
        ]
    )]
    public function applyCustomerCoupon(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'coupon_code' => 'required|string',
            'cart_items' => 'required|array|min:1',
            'cart_items.*.product_id' => 'nullable|integer|exists:ec_products,id',
            'cart_items.*.category_id' => 'nullable|integer|exists:categories,id',
            'cart_items.*.quantity' => 'required|integer|min:1',
            'cart_items.*.price' => 'required|numeric|min:0',
            'cart_items.*.subtotal' => 'required|numeric|min:0',
        ]);

        // Get authenticated customer
        $customerId = auth()->id(); // Assuming customer is authenticated

        // Find coupon by code
        $coupon = Coupon::where('code', $validated['coupon_code'])
            ->where('is_active', true)
            ->first();

        if (!$coupon) {
            return response()->json([
                'success' => false,
                'data' => [
                    'coupon_valid' => false,
                    'reason' => 'Coupon code not found or inactive'
                ],
                'message' => 'Invalid coupon code'
            ], 400);
        }

        // Calculate cart totals
        $cartItems = collect($validated['cart_items']);
        $originalTotal = $cartItems->sum('subtotal');
        $productIds = $cartItems->pluck('product_id')->unique()->toArray();
        $categoryIds = $cartItems->pluck('category_id')->unique()->toArray();

        // Validate coupon eligibility
        $validationResult = $this->validateCouponForCustomer($coupon, $customerId, $originalTotal, $categoryIds, $productIds);

        if (!$validationResult['valid']) {
            return response()->json([
                'success' => false,
                'data' => [
                    'coupon_valid' => false,
                    'reason' => $validationResult['reason']
                ],
                'message' => 'Coupon cannot be applied'
            ], 400);
        }

        // Determine applicable items based on coupon basis
        $applicableItems = $this->getApplicableItems($coupon, $cartItems);

        if ($applicableItems->isEmpty()) {
            return response()->json([
                'success' => false,
                'data' => [
                    'coupon_valid' => false,
                    'reason' => 'No items in cart are eligible for this coupon'
                ],
                'message' => 'Coupon not applicable to cart items'
            ], 400);
        }

        // Calculate discount on applicable items
        $applicableTotal = $applicableItems->sum('subtotal');
        $discountAmount = $this->calculateCouponDiscount($coupon, $applicableTotal);

        // Ensure discount doesn't exceed applicable total
        $discountAmount = min($discountAmount, $applicableTotal);
        $finalTotal = max(0, $originalTotal - $discountAmount);

        return response()->json([
            'success' => true,
            'data' => [
                'coupon_valid' => true,
                'coupon_code' => $coupon->code,
                'coupon_name' => $coupon->name,
                'coupon_description' => $coupon->description,
                'discount_type' => $coupon->type,
                'discount_value' => $coupon->value,
                'original_total' => round($originalTotal, 2),
                'discount_amount' => round($discountAmount, 2),
                'final_total' => round($finalTotal, 2),
                'savings' => round($discountAmount, 2),
                'applicable_items' => $applicableItems->map(function ($item) {
                    return [
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'subtotal' => $item['subtotal']
                    ];
                })->toArray(),
                'coupon_details' => [
                    'basis' => $coupon->basis,
                    'usage_type' => $coupon->usage_type,
                    'min_order_value' => $coupon->min_order_value,
                    'max_order_value' => $coupon->max_order_value,
                    'expire_date' => $coupon->expire_date->format('Y-m-d H:i:s'),
                ]
            ],
            'message' => 'Coupon applied successfully! You saved $' . number_format($discountAmount, 2)
        ]);
    }

    /**
     * Validate if coupon can be used by customer
     */
    private function validateCouponForCustomer($coupon, $customerId, $orderValue, $categoryIds, $productIds): array
    {
        // Check if coupon is valid (active, approved, not expired)
        if (!$coupon->isValid()) {
            return [
                'valid' => false,
                'reason' => 'Coupon is inactive, expired, or not approved'
            ];
        }

        // Check if coupon has reached usage limit
        if ($coupon->hasReachedUsageLimit()) {
            return [
                'valid' => false,
                'reason' => 'Coupon usage limit has been reached'
            ];
        }

        // Check usage limit per customer
        if ($coupon->usage_limit_per_customer) {
            $customerUsageCount = CouponUsage::where('coupon_id', $coupon->id)
                ->where('customer_id', $customerId)
                ->count();

            if ($customerUsageCount >= $coupon->usage_limit_per_customer) {
                return [
                    'valid' => false,
                    'reason' => 'You have reached the usage limit for this coupon'
                ];
            }
        }

        // Check if it's a "once" usage coupon and customer has already used it
        if ($coupon->usage_type === 'once') {
            $hasUsed = CouponUsage::where('coupon_id', $coupon->id)
                ->where('customer_id', $customerId)
                ->exists();

            if ($hasUsed) {
                return [
                    'valid' => false,
                    'reason' => 'You have already used this coupon'
                ];
            }
        }

        // Check minimum order value
        if ($coupon->min_order_value && $orderValue < $coupon->min_order_value) {
            return [
                'valid' => false,
                'reason' => 'Minimum order value of ' . number_format($coupon->min_order_value, 2) . ' required'
            ];
        }

        // Check maximum order value
        if ($coupon->max_order_value && $orderValue > $coupon->max_order_value) {
            return [
                'valid' => false,
                'reason' => 'Order value exceeds maximum limit of ' . number_format($coupon->max_order_value, 2)
            ];
        }
        if ($orderValue < $coupon->value ) {     
            return [
                'valid' => false,
                'reason' =>  "The order amount (" . number_format($orderValue, 2) . ") is less than the coupon value (" . number_format($coupon->value, 2) . ").",          
                ];                             
        }
        // Check customer-specific coupon
        if ($coupon->basis === 'customer') {
            $isValidCustomer = $coupon->customers()->where('customer_id', $customerId)->exists();
            if (!$isValidCustomer) {
                return [
                    'valid' => false,
                    'reason' => 'This coupon is not available for your account'
                ];
            }
        }

        // Check category-specific coupon
        if ($coupon->basis === 'category') {
            $validCategories = $coupon->categories()->pluck('categories.id')->toArray();
            $hasValidCategory = !empty(array_intersect($categoryIds, $validCategories));

            if (!$hasValidCategory) {
                return [
                    'valid' => false,
                    'reason' => 'This coupon is not applicable to items in your cart'
                ];
            }
        }

        // Check product-specific coupon
        if ($coupon->basis === 'product') {
            $validProducts = $coupon->products()->pluck('products.id')->toArray();
            $hasValidProduct = !empty(array_intersect($productIds, $validProducts));

            if (!$hasValidProduct) {
                return [
                    'valid' => false,
                    'reason' => 'This coupon is not applicable to items in your cart'
                ];
            }
        }

        return ['valid' => true];
    }

    /**
     * Get items that are applicable for the coupon
     */
    private function getApplicableItems($coupon, $cartItems)
    {
        switch ($coupon->basis) {
            case 'promotional':
                // All items are applicable
                return $cartItems;

            case 'customer':
                // All items are applicable for customer-specific coupons
                return $cartItems;

            case 'category':
                $validCategories = $coupon->categories()->pluck('categories.id')->toArray();
                return $cartItems->filter(function ($item) use ($validCategories) {
                    return in_array($item['category_id'], $validCategories);
                });

            case 'product':
                $validProducts = $coupon->products()->pluck('products.id')->toArray();
                return $cartItems->filter(function ($item) use ($validProducts) {
                    return in_array($item['product_id'], $validProducts);
                });

            default:
                return collect([]);
        }
    }

    /**
     * Calculate discount amount
     */
    private function calculateCouponDiscount($coupon, $applicableTotal): float
    {
        if ($coupon->type === 'fixed') {
            return min($coupon->value, $applicableTotal);
        } else { // percentage
            return ($applicableTotal * $coupon->value) / 100;
        }
    }

    /**
     * @OA\Get(
     *     path="/api/customer/check-coupon",
     *     summary="Check if a coupon code is valid for the authenticated customer",
     *     description="Validates a coupon code for existence, active status, expiry, and usage for the logged-in customer.",
     *     tags={"Customer Coupons"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="coupon_code",
     *         in="query",
     *         required=true,
     *         description="The coupon code to validate",
     *         @OA\Schema(type="string", example="SUMMER2025")
     *     ),
     *  @OA\Parameter(
     *         name="orderValue",
     *         in="query",
     *         required=true,
     *         description="The orderValue to validate",
     *         @OA\Schema(type="number", example="500")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Coupon is valid",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Coupon is valid for this customer"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="coupon_valid", type="boolean", example=true),
     *                 @OA\Property(property="coupon_code", type="string", example="SUMMER2025"),
     *                 @OA\Property(property="coupon_name", type="string", example="Summer Sale"),
     *                 @OA\Property(property="coupon_description", type="string", example="Get 20% off"),
     *                 @OA\Property(property="discount_type", type="string", example="percentage"),
     *                 @OA\Property(property="discount_value", type="number", example=20),
     *                 @OA\Property(property="basis", type="string", example="customer"),
     *                 @OA\Property(property="usage_type", type="string", example="once"),
     *                 @OA\Property(property="expire_date", type="string", format="date-time", example="2025-12-31 23:59:59")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid or expired coupon",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invalid coupon code"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="coupon_valid", type="boolean", example=false),
     *                 @OA\Property(property="reason", type="string", example="Coupon expired")
     *             )
     *         )
     *     )
     * )
     */
    // GET /api/customer/check-coupon
// GET /api/customer/check-coupon
    public function checkCustomerCoupon(Request $request)
    {
        $customerId = auth()->id(); // Logged-in customer ID

        if (!$customerId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }
 
        //Coupon expire automatic is_active false
        $today = now()->toDateString();        
        Coupon::whereDate('expire_date', '<', $today)
        ->where('is_active', '1')           
        ->update(['is_active' => 0]);

        $couponCode = $request->query('coupon_code');
        $orderValue = $request->query('orderValue');
        $coupon = Coupon::where('code', $couponCode)->first();
        if (!$coupon) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired coupon',
            ], 400);
        }
        $usage_type = $coupon->usage_type;
        $usage_limit = $coupon->usage_limit;
        $usage_limit_per_customer = $coupon->usage_limit_per_customer;
        $basis = $coupon->basis;

        
        $current_total_usage = Order::where('coupon_id',$coupon->id)->where('customer_id',$customerId)->get()->count();
 
        // $current_total_usage = $coupon->usage_count;  
 
        $current_customer_usage = $coupon->usages()->where('customer_id', $customerId)->count(); 

      
        $current_customer_count = CouponCustomer::where('coupon_id', $coupon->id)
            ->where('customer_id', $customerId)
            ->count();

        $category_ids = CouponCategory::where('coupon_id', $coupon->id)
            ->pluck('category_id')
            ->toArray();
        $validCategories = $coupon->categories()->pluck('categories.id')->toArray();

        $productIds = CouponProduct::where('coupon_id', $coupon->id)
            ->pluck('product_id')
            ->toArray();
       
        $is_valid = true;
        $error_message = '';        
        if ($usage_type == 'once') {             
            if ($current_total_usage >= 1) {                
                $is_valid = false;
                $error_message = 'This coupon can only be used once and has already been used.';
            }
        } elseif ($usage_type == 'multiple') {
                     
            if ($usage_limit > 0 && $current_total_usage >= $usage_limit) {
                $is_valid = false;
                $error_message = "This coupon has reached its usage limit of {$usage_limit}.";
            }
        } elseif ($usage_type == 'unlimited') {
            if ($is_valid && $usage_limit_per_customer > 0) {
                if ($current_customer_usage >= $usage_limit_per_customer) {
                    $is_valid = false;
                    $error_message = "You have reached the usage limit of {$usage_limit_per_customer} for this coupon.";
                }
            }
        }
         
         
        if ($is_valid) {
            switch ($basis) {
                case 'customer':
                      $isAssigned = $coupon->customers()
                    ->where('customer_id', $customerId)
                    ->exists();    
                    if (!$isAssigned) {
                        $is_valid = false;
                        $error_message = "This coupon is not valid for your account.";
                    }             
                     
                    break;

                case 'category':
                     
                    $validCategories = $coupon->categories()->pluck('categories.id')->toArray();                 
                    if (empty(array_intersect($category_ids, $validCategories))) {
 
                        $is_valid = false;
                        $error_message = "This category has reached its usage limit.";
                    }
                    break;

                case 'product':
                    $validProducts = $coupon->products()->pluck('ec_products.id')->toArray();
                    if (empty(array_intersect($productIds, $validProducts))) {

                        $is_valid = false;
                        $error_message = "This product has reached its usage limit.";
                    }
                    break;
            }
        }
 
         if ($orderValue < $coupon->min_order_value) {
            
            $is_valid = false;          
            $error_message = 'Minimum order value of ' . number_format($coupon->min_order_value, 2) . ' required';
        }
        if ($orderValue < $coupon->value ) {            
            $is_valid = false;          
            $error_message = "The order amount (" . number_format($orderValue, 2) . ") is less than the coupon value (" . number_format($coupon->value, 2) . ").";             
        }

        if ($coupon->max_order_value && $orderValue > $coupon->max_order_value) {
            
            $is_valid = false;
            $error_message = 'Order value exceeds maximum limit of ' . number_format($coupon->max_order_value, 2);
        }

        if ($coupon->type === 'percentage') {    
            $percentage =  ($orderValue * $coupon->value) / 100;
             if ($coupon->max_order_value && $percentage > $coupon->max_order_value) {
                $is_valid = false;
                $error_message = 'Order value exceeds maximum limit of ' . number_format($coupon->max_order_value, 2);
             }
            if ($percentage < $coupon->value ) {     
            return [
                $is_valid = false,
                $error_message =  "The order amount (" . number_format($orderValue, 2) . ") is less than the coupon value (" . number_format($coupon->value, 2) . ").",          
                ];                             
            }
            if ($percentage < $coupon->min_order_value) {
                $is_valid = false;          
                $error_message = 'Minimum order value of ' . number_format($coupon->min_order_value, 2) . ' required';
            }
             
        }
        
        if (!$coupon->isValid()) {
            $is_valid = false;
            $error_message = 'This coupon is not valid OR expired coupon '.$coupon->expire_date->toDateString();
        }
 
        if ($coupon->isExpired()) {
            $is_valid = false;
            $error_message = 'This coupon has expired.';
        }
        if($is_valid){
        return response()->json([
            'success' => $is_valid,
            'message' => $error_message,
            'data' => [
                'coupon_id' => $coupon->id,
                'coupon_code' => $coupon->code,
                'coupon_name' => $coupon->name,
                'coupon_description' => $coupon->description,
                'discount_type' => $coupon->type,
                'discount_value' => $coupon->value,
                'expire_date' => $coupon->expire_date,
            ],
        ]);
    }else{
          return response()->json([
            'success' => $is_valid,
            'message' => $error_message,
            'data' => [],
        ]);

    }

        
    }
    // public function checkCustomerCoupon_old(Request $request)
    // {
    //     $customerId = auth()->id(); // Logged-in customer ID

    //     if (!$customerId) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Unauthorized',
    //         ], 401);
    //     }

    //     $couponCode = $request->query('coupon_code');

    //     $coupon = Coupon::where('code', $couponCode)
    //         ->where('is_active', true)        // active status
    //         ->where('status', 'approved')    // approved in DB
    //         ->where(function ($q) {
    //             $q->whereNull('start_date')
    //                 ->orWhere('start_date', '<=', now());
    //         })
    //         ->where(function ($q) {
    //             $q->whereNull('expire_date')
    //                 ->orWhere('expire_date', '>=', now());
    //         })
    //         ->first();
 
    //     if (!$coupon) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Invalid or expired coupon',
    //         ], 400);
    //     }

    //     // Check if coupon is customer-specific
    //     if ($coupon->basis === 'customer') {
    //         $isAssigned = $coupon->customers()
    //             ->where('customer_id', $customerId)
    //             ->exists();

    //         if (!$isAssigned) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'This coupon is not valid for your account',
    //             ], 403);
    //         }
    //     }

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Coupon is valid',
    //         'data' => [
    //             'coupon_id' => $coupon->id,
    //             'coupon_code' => $coupon->code,
    //             'coupon_name' => $coupon->name,
    //             'coupon_description' => $coupon->description,
    //             'discount_type' => $coupon->type,
    //             'discount_value' => $coupon->value,
    //             'expire_date' => $coupon->expire_date,
    //         ],
    //     ]);
    // }







}
