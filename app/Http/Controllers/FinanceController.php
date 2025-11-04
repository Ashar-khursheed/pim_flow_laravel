<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Finance;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use OpenApi\Annotations as OA;
class FinanceController extends Controller
{


    /**
     * @OA\Get(
     *     path="/api/finances",
     *     summary="Get all finance records",
     *     tags={"Finance"},      
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         description="Filter by status",
     *         @OA\Schema(type="string", enum={"Pending","Completed","Failed","Cancelled","Refunded","all"}, example="all")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         required=false,
     *         description="Search by order id, transaction_id",
     *         @OA\Schema(type="string", example="")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number for pagination",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Number of records per page",
     *         @OA\Schema(type="integer", minimum=1, example=10)
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         required=false,
     *         description="Column to sort by (id, order_id, status)",
     *         @OA\Schema(type="string", enum={"id", "order_id", "status"}, example="id")
     *     ),
     *     @OA\Parameter(
     *         name="sort_direction",
     *         in="query",
     *         required=false,
     *         description="Sort direction (asc or desc)",
     *         @OA\Schema(type="string", enum={"asc", "desc"}, example="desc")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product accessories retrieved successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function index(Request $request)
    {
        $query = Finance::with(['createdBy', 'updatedBy']);



        if ($request->filled('status') && $request->input('status') !== "all") {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');

            $query->where(function ($q) use ($search) {

                $q->where('business_name', 'like', "%{$search}%")
                    ->orWhere('amount', 'like', "%{$search}%")
                    ->orWhere('payment_due', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('duns_number', 'like', "%{$search}%")
                    ->orWhere('accounts_payable_phone', 'like', "%{$search}%")
                    ->orWhere('accounts_payable_email', 'like', "%{$search}%")
                    ->orWhere('business_address', 'like', "%{$search}%");
            });
        }


        $searchableColumns = ['id', 'business_name', 'city', 'status', 'duns_number', 'accounts_payable_phone', 'accounts_payable_email', 'business_address'];
        $sortableColumns = array_merge($searchableColumns, ['created_at', 'updated_at', 'payment_due', 'amount']);


        $sortBy = in_array($request->input('sort_by'), $sortableColumns) ? $request->input('sort_by') : 'created_at';
        $sortDir = strtolower($request->input('sort_direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        // Pagination parameters
        $perPage = min($request->get('per_page', 15), 100); // Limit max per_page to 100
        $page = max($request->get('page', 1), 1); // Ensure page is at least 1

        // Get total count BEFORE applying pagination
        $totalRecords = (clone $query)->count();
        $totalPages = $perPage > 0 ? (int) ceil($totalRecords / $perPage) : 1;

        // Adjust page if it exceeds total pages
        if ($page > $totalPages && $totalPages > 0) {
            $page = $totalPages; // Go to last page instead of first page
        }

        // Apply sorting and pagination
        $financeManagement = $query->orderBy($sortBy, $sortDir)
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        // Format the results
        $formattedFinance = $financeManagement->map(function ($finance) {
            return [
                'id' => $finance->id,
                'payment_selection' => $finance->payment_selection,
                'payment_options' => $finance->payment_options,
                'term_selection' => $finance->term_selection,
                'type_of_business' => $finance->type_of_business,
                'amount' => number_format($finance->amount, 2), // Format amount
                'status' => $finance->status,
                'business_name' => $finance->business_name,
                'business_address' => $finance->business_address,
                'country' => $finance->country,
                'address' => $finance->address,
                'state' => $finance->state,
                'zip' => $finance->zip,
                'annual_revenue' => $finance->annual_revenue,
                'years_in_business' => $finance->years_in_business,
                'accounts_payable_email' => $finance->accounts_payable_email,
                'accounts_payable_phone' => $finance->accounts_payable_phone,
                'duns_number' => $finance->duns_number,
                'payment_due' => $finance->payment_due ? date('d-m-Y', strtotime($finance->payment_due)) : null,
                'created_by' => $finance->createdBy?->username ?? null,
                'updated_by' => $finance->updatedBy?->username ?? null,
                'created_at' => date('d-m-Y H:i:s', strtotime($finance->created_at)),
                'updated_at' => date('d-m-Y H:i:s', strtotime($finance->updated_at)),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => __("msg_rec_list"),
            'data' => [
                'current_page' => (int) $page,
                'per_page' => (int) $perPage,
                'total_pages' => $totalPages,
                'total_records' => $totalRecords,
                'data' => $formattedFinance,
            ]
        ]);
    }

     /**
 * @OA\Post(
 *     path="/api/finances",
 *     summary="Create a new finance record",
 *     tags={"Finance"},
 *     @OA\Parameter(
 *         name="payment_selection",
 *         in="query",
 *         required=false,
 *         description="Credit,Debit Card,Net Banking,Net Payment Terms",
 *         @OA\Schema(type="string"),
 *         example="Credit"
 *     ),
 *     @OA\Parameter(
 *         name="payment_options",
 *         in="query",
 *         required=false,
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="term_selection",
 *         in="query",
 *         required=false,
 *         @OA\Schema(type="string"),
 *         example="Net 30 Days"
 *     ),
 *     @OA\Parameter(
 *         name="amount",
 *         in="query",
 *         required=false,
 *         @OA\Schema(type="number", format="float")
 *     ),
 *     @OA\Parameter(
 *         name="documents",
 *         in="query",
 *         required=false,
 *         @OA\Schema(type="string"),
 *         description="File path or filename"
 *     ),
 *     @OA\Parameter(
 *         name="payment_due",
 *         in="query",
 *         required=false,
 *         @OA\Schema(type="string", format="date")
 *     ),
 *     @OA\Parameter(
 *         name="type_of_business",
 *         in="query",
 *         required=false,
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="business_name",
 *         in="query",
 *         required=true,
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="business_address",
 *         in="query",
 *         required=false,
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="country",
 *         in="query",
 *         required=false,
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="address",
 *         in="query",
 *         required=false,
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="city",
 *         in="query",
 *         required=false,
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="state",
 *         in="query",
 *         required=false,
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="zip",
 *         in="query",
 *         required=false,
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="annual_revenue",
 *         in="query",
 *         required=false,
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="years_in_business",
 *         in="query",
 *         required=false,
 *         @OA\Schema(type="string"),
 *         example="5 – 10 years"
 *     ),
 *     @OA\Parameter(
 *         name="accounts_payable_email",
 *         in="query",
 *         required=false,
 *         @OA\Schema(type="string", format="email")
 *     ),
 *     @OA\Parameter(
 *         name="accounts_payable_phone",
 *         in="query",
 *         required=false,
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="duns_number",
 *         in="query",
 *         required=false,
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response=201, description="Created")
 * )
 */

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
          'payment_selection' => 'nullable|string',
            'payment_options' => 'nullable|string',
            'term_selection' => 'nullable|string',
            'amount' => 'required|integer|string',
            'documents' => 'nullable|string',
            'payment_due' => 'nullable|date',
            'type_of_business' => 'nullable|string|max:255',
            'business_name' => 'required|string|max:255',
            'business_address' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'nullable|string|max:255',
            'annual_revenue' => 'nullable|numeric',
            'zip' => 'nullable|numeric',
            'years_in_business' => 'nullable|string',
            'accounts_payable_email' => 'nullable|email',
            'accounts_payable_phone' => 'nullable|string',
            'duns_number' => 'nullable|string',
            'status' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $finance = Finance::create($data);
        return response()->json([
            'success' => true,
            'message' => 'Finance created successfully',
            'data' => $finance
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/finances/{id}",
     *     summary="Get finance record by ID",
     *     tags={"Finance"},
     *     @OA\Parameter(
     *         name="id", in="path", required=true, @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=404, description="Not Found")
     * )
     */
    public function show($id)
    {
        $finance = Finance::find($id);
        if (!$finance) {
            return response()->json(['message' => 'Record not found'], 404);
        }
         return response()->json([
            'success' => true,
            'message' => 'Finance details successfully',
            'data' => $finance
        ], 201);
         
    }

    
     /**
     * @OA\Put(
     *     path="/api/finances/{id}",
     *     summary="Update finance record",
     *     tags={"Finance"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"business_name","type_of_business"},
     *             @OA\Property(property="payment_selection", type="string"),
     *             @OA\Property(property="payment_options", type="string"),
     *             @OA\Property(property="term_selection", type="string", example="Net 30 Days"),
     *             @OA\Property(property="amount", type="number", format="float"),
     *             @OA\Property(property="documents", type="string"),
     *             @OA\Property(property="payment_due", type="string", format="date"),
     *             @OA\Property(property="type_of_business", type="string"),
     *             @OA\Property(property="business_name", type="string"),
     *             @OA\Property(property="business_address", type="string"),
     *             @OA\Property(property="country", type="string"),
     *             @OA\Property(property="address", type="string"),
     *             @OA\Property(property="city", type="string"),
     *             @OA\Property(property="state", type="string"),
     *             @OA\Property(property="zip", type="string"),
     *             @OA\Property(property="annual_revenue", type="string"),
     *             @OA\Property(property="years_in_business", type="string", example="5 – 10 years"),
     *             @OA\Property(property="accounts_payable_email", type="string", format="email"),
     *             @OA\Property(property="accounts_payable_phone", type="string"),
     *             @OA\Property(property="duns_number", type="string")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Updated")
     * )
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'payment_selection' => 'nullable|string',
            'payment_options' => 'nullable|string',
            'term_selection' => 'nullable|string',
            'amount' => 'required|integer|string',
            'documents' => 'nullable|string',
            'payment_due' => 'nullable|date',
            'type_of_business' => 'nullable|string|max:255',
            'business_name' => 'required|string|max:255',
            'business_address' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'nullable|string|max:255',
            'annual_revenue' => 'nullable|numeric',
            'zip' => 'nullable|numeric',
            'years_in_business' => 'nullable|string',
            'accounts_payable_email' => 'nullable|email',
            'accounts_payable_phone' => 'nullable|string',
            'duns_number' => 'nullable|string',
            'status' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $finance = Finance::find($id);
        if (!$finance) {
            return response()->json(['message' => 'Record not found'], 404);
        }

        $data = $validator->validated();
        $data['updated_by'] = Auth::id() ?? 1;
        $finance->update($data);


        return response()->json([
            'success' => true,
            'message' => 'Finance updated successfully',
            'data' => $finance
        ], 200);
    }

    /**
     * @OA\Delete(
     *     path="/api/finances/{id}",
     *     summary="Delete finance record",
     *     tags={"Finance"},
     *     @OA\Response(response=204, description="Deleted")
     * )
     */
    public function destroy($id)
    {
        $finance = Finance::find($id);
        if (!$finance) {
            return response()->json(['message' => 'Record not found'], 404);
        }
        $finance->delete();
        return response()->json([
            'success' => true,
            'message' => 'Finances deleted successfully'
        ]);
    }
}
