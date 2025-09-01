<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\FrontEnd\AlternateProduct;
use Illuminate\Support\Facades\DB;
use App\Models\Product;
use App\Models\Category;
class AIAlternateProductController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/ai-products-alternates",
     *     summary="Get a list of Products AI alternates",
     *     description="Report of products display with id, sku, name, and branch name. Can search across product name, SKU, brand, status, and categories.",
     *     tags={"Products AI alternates"},	 * 	   
     *       @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/AlternateProduct")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request"
     *     ),	 *      
     *      security={{"bearerAuth":{}}}
     * )
     */
    public function index(Request $request)
    {

        $userId = Auth::id();
        $isUserLoggedIn = $userId !== null;

        Log::info('Fetching alternate products for:', ['product_id' => "", 'user_id' => $userId]);


        $response = [];
        $baseValue = 150;
        $monday = strtotime("last monday midnight");
        $now = strtotime("now");
        $sunday = strtotime("next sunday", $monday);
        $diff = date_diff(date_create(date('Y-m-d', $monday)), date_create(date('Y-m-d', $now)));
        $baseValue *= ($diff->days + 1);
        //$monday  = strtotime($monday.'00:00:00');
        //$sunday  = strtotime($sunday.'23:59:59');
        // FIND WEEKLY Total Suggestions             
        $alternateProduct = Product::whereHas('alternateProducts', function ($q) use ($monday, $sunday) {
            $q->whereBetween(DB::raw('DATE(created_at)'), [
                date('Y-m-d', $monday),
                date('Y-m-d', $sunday)
            ]);
        });
        $response['weekly_alternative']['total_suggestions'] = $alternateProduct->count();
        $response['weekly_alternative']['total_suggestions_percent'] = round(($alternateProduct->count() / $baseValue) * 100, 2);

        // FIND WEEKLY Total pending review            
        $alternateProduct = Product::whereHas('alternateProducts', function ($q) use ($monday, $sunday) {
            $q->where('status', 'like', 'pending')
                ->whereBetween(DB::raw('DATE(created_at)'), [
                    date('Y-m-d', $monday),
                    date('Y-m-d', $sunday)
                ]);
        });

        $response['weekly_alternative']['pending_review'] = $alternateProduct->count();
        $response['weekly_alternative']['pending_percent'] = round(($alternateProduct->count() / $baseValue) * 100, 2);

        // FIND WEEKLY Total Approved          
        $alternateProduct = Product::whereHas('alternateProducts', function ($q) use ($monday, $sunday) {
            $q->where('status', 'like', 'approved')
                ->whereBetween(DB::raw('DATE(created_at)'), [
                    date('Y-m-d', $monday),
                    date('Y-m-d', $sunday)
                ]);
        });
        $response['weekly_alternative']['approved'] = $alternateProduct->count();
        $response['weekly_alternative']['approved_percent'] = round(($alternateProduct->count() / $baseValue) * 100, 2);

        // FIND WEEKLY Total Rejected               
        $alternateProduct = Product::whereHas('alternateProducts', function ($q) use ($monday, $sunday) {
            $q->where('status', 'like', 'rejected')
                ->whereBetween(DB::raw('DATE(created_at)'), [
                    date('Y-m-d', $monday),
                    date('Y-m-d', $sunday)
                ]);
        });
        $response['weekly_alternative']['rejected '] = $alternateProduct->count();
        $response['weekly_alternative']['rejected_percent'] = round(($alternateProduct->count() / $baseValue) * 100, 2);



        // FIND WEEKLY Total Accuracy             
        $alternateProduct = Product::whereHas('alternateProducts', function ($q) use ($monday, $sunday) {
            $q->where('status', 'like', 'accuracy')
                ->whereBetween(DB::raw('DATE(created_at)'), [
                    date('Y-m-d', $monday),
                    date('Y-m-d', $sunday)
                ]);
        });
        $response['weekly_alternative']['accuracy '] = $alternateProduct->count();
        $response['weekly_alternative']['accuracy_percent'] = round(($alternateProduct->count() / $baseValue) * 100, 2);
        if (empty($response)) {
            return response()->json([
                'success' => true,
                'message' => 'No alternate products found for this product',
                'data' => [],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $response,
            'message' => 'Alternate products retrieved successfully',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/ai-products-alternates",
     *     summary="Get a list of Products AI alternates",
     *     description="Report of products display with id, sku, name, and branch name. Can search across product name, SKU, brand, status, and categories.",
     *     tags={"Products AI alternates"},	 *      
     * 	   @OA\Property(property="search", type="string", example='', description="Search by Sku"),
     *  @OA\Property(property="range_from", type="integer", example=1, description="Starting product index (must be >= 1)"),
     *     @OA\Property(property="range_to", type="integer", example=500, description="Ending product index (max range allowed: 500 products)"),
     *     @OA\Property(property="rejection", type="string", example='', description="Search by rejection"),
     *  @OA\Property(property="reviewers", type="string", example='', description="Search by reviewers"),
     *  @OA\Property(property="category", type="string", example='', description="Filter by Category id"),
     *       @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/AlternateProduct")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request"
     *     ),	 *      
     *      security={{"bearerAuth":{}}}
     * )
     */

    public function getAiAlternateProducts(Request $request)
    {
        $request->validate([
            'range_from' => 'integer|min:1',
            'range_to' => 'integer|gte:range_from|max:' . ($request->range_from + 500),

        ]);
        $perPage = $request->input('per_page', 50);
        $sortBy = $request->input('sort_by', 'id');
        $sortDirection = $request->input('sort_direction', 'desc');

        // Validate sort columns to prevent SQL injection
        $allowedSortColumns = ['id', 'name', 'sku', 'brand_id', 'status', 'gen_type', 'approved'];
        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'id'; // Default to id if invalid column
        }

        // Validate sort direction
        if (!in_array(strtolower($sortDirection), ['asc', 'desc'])) {
            $sortDirection = 'desc'; // Default to descending if invalid direction
        }

        $query = DB::table('ec_products as ec')
            ->join(DB::raw("(
        SELECT m1.id AS alt_id, m1.product_id, m1.status AS alt_status,m1.product_alternate_id,m1.priority,m1.similarity,m1.order,m1.created_at as alt_created,m1.updated_at as alt_updated_by, m1.created_by as alt_created_by,m1.rejected_by as alt_rejected_by,m1.reason
        FROM `alternate_products` m1
        LEFT JOIN alternate_products m2
            ON (m1.product_id = m2.product_id AND m1.id < m2.id)
        WHERE m2.id IS NULL
        AND m1.status IN ('pending', 'approved')
    ) as fu"), 'ec.id', '=', DB::raw('fu.product_id'))
            ->select([
                'ec.id AS p_id',
                'ec.name AS product_name',
                'ec.sku AS product_sku',
                'ec.status AS product_status',
                'ec.images AS product_images',
                'fu.alt_id',
                'fu.alt_status',
                'fu.product_alternate_id',
                'fu.priority',
                'fu.similarity',
                'fu.order',
                'fu.alt_created',
                'fu.alt_updated_by',
                'fu.alt_created_by',
                'fu.alt_rejected_by',
                'fu.reason'
            ])
            ->orderBy('fu.alt_id', 'desc');
        // Apply status filter
        if (!empty($request->input('rejection'))) {
            $query->where('reason', $request->input('rejection'));
        }
        if (!empty($request->input('reviewers'))) {
            $query->where('status', $request->input('reviewers'));
        }


        if ($request->input('category')) {
            $category = Category::find($request->input('category'));
            $leafCategories = Category::getLeafCategories($category);
            $leafCategoryIds = $leafCategories->pluck('id')->toArray();
            $query->whereHas('categories', function ($q) use ($leafCategoryIds) {
                $q->whereIn('category_id', $leafCategoryIds);
            });
        }

        if ($request->input('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhereHas('brand', function ($brandQuery) use ($search) {
                        $brandQuery->where('name', 'like', "%{$search}%");
                    })

                    ->orWhereHas('categories', function ($categoryQuery) use ($search) {
                        $categoryQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $products = $query->whereNotNull('status')
            ->offset($request->range_from - 1)
            ->limit($request->range_to - $request->range_from + 1)
            ->paginate($perPage);
        /* Formatting response */
        $formattedProducts = $products->map(function ($product) {

            return [
                'id' => $product->p_id,
                'product_name' => $product->product_name,
                'product_sku' => $product->product_sku,
                'product_status' => $product->product_status,
                'product_images' => $product->product_images,
                'alt_id' => $product->alt_id,
                'alt_status' => $product->alt_status,
                'product_alternate_id' => $product->product_alternate_id,
                'priority' => $product->priority,
                'similarity' => $product->similarity,
                'order' => $product->order,
                'alt_created' => $product->alt_created,
                'alt_updated_by' => $product->alt_updated_by,
                'alt_created_by' => $product->alt_created_by,
                'alt_rejected_by' => $product->alt_rejected_by,
                'reason' => $product->reason

            ];
        });


        return response()->json([
            'success' => true,
            'message' => 'Products retrieved successfully',
            'data' => $formattedProducts,
            'pagination' => [
                'total' => $products->total(),
                'per_page' => $products->perPage(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'next_page_url' => $products->nextPageUrl(),
                'prev_page_url' => $products->previousPageUrl(),
            ],
        ]);
    }
}
