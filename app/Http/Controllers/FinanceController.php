<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FrontEnd\Finance;

use App\Models\FinancesPayment;
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
     *         name="sort_dir",
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
                    ->orWhere('requestedAmount', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('term_selection', 'like', "%{$search}%")                    
                    ->orWhere('mobile_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('search')) {
            $search = $request->input('search');

            $query->where(function ($q) use ($search) {

                $q->where('business_name', 'like', "%{$search}%")
                    ->orWhere('requestedAmount', 'like', "%{$search}%")
                    ->orWhere('term_selection', 'like', "%{$search}%")                   
                    ->orWhereHas('customer', function ($c) use ($search) {
                        $c->where('name', 'like', "%{$search}%")
                        ->orWhere('mobile_number', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $searchableColumns = ['id', 'term_selection', 'business_name', 'status', 'duns_number', 'requestedAmount', 'email', 'mobile_number'];
        $sortableColumns = array_merge($searchableColumns, ['created_at', 'updated_at']);


        $sortBy = in_array($request->input('sort_by'), $sortableColumns) ? $request->input('sort_by') : 'created_at';
        $sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

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

        'requestedAmount' => number_format($finance->requestedAmount, 2),
        'creditLimitAmount' => number_format($finance->creditLimitAmount, 2),
        'approvedAmount' => number_format($finance->approvedAmount, 2),
        'approvalDate' => date('d-m-Y',strtotime($finance->approvalDate)),
        'approvalBy' => $finance->approvalBy?->name,

        'status' => $finance->status,
        'business_name' => $finance->business_name,

        // CUSTOMER FIELDS
        'customer_name' => $finance->customer?->name,
        'customer_email' => $finance->customer?->email,
        'customer_mobile' => $finance->customer?->mobile_number,

        'annual_revenue' => $finance->annual_revenue,
        'years_in_business' => $finance->years_in_business,

        'documents' => $finance->documents,
        'duns_number' => $finance->duns_number,
        'payment_due' => $finance->payment_due ? date('d-m-Y', strtotime($finance->payment_due)) : null,

        // CREATED BY / UPDATED BY
        'created_by' => $finance->createdBy?->username,
        'updated_by' => $finance->updatedBy?->username,

        'created_at' => date('d-m-Y H:i:s', strtotime($finance->created_at)),
        'updated_at' => date('d-m-Y H:i:s', strtotime($finance->updated_at)),
    ];
});

        return response()->json([
            'success' => true,
            'message' => __("msg_rec_list"),
            'current_page' => $page,
            'per_page' => (int) $perPage,
            'total_records' => $totalRecords,
            'total_pages' => $totalPages,
            'data' => $formattedFinance,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/finances",
     *     summary="Create a new finance record",
     *     tags={"Finance"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"customer_id", "term_selection", "requestedAmount"},              
     *                 @OA\Property(property="customer_id", type="string", example="19", description="Select customer_id"),      
     *                 @OA\Property(property="payment_options", type="string", example="netTerm", description="Payment option description"),      
     *                 @OA\Property(property="term_selection", type="string",enum={"Net 30 Days","Net 45 Days","Net 60 Days"}, example="Net 30 Days", description="Net Pay in 30/45/60 Days"),
     *                 @OA\Property(property="requestedAmount", type="number", format="float", example=5000.75, description="Enter amount"),
     *                 @OA\Property(property="documents", type="string", format="binary", description="Upload supporting document file"),     *                  
     *                 @OA\Property(property="type_of_business", type="string", example="E-commerce", description="Type of business (Advertising / E-commerce)"),
     *                 
     *                 @OA\Property(property="annual_revenue", type="string", example="10M USD"),
     *                 @OA\Property(property="years_in_business", type="string", example="5 – 10 years"),     *                 
     *                 @OA\Property(property="duns_number", type="string", example="123456789"),
     *                 @OA\Property(property="creditLimitAmount", type="integer", example="5000"),
     *                 @OA\Property(property="approvedAmount", type="integer", example="5000"),
     *                 @OA\Property(property="status", type="integer", enum={"Active","Overdue","Pending"}, example="Pending"),
     *                  
     *                 
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Finance record created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Finance record created successfully."),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,id',
            'payment_options' => 'nullable|string',
            'term_selection' => 'nullable|string|in:Net 30 Days,Net 45 Days,Net 60 Days',
            'requestedAmount' => 'required|numeric',
            'documents' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png,webp,svg|max:10240',            
            'type_of_business' => 'nullable|string|max:255',             
            'annual_revenue' => 'nullable|string',
            'years_in_business' => 'nullable|string',           
            'duns_number' => 'nullable|string',
            'creditLimitAmount' => 'nullable|integer|string',
            'approvedAmount' => 'nullable|integer|string',
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
        $data['created_by'] = Auth::id() ?? 1;
        $data['updated_by'] = '0';
        if ($request->status == 'Active') {
            $data['approvalBy'] = Auth::id();
        }
        // if (!empty($request->creditLimitAmount) && !empty($request->approvedAmount)) {
        //     $data['availableCreditAmount']  = $request->creditLimitAmount - $request->approvedAmount;
        // }
        // if (!empty($request->approvedAmount)) {
        //     $data['usedCreditAmount']  = $request->approvedAmount;
        // }         

        // if ($request->term_selection == 'Net 30 Days') {
        //     $nextPaymentDue = "+30 Days";
        // } elseif ($request->term_selection == 'Net 45 Days') {
        //     $nextPaymentDue = "+45 Days";
        // } else {
        //     $nextPaymentDue = "+60 Days";
        // }
        // if(!empty($nextPaymentDue)){
        //     $data['next_due_date'] =date('Y-m-d', strtotime($nextPaymentDue));
        // }
        
        if ($request->hasFile('documents')) {
            $data['documents'] = uploadImageToWebpS3FromFile(
                $request,
                'documents',
                env('STORAGE_ENV') . '/documents'
            );
        } else {
            $data['documents'] = null;
        }

        $finance = Finance::create($data);
        
        return response()->json([
            'success' => true,
            'message' => 'Finance record created successfully.',
            'data' => $finance
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/finances/{id}",
     *     summary="Get finance record by ID",
     *     tags={"Finance"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Finance record ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Finance record retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Finance record retrieved successfully."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="payment_selection", type="string", example="Credit"),
     *                 @OA\Property(property="amount", type="number", format="float", example=5000.75),
     *                 @OA\Property(property="business_name", type="string", example="ABC Pvt Ltd"),     *                  
     *                 @OA\Property(property="documents", type="string", example="https://s3.amazonaws.com/path/to/document.pdf"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-01-01T10:00:00Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-01-02T15:30:00Z")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Finance record not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Finance record not found.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
  public function show($id)
{
    $finance = Finance::with([
        'customer',
        'createdBy',
        'updatedBy'
    ])->find($id);

    if (!$finance) {
        return response()->json([
            'success' => false,
            'message' => 'Finance record not found.'
        ], 404);
    }

    return response()->json([
        'success' => true,
        'message' => 'Finance record retrieved successfully.',
        'data' => $finance
    ], 200);
}
    /**
     * @OA\Post(
     *     path="/api/finances/{id}",
     *     summary="Update an existing finance record",
     *     tags={"Finance"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Finance record ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"customer_id"},              
     *                 @OA\Property(property="customer_id", type="integer", example="19", description="Payment options details"),
     *                @OA\Property(property="term_selection", type="string",enum={"Net 30 Days","Net 45 Days","Net 60 Days"}, example="Net 30 Days", description="Net Pay in 30/45/60 Days"),
     *                 @OA\Property(property="requestedAmount", type="number", format="float", example=5000.75, description="Transaction amount"),
     *                 @OA\Property(property="documents", type="string", format="binary", description="Upload related document"),
     *                 
     *                 @OA\Property(property="type_of_business", type="string", example="E-commerce", description="Type of business"),
     *                  @OA\Property(property="annual_revenue", type="string", example="10M USD", description="Annual business revenue"),
     *                 @OA\Property(property="years_in_business", type="string", example="5 – 10 years", description="Years in business"),
     *                  @OA\Property(property="duns_number", type="string", example="123456789", description="DUNS number (if applicable)"),
     *                  @OA\Property(property="creditLimitAmount", type="integer", example="5000"),
     *                 @OA\Property(property="approvedAmount", type="integer", example="5000"),
     *                 @OA\Property(property="status", type="integer", enum={"Active","Overdue","Pending"}, example="Pending"),
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Finance record updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Finance record updated successfully."),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,id',          
            'payment_options' => 'nullable|string',            
            'term_selection' => 'nullable|string|in:Net 30 Days,Net 45 Days,Net 60 Days',
            'requestedAmount' => 'required|numeric',
            'documents' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png,webp,svg|max:10240',
            
            'type_of_business' => 'nullable|string|max:255',             
            'annual_revenue' => 'nullable|string',
            'years_in_business' => 'nullable|string',           
            'duns_number' => 'nullable|string',
            'status' => 'nullable|string',
            'creditLimitAmount' => 'nullable|string',
            'approvedAmount' => 'nullable|string',
            
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
            return response()->json([
                'success' => false,
                'message' => 'Record not found'
            ], 404);
        }

        $data = $validator->validated();
        if ($request->status == 'Active') {
            $data['approvalBy'] = Auth::id();            
        } 
        
        // if (!empty($request->creditLimitAmount) && !empty($request->approvedAmount)) {
        //     $data['availableCreditAmount']  = $request->approvedAmount - $request->approvedAmount;
        // }
                 
        // if ($request->term_selection == 'Net 30 Days') {
        //     $nextPaymentDue = "+30 Days";
        // } elseif ($request->term_selection == 'Net 45 Days') {
        //     $nextPaymentDue = "+45 Days";
        // } else {
        //     $nextPaymentDue = "+60 Days";
        // }
        // if(!empty($nextPaymentDue)){
        //     $data['next_due_date'] =date('Y-m-d', strtotime($nextPaymentDue));
        // }
        $data['updated_by'] = Auth::id() ?? 1;
        if ($request->hasFile('documents')) {
            $data['documents'] = uploadImageToWebpS3FromFile(
                $request,
                'documents',
                env('STORAGE_ENV') . '/documents'
            );
        } else {
            $data['documents'] = $finance->documents;
        }

        $finance->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Finance record updated successfully.',
            'data' => $finance
        ], 200);
    }


    /**
     * @OA\Delete(
     *     path="/api/finances/{id}",
     *     summary="Delete a finance record",
     *     description="Deletes a finance record by its unique ID.",
     *     tags={"Finance"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Finance record ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Finance record deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Finance record deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Finance record not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Finance record not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error"
     *     )
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
