<?php
namespace App\Http\Controllers;
use App\Models\FrontEnd\GetInTouch;
use Illuminate\Http\Request;

class GetInTouchController extends Controller
{
     /**
     * @OA\Get(
     *     path="/api/get-in-touch",
     *     summary="List all get-in-touch submissions with search, sorting, and pagination",
     *     tags={"GetInTouch"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search name, email, phone, topic, message, order_number",
     *         required=false,
     *         @OA\Schema(type="string", example="john")
     *     ),
     *  @OA\Parameter(name="from_date", in="query", @OA\Schema(type="string", format="date",example="2025-01-01")),
     *     @OA\Parameter(name="to_date", in="query", @OA\Schema(type="string", format="date",example="2025-12-31")),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Column to sort by",
     *         required=false,
     *         @OA\Schema(type="string", example="created_at")
     *     ),
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Sort direction",
     *         required=false,
     *         @OA\Schema(type="string", enum={"asc","desc"}, example="desc")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="How many items per page",
     *         required=false,
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="List retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="List retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="per_page", type="integer", example=10),
     *                 @OA\Property(property="total", type="integer", example=120),
     *                 @OA\Property(
     *                     property="records",
     *                     type="array",
     *                     @OA\Items(
     *                          @OA\Property(property="id", type="integer", example=1),
     *                          @OA\Property(property="name", type="string", example="John Doe"),
     *                          @OA\Property(property="email", type="string", example="john@example.com"),
     *                          @OA\Property(property="phone", type="string", example="971500000000"),
     *                          @OA\Property(property="topic", type="string", example="Regarding my order"),
     *                          @OA\Property(property="order_number", type="string", example="1001"),
     *                          @OA\Property(property="message", type="string", example="Description goes here"),
     *                          @OA\Property(property="image_url", type="string", example="https://bucket.s3.amazonaws.com/get_in_touch/abc.webp"),
     *                          @OA\Property(property="created_at", type="string", example="2025-01-20 10:30:00")
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {

         if ($request->filled('from_date') && $request->filled('to_date')) {
            $from = $request->from_date . ' 00:00:00';
            $to = $request->to_date . ' 23:59:59';

            $records = GetInTouch::whereBetween('created_at', [$from, $to])->pluck('id');
            return response()->json([
                'success' => true,
                'message' => __('msg_rec_list'),
                'data' => $records,
            ]);
        }



        $query = GetInTouch::query();

        // Search
        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%")
                    ->orWhere('phone', 'like', "%$search%")
                    ->orWhere('topic', 'like', "%$search%")
                    ->orWhere('message', 'like', "%$search%")
                    ->orWhere('order_number', 'like', "%$search%");
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 10);

        $records = $query->paginate($perPage);

        return response()->json([
            'message' => 'List retrieved successfully',
            'data' => [
                'current_page' => $records->currentPage(),
                'per_page'     => $records->perPage(),
                'total'        => $records->total(),
                'records'      => $records->items(),
            ]
        ]);
    }


        /**
         * @OA\Get(
         *     path="/api/get-in-touch/{id}",
         *     summary="Get a single get-in-touch entry",
         *     tags={"GetInTouch"},
         *     security={{"bearerAuth":{}}},
         *     @OA\Parameter(
         *         name="id",
         *         in="path",
         *         required=true,
         *         description="ID of the form submission",
         *         @OA\Schema(type="integer")
         *     ),
         *     @OA\Response(
         *         response=200,
         *         description="Record retrieved successfully",
         *         @OA\JsonContent(
         *             type="object",
         *             @OA\Property(property="message", type="string", example="Record retrieved successfully"),
         *             @OA\Property(
         *                 property="data",
         *                 type="object",
         *                 @OA\Property(property="id", type="integer", example=1),
         *                 @OA\Property(property="name", type="string", example="John Doe"),
         *                 @OA\Property(property="email", type="string", example="john@example.com"),
         *                 @OA\Property(property="phone", type="string", example="971500000000"),
         *                 @OA\Property(property="topic", type="string", example="Regarding my order"),
         *                 @OA\Property(property="order_number", type="string", example="1001"),
         *                 @OA\Property(property="message", type="string", example="Description goes here"),
         *                 @OA\Property(property="image_url", type="string", example="https://bucket.s3.amazonaws.com/get_in_touch/abc.webp"),
         *                 @OA\Property(property="created_at", type="string", example="2025-01-20 10:30:00")
         *             )
         *         )
         *     ),
         *     @OA\Response(
         *         response=404,
         *         description="Record not found"
         *     )
         * )
         */
        public function show($id)
        {
            $record = GetInTouch::find($id);

            if (!$record) {
                return response()->json([
                    'message' => 'Record not found',
                ], 404);
            }

            return response()->json([
                'message' => 'Record retrieved successfully',
                'data'    => $record,
            ], 200);
        }





}