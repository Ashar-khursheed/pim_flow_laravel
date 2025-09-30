<?php
// app/Http/Controllers/Api/SearchLogController.php
Namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Models\FrontEnd\SearchLog;
use Illuminate\Http\Request;
class SearchLogController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/frontend/search-logs",
     *     summary="Store a search or click log",
     *     tags={"SearchLogs"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="search_term", type="string", example="laptop"),
     *             @OA\Property(property="product_id", type="integer", example=123)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Log stored successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Log stored successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="search_term", type="string", example="laptop"),
     *                 @OA\Property(property="product_id", type="integer", example=null),
     *                 @OA\Property(property="ip_address", type="string", example="192.168.1.10"),
     *                 @OA\Property(property="user_agent", type="string", example="Mozilla/5.0"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        $log = SearchLog::create([
            'customer_id' => auth('customers')->id() ?? null,
            'search_term' => $request->input('search_term'),
            'product_id'  => $request->input('product_id'),
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->header('User-Agent'),
        ]);

        return response()->json([
            'message' => 'Log stored successfully',
            'data' => $log,
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/frontend/search-logs",
     *     summary="Get list of search logs with filtering, pagination and sorting",
     *     tags={"SearchLogs"},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by term",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Field to sort by (id, created_at, search_term)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Sort order (asc or desc)",
     *         required=false,
     *         @OA\Schema(type="string", default="desc")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of results per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=20)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of logs",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="search_term", type="string", example="laptop"),
     *                     @OA\Property(property="product_id", type="integer", example=123),
     *                     @OA\Property(property="ip_address", type="string", example="192.168.1.10"),
     *                     @OA\Property(property="user_agent", type="string", example="Mozilla/5.0"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )
     *             ),
     *             @OA\Property(property="links", type="object"),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = SearchLog::query();

        // 🔎 Filtering
        if ($request->has('search')) {
            $query->where('search_term', 'like', '%' . $request->search . '%');
        }

        // 📑 Sorting
        $sortBy = $request->get('sort_by', 'id');
        $sortOrder = $request->get('sort_order', 'desc');

        $query->orderBy($sortBy, $sortOrder);

        // 📄 Pagination
        $perPage = $request->get('per_page', 20);

        $logs = $query->paginate($perPage);

        return response()->json($logs);
    }
}