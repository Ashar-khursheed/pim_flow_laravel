<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FrontEnd\Finance;

use App\Models\FrontEnd\FinancesPayment;
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

        $query = Finance::with(['createdBy', 'updatedBy', 'customer', 'customerAddress', 'approvalUser']);
        if ($request->filled('status') && $request->input('status') !== "all") {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');

            $query->where(function ($q) use ($search) {
                $q->where('business_name', 'like', "%{$search}%")
                    ->orWhere('requested_amount', 'like', "%{$search}%")
                    ->orWhere('term_selection', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($c) use ($search) {
                        $c->where('name', 'like', "%{$search}%")
                            ->orWhere('mobile_number', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('customerAddress', function ($a) use ($search) {
                        $a->where('address', 'like', "%{$search}%")
                            ->orWhere('city', 'like', "%{$search}%")
                            ->orWhere('state', 'like', "%{$search}%")
                            ->orWhere('pincode', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('search')) {
            $search = $request->input('search');

            $query->where(function ($q) use ($search) {

                $q->where('business_name', 'like', "%{$search}%")
                    ->orWhere('requested_amount', 'like', "%{$search}%")
                    ->orWhere('term_selection', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($c) use ($search) {
                        $c->where('name', 'like', "%{$search}%")
                            ->orWhere('mobile_number', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $searchableColumns = ['id', 'term_selection', 'business_name', 'status', 'duns_number', 'requested_amount', 'email', 'mobile_number'];
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
            $address = $finance->customerAddress;

            return [
                'id' => $finance->id,
                'customer_id' => $finance->customer_id,
                'payment_selection' => $finance->payment_selection,
                'payment_options' => $finance->payment_options,
                'term_selection' => $finance->term_selection,
                'type_of_business' => $finance->type_of_business,
                'legal_business_name' => $finance->legal_business_name,
                'doing_business' => $finance->doing_business,
                'role_at_business' => $finance->role_at_business,

                'requested_amount' => number_format($finance->requested_amount, 2),
                'credit_limit_amount' => number_format($finance->credit_limit_amount, 2),
                'approved_amount' => number_format($finance->approved_amount, 2),
                'approval_date' => date('d-m-Y', strtotime($finance->approval_date)),
                'approvalBy' => $finance->approvalUser?->username,
                'accounts_payable_email' => $finance->accounts_payable_email,
                'accounts_payable_phone' => $finance->accounts_payable_phone,
                 
                'used_credit_amount' => $finance->used_credit_amount,
                'available_credit_amount' => $finance->available_credit_amount, 
                'paid_amount' => $finance->paid_amount,             
                'next_due_amt' => $finance->next_due_amt,  
                'next_due_date' => $finance->next_due_date ? date('d-m-Y', strtotime($finance->next_due_date)) : null,             
                'status' => $finance->status,
                'accounts_status' => $finance->accounts_status, 
                               
                'customer_name' => $finance->customer?->name,
                'business_name' => $finance->customer?->business_name,
                'customer_email' => $finance->customer?->email,
                'customer_mobile' => $finance->customer?->mobile_number,
                'annual_revenue' => $finance->annual_revenue,
                'years_in_business' => $finance->years_in_business,
                'documents' => $finance->documents,
                'duns_number' => $finance->duns_number,                          
               
                'address' =>  $address ? [
                    'address' => $address->address,
                    'city' => $address->city,
                    'state' => $address->state,
                    'zip_code' => $address->zip_code,
                ] : null,
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
     *                 required={"customer_id", "term_selection", "requested_amount","customer_address_id"},             
     *                   
     *                 @OA\Property(property="payment_options", type="string", example="netTerm", description="Payment option netTerm"),    
     *                 @OA\Property(property="customer_id", type="interger", example="2487", description="customer address id"),             
     *                 @OA\Property(property="customer_address_id", type="interger", example="23", description="customer address id"),             
     *                 @OA\Property(property="term_selection", type="string",enum={"Net 30 Days","Net 45 Days","Net 60 Days"}, example="Net 30 Days", description="Net Pay in 30/45/60 Days"),
     *                 @OA\Property(property="legal_business_name", type="string", example="ABC Company", description="Enter ABC Company"),
     *                 @OA\Property(property="doing_business", type="string", example="ABC CO", description="Enter ABC CO"),
     *                 @OA\Property(property="requested_amount", type="number", format="float", example=5000.75, description="HOW MUCH DO YOU EXPECT TO PURCHASE OVER 30 DAYS requested amount"),
     *                 @OA\Property(property="documents", type="string", format="binary", description="Upload supporting document file"),
     *                 
     *                 @OA\Property(property="type_of_business", type="string", enum={"Corporation","LLC","Sole proprietor/ partnership","Non-profit","Government"}, example="E-commerce", description="Type of business (Advertising / E-commerce)"),                
     *                 @OA\Property(property="accounts_payable_email", type="string", example="pay@gmail.com", description="accounts payable email"),                
     *                 @OA\Property(property="accounts_payable_phone", type="string", example="123456789", description="Accounts Payable Phone"),                
     *                               
     *                 @OA\Property(property="annual_revenue", type="string", enum={"Less then 1,000,000","1,000,000 to 2,000,000","2,000,000 to 5,000,000","5,000,000 to 25,000,000","More than 25,000,000",}, example="Less then 1,000,000"),
     *                 @OA\Property(property="years_in_business", type="string", enum={"Less than 2 years","2 - 5 years","5 - 10 years","More than 10 years"}, example="5 – 10 years"),   
     *                 @OA\Property(property="first_name", type="string", example="John"),   
     *                 @OA\Property(property="last_name", type="string", example="Doe"),   
     *                 @OA\Property(property="email", type="string", example="john@gmail.com"),   
     *                 @OA\Property(property="role_at_business", type="string", enum={"CEO","Accounts payable"}, example="Accounts payable"),   
     *             
     *                 @OA\Property(property="country", type="string", example="United States"),   
     *                 @OA\Property(property="address", type="string", example="Address"),   
     *                 @OA\Property(property="city", type="string", example="City"),   
     *                 @OA\Property(property="state", type="string", example="State"),   
     *                 @OA\Property(property="zipcode", type="string", example="zipcode"),  
     *                 @OA\Property(property="accounts_status", type="string", enum={"Approved","Pending","Hold","Rejected"}, example="Approved"),  
     *                  @OA\Property(property="duns_number", type="string", example="123456789")
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
            'requested_amount' => 'required|numeric',
            'documents' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png,webp,svg|max:10240',
            'type_of_business' => 'nullable|string|max:255',
            'annual_revenue' => 'nullable|string',
            'years_in_business' => 'nullable|string',
            'duns_number' => 'nullable|string',
            'legal_business_name' => 'nullable|string',
            'doing_business' => 'nullable|string',  
            'credit_limit_amount' => 'nullable|integer|string',
            'approved_amount' => 'nullable|integer|string',             
            'accounts_payable_email' => 'required|email|string|max:255',
            'accounts_payable_phone' => 'required|string|max:255',
            'customer_address_id' => 'required|numeric',
            'accounts_status' => 'required|string',
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
        if ($request->accounts_status == 'Approved') {
            $data['approvalBy'] = Auth::id();
        }
        $data['business_address'] = $request->address;
        if ($request->approved_amount > $request->credit_limit_amount) {
            return response()->json([
                'success' => false,
                'message' => 'Approved Amount cannot be greater than Credit Limit Amount.',

            ], 201);
        }

        if ($request->hasFile('documents')) {
            $data['documents'] = uploadImageToWebpS3FromFile(
                $request,
                'documents',
                env('STORAGE_ENV') . '/documents'
            );
        } else {
            $data['documents'] = null;
        }
        $customer_id = $request->customer_id;
        $finance = Finance::where('customer_id', $customer_id)
            ->orderBy('id', 'desc')
            ->first();
       if ($finance) {

            if ($finance->status === "Paid" && $finance->accounts_status === "Approved") {
                $finance = Finance::create($data);
            } else {

                return response()->json([
                    'success' => false,
                    'message' => 'Finance cannot be created. Previous finance is already Pending or Overdue.'
                ], 422);
            }
        } else if (empty($finance)) {
            $finance = Finance::create($data);
        }

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
            'updatedBy',
            'customerAddress',
            'approvalUser'
        ])->find($id);

        if (!$finance) {
            return response()->json([
                'success' => false,
                'message' => 'Finance record not found.'
            ], 404);
        }
        $address = $finance->customerAddress;
        $financeData =  [
            'id' => $finance->id,
            'customer_id' => $finance->customer_id,
            'payment_selection' => $finance->payment_selection,
            'payment_options' => $finance->payment_options,
            'term_selection' => $finance->term_selection,
            'type_of_business' => $finance->type_of_business,
            'legal_business_name' => $finance->legal_business_name,
            'doing_business' => $finance->doing_business,
            'business_address' => $finance->business_address,
            'requested_amount' => number_format($finance->requested_amount, 2),
            'credit_limit_amount' => number_format($finance->credit_limit_amount, 2),
            'approved_amount' => number_format($finance->approved_amount, 2),
            'approval_date' => date('d-m-Y', strtotime($finance->approval_date)),
            'approvalBy' => $finance->approvalUser?->username,
            'accounts_payable_email' => $finance->accounts_payable_email,
            'accounts_payable_phone' => $finance->accounts_payable_phone,
           
            'used_credit_amount' => $finance->used_credit_amount,
            'available_credit_amount' => $finance->available_credit_amount,            
            'next_due_amt' => $finance->next_due_amt,
            'paid_amount' => $finance->paid_amount,
            'status' => $finance->status,
            'accounts_status' => $finance->accounts_status,           

            // CUSTOMER FIELDS
            'customer_name' => $finance->customer?->name,
            'business_name' => $finance->customer?->business_name,
            'customer_email' => $finance->customer?->email,
            'customer_mobile' => $finance->customer?->mobile_number,

            'annual_revenue' => $finance->annual_revenue,
            'years_in_business' => $finance->years_in_business,

            'documents' => $finance->documents,
            'duns_number' => $finance->duns_number,
            'next_due_date' => $finance->next_due_date ? date('d-m-Y', strtotime($finance->next_due_date)) : null,
            'address' =>  $address ? [
                'address' => $address->address,
                'city' => $address->city,
                'state' => $address->state,
                'zip_code' => $address->zip_code,
            ] : null,
            // CREATED BY / UPDATED BY
            'created_by' => $finance->createdBy?->username,
            'updated_by' => $finance->updatedBy?->username,

            'created_at' => date('d-m-Y H:i:s', strtotime($finance->created_at)),
            'updated_at' => date('d-m-Y H:i:s', strtotime($finance->updated_at)),
        ];


        return response()->json([
            'success' => true,
            'message' => 'Finance record retrieved successfully.',
            'data' => $financeData
        ], 200);
    }

     /**
 * @OA\Post(
 *     path="/api/finances/{id}",
 *     summary="Update an existing finance record",
 *     tags={"Finance"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="Finance record ID",
 *         @OA\Schema(type="integer")
 *     ),
 *
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="multipart/form-data",
 *             @OA\Schema(
 *                 type="object",
 *                 required={"accounts_payable_email", "accounts_payable_phone", "requested_amount", "status", "credit_limit_amount", "accounts_status"},
 *
 *                 @OA\Property(
 *                     property="term_selection",
 *                     type="string",
 *                     enum={"Net 30 Days", "Net 45 Days", "Net 60 Days"},
 *                     example="Net 30 Days"
 *                 ),
 *
 *                 @OA\Property(
 *                     property="requested_amount",
 *                     type="number",
 *                     format="float",
 *                     example=5000.75
 *                 ),
 *
 *                 @OA\Property(
 *                     property="documents",
 *                     type="string",
 *                     format="binary",
 *                     description="Upload related document"
 *                 ),
 *
 *                 @OA\Property(
 *                     property="type_of_business",
 *                     type="string",
 *                     enum={"Corporation","LLC","Sole proprietor/ partnership","Non-profit","Government"},
 *                     example="Corporation"
 *                 ),
 *
 *                 @OA\Property(
 *                     property="annual_revenue",
 *                     type="string",
 *                     enum={"Less than 1,000,000","1,000,000 to 2,000,000","2,000,000 to 5,000,000","5,000,000 to 25,000,000","More than 25,000,000"},
 *                     example="Less than 1,000,000"
 *                 ),
 *
 *                 @OA\Property(
 *                     property="years_in_business",
 *                     type="string",
 *                     enum={"Less than 2 years","2 - 5 years","5 - 10 years","More than 10 years"},
 *                     example="5 - 10 years"
 *                 ),
 *
 *                 @OA\Property(
 *                     property="role_at_business",
 *                     type="string",
 *                     enum={"CEO","Accounts payable"},
 *                     example="Accounts payable"
 *                 ),
 *
 *                 @OA\Property(
 *                     property="accounts_payable_email",
 *                     type="string",
 *                     example="pay@gmail.com"
 *                 ),
 *
 *                 @OA\Property(
 *                     property="accounts_payable_phone",
 *                     type="string",
 *                     example="123456789"
 *                 ),
 *
 *                 @OA\Property(
 *                     property="duns_number",
 *                     type="string",
 *                     example="123456789"
 *                 ),
 *
 *                 @OA\Property(
 *                     property="credit_limit_amount",
 *                     type="number",
 *                     example=5000
 *                 ),
 *
 *                 @OA\Property(
 *                     property="status",
 *                     type="string",
 *                     enum={"Paid", "Overdue", "Pending"},
 *                     example="Pending"
 *                 ),
 *
 *                 @OA\Property(
 *                     property="accounts_status",
 *                     type="string",
 *                     enum={"Pending", "Approved", "Rejected", "Hold"},
 *                     example="Pending"
 *                 ),
 *
 *                 @OA\Property(
 *                     property="approved_amount",
 *                     type="number",
 *                     example=5000,
 *                     description="Required when accounts_status is Approved"
 *                 ),
 *                 @OA\Property(
 *                     property="rejection_reason",
 *                     type="string",
 *                     example="rejection reason",
 *                     description="Descrive rejection reason"
 *                 )
 *             )
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Finance record updated successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Finance record updated successfully."),
 *             @OA\Property(property="data", type="object")
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=422,
 *         description="Validation error",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="Validation failed"),
 *             @OA\Property(property="errors", type="object")
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=404,
 *         description="Record not found",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="Record not found")
 *         )
 *     )
 * )
 */
public function update(Request $request, $id)
{  
    $validator = Validator::make($request->all(), [
        'payment_options' => 'nullable|string',
        'term_selection' => 'nullable|string|in:Net 30 Days,Net 45 Days,Net 60 Days',
        'requested_amount' => 'required|numeric',
        'documents' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png,webp,svg|max:10240',
        'type_of_business' => 'nullable|string|max:255',
        'role_at_business' => 'nullable|string|max:255',
        'accounts_payable_email' => 'required|email|string|max:255',
        'accounts_payable_phone' => 'required|string|max:255',
        'annual_revenue' => 'nullable|string',
        'years_in_business' => 'nullable|string',
        'duns_number' => 'nullable|string',
        'status' => 'required|in:Paid,Overdue,Pending',
        'credit_limit_amount' => 'required|numeric',                          
        'accounts_status' => 'required|in:Pending,Approved,Rejected,Hold',
        'approved_amount' => 'nullable|numeric|required_if:accounts_status,Approved',
        'rejection_reason' => 'nullable|string',
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

    // Check approved amount vs credit limit BEFORE processing
    if ($request->accounts_status == 'Approved' && $request->approved_amount > $request->credit_limit_amount) {
        return response()->json([
            'success' => false,
            'message' => 'Approved Amount cannot be greater than Credit Limit Amount.',
        ], 422); // Changed from 201 to 422
    }

    $data = $validator->validated();
    $data['updated_by'] = Auth::id() ?? 1;
    

    // Handle approval logic
    if ($request->accounts_status == 'Approved') {
        $data['approvalBy'] = Auth::id();
        $data['approved_amount'] = $request->approved_amount;
        $data['approval_date'] = now();
    } else {
        $data['approved_amount'] = 0;  
        $data['approvalBy'] = null; 
        $data['approval_date'] = null; 
    }

    // Handle document upload
    if ($request->hasFile('documents')) {
        $data['documents'] = uploadImageToWebpS3FromFile(
            $request,
            'documents',
            env('STORAGE_ENV') . '/documents'
        );
    }
    // Note: No need for else block - if no new document, validated data won't include it
    // and update() will only update fields present in $data

    $finance->update($data);

    return response()->json([
        'success' => true,
        'message' => 'Finance record updated successfully.',
        'data' => $finance->fresh() // Refresh to get updated data
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
     *      
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


    /**
     * @OA\Post(
     *     path="/api/finances/{id}/account-status",
     *     summary="Update finance account status",
     *     tags={"Finance"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Finance ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"accounts_status", "approved_amount"},
     *
     *                 @OA\Property(
     *                     property="accounts_status",
     *                     type="string",
     *                     description="Finance account status",
     *                     enum={"Approved","Pending","Rejected","Hold"},
     *                     example="Approved"
     *                 ),
     *
     *                 @OA\Property(
     *                     property="approved_amount",
     *                     type="number",
     *                     format="float",
     *                     description="Approved amount",
     *                     example=300.00
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Status updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Status updated successfully.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Finance record not found"
     *     )
     * )
     */



    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'accounts_status'   => 'required|in:Pending,Approved,Rejected,Hold',
            'approved_amount'   => 'required|numeric|required_if:accounts_status,Approved'
        ]);

        $finance = Finance::find($id);

        if (!$finance) {
            return response()->json([
                'success' => false,
                'message' => 'Finance record not found.'
            ], 404);
        }
        if ($request->approved_amount > $finance->credit_limit_amount) {
            return response()->json([
                'success' => false,
                'message' => 'Approved Amount cannot be greater than Credit Limit Amount.',
            ], 201);
        }
        if ($request->accounts_status == 'Approved' && !empty($request->approved_amount)) {
            $finance->approvalBy = Auth::id();
            $finance->approved_amount = $request->approved_amount;
            $finance->approval_date = now();
        } else {
            $finance->approved_amount = '0';
             $finance->approvalBy = null;
             $finance->approval_date = null;
        }
        $finance->accounts_status = $request->accounts_status;
        $finance->save();

        return response()->json([
            'success' => true,
            'message' => 'Finance account status updated successfully.',
            'data' => $finance
        ]);
    }
    /**
     * @OA\Get(
     *     path="/api/finances/{id}/due",
     *     summary="Get finance due amount and due date",
     *     tags={"Finance"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Finance ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Due details fetched",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="finance_id", type="integer"),
     *                 @OA\Property(property="customer_id", type="integer"),
     *                 @OA\Property(property="next_due_amt", type="number", format="float"),
     *                 @OA\Property(property="due_date", type="string", format="date")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Finance not found")
     * )
     */
    public function getDueDetails($id)
    {
        $finance = Finance::find($id);

        if (!$finance) {
            return response()->json([
                'success' => false,
                'message' => 'Finance record not found.'
            ], 404);
        }
        if (!empty($finance->next_due_amt)) {
            return response()->json([
                'success' => true,
                'message' => 'Finance due details fetched.',
                'data' => [
                    'finance_id'   => $finance->id,
                    'customer_id'  => $finance->customer_id,
                    'next_due_amt' => $finance->next_due_amt,
                    'next_due_date'     => $finance->next_due_date,
                    // 'term_selection' => $finance->term_selection,
                    // 'used_credit_amount' => $finance->used_credit_amount,
                    // 'available_credit_amount' => $finance->available_credit_amount,
                ]
            ], 200);
        } else {

            return response()->json([
                'success' => false,
                'message' => 'Finance no due amount.',

            ], 200);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/finances/pay/{id}",
     *     summary="Pay finance amount",
     *     tags={"Finance"},
     *     security={{"bearerAuth":{}}},
     * @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Finance ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"customer_id", "pay_amount"},     *              
     *             @OA\Property(property="customer_id", type="integer"),
     *             @OA\Property(property="pay_amount", type="number", format="float")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="finance_id", type="integer"),
     *                 @OA\Property(property="customer_id", type="integer"),
     *                 @OA\Property(property="paid_amount", type="number", format="float"),
     *                 @OA\Property(property="remaining_due", type="number", format="float"),
     *                 @OA\Property(property="status", type="string")
     *             )
     *         )
     *     )
     * )
     */
    public function payAmount(Request $request,$id)
    {  
        $request->validate([             
            'customer_id' => 'required|exists:customers,id',
            'pay_amount'  => 'required|numeric|min:1',
        ]);

        $finance = Finance::find($id);
        
        if (!$finance) {
            return response()->json([
                'success' => false,
                'message' => 'Finance record not found.'
            ], 404);
        }


        if ($finance->next_due_amt < $request->pay_amount) {
            return response()->json([
                'success' => false,
                'message' => 'Pay amount cannot be greater than due amount.',
            ], 201);
        }


        if (!empty($finance->next_due_amt)) {
            $data = [
                'finances_id'  => $request->id,
                'customer_id' => trim($request->customer_id),
                'due_amount'      => $finance->next_due_amt,
                'due_date'      => $finance->next_due_date,
                'paid_amount'      => $request->pay_amount,
                'paid_on_date'   => now(),
                'balance'      => $finance->next_due_amt - $request->pay_amount,
                'creditTerms'      => $finance->term_selection,
                'payment_mode'      => $request->payment_mode,
                'paid_by'      => Auth::id(),
            ];


            $payment = FinancesPayment::create($data);

            // Update due amount
            $finance->next_due_amt = max(0, $finance->next_due_amt - $request->pay_amount);
            // Update status
            $finance->status = $finance->next_due_amt <= 0 ? 'Paid' : 'Pending';
            $finance->paid_amount = $finance->paid_amount + $request->pay_amount;
            $finance->save();
        }
        return response()->json([
            'success' => true,
            'message' => 'Payment processed successfully.',
            'data' => [
                'finance_id'     => $finance->id,
                'customer_id'    => $request->customer_id,
                'paid_amount'    => $request->pay_amount,
                'next_due_amt'  => $finance->next_due_amt,
                'status'         => $finance->status
            ]
        ]);
    }


     /**
     * @OA\Get(
     *     path="/api/finances/{id}/payment-history",
     *     summary="Get payment history",
     *     tags={"Finance"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Finance ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Due details fetched",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="finance_id", type="integer"),
     *                 @OA\Property(property="customer_id", type="integer"),
     *                 @OA\Property(property="next_due_amt", type="number", format="float"),
     *                 @OA\Property(property="due_date", type="string", format="date")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Finance not found")
     * )
     */
    public function getPaymentHistory($id)
    {
         $paymentHistory = FinancesPayment::where('finances_id', $id)
            ->with(['paidByUser', 'updatedBy']) // load user relations
            ->orderBy('id', 'desc')
            ->get();

        if ($paymentHistory->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No payment history found.'
            ], 404);
        }

        $paymentData = $paymentHistory->map(function ($finance) {
            return [
                'id' => $finance->id,
                'finances_id' => $finance->finances_id,
                'customer_id' => $finance->customer_id,
                'due_date' => $finance->due_date,
                'due_amount' => $finance->due_amount,
                'paid_on_date' =>  $finance->paid_on_date,
                'paid_amount' => $finance->paid_amount,
                'balance' => $finance->balance,
                'creditTerms' => $finance->creditTerms,
                'payment_mode' => $finance->payment_mode,

                // RELATION DATA
                'paid_by' => $finance->paidByUser?->username,
                'updated_by' => $finance->updatedBy?->username,

                'created_at' => $finance->created_at->format('d-m-Y H:i:s'),
                'updated_at' => $finance->updated_at->format('d-m-Y H:i:s'),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Finance Payment History fetched.',
            'data' => $paymentData
        ], 200);

    }

}
