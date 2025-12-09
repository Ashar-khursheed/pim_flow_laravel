<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FrontEnd\Finance;

use App\Models\FrontEnd\FinancesPayment;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use OpenApi\Annotations as OA;
use Illuminate\Support\Facades\DB;
use App\Models\PaymentManagement;

use Illuminate\Support\Facades\Bus;
use Illuminate\Bus\Batch;

use App\Jobs\NetTerm\NetTermApprovedMailJob;
use App\Jobs\NetTerm\NetTermRejectedMailJob;

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

            $today = now()->toDateString();  
            Finance::where('customer_id', $finance->customer_id)
            ->where('status', 'Pending')              
            ->whereDate('next_due_date', '<', $today)
            ->update(['status' => 'Overdue']);

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
                'approval_date' => $finance->approval_date ? date('d-m-Y', strtotime($finance->approval_date)) : null,
                'approvalBy' => $finance->approvalUser?->username ?? null,
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

            'approval_date' => $finance->approval_date ? date('d-m-Y', strtotime($finance->approval_date)) : null,
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
            'term_selection' => 'required|string|in:Net 30 Days,Net 45 Days,Net 60 Days',
            'requested_amount' => 'required|numeric',
            'documents' => 'nullable|file|mimes:pdf|max:10240',
            'type_of_business' => 'nullable|string|max:255',
            'role_at_business' => 'nullable|string|max:255',
            'accounts_payable_email' => 'required|email|string|max:255',
            'accounts_payable_phone' => 'required|string|max:255',
            'annual_revenue' => 'required|string',
            'years_in_business' => 'required|string',           
            'credit_limit_amount' => 'nullable|numeric',
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

        // Check approved amount vs credit limit
        if ($request->accounts_status == 'Approved' && $request->approved_amount > $request->credit_limit_amount) {
            return response()->json([
                'success' => false,
                'message' => 'Approved Amount cannot be greater than Credit Limit Amount.',
            ], 422);
        }

        $data = $validator->validated();
        $data['updated_by'] = Auth::id() ?? 1;

        // Handle approval logic
        if ($request->accounts_status == 'Approved') {
            $next_due_amt = (float) $finance->next_due_amt;
             if (!empty($next_due_amt) && $next_due_amt > 0 ) {
                return response()->json([
                'success' => false,
                'message' => 'Cannot approve the request as there is pending due amount.',
                ], 201);
            }
            $data['approvalBy'] = Auth::id();
            $data['approved_amount'] = $request->approved_amount;
            $data['approval_date'] = now();
            $data['used_credit_amount'] = null;
            $data['available_credit_amount'] = null;
            $daa['next_due_date'] = null;
            $data['next_due_amt'] = null;
            $data['paid_amount'] = null;
            $data['status'] = "Pending";
            $batch = Bus::batch([])->name("Net Terms Approved by backend")->dispatch();
            $batch->options['queue'] = config('app.website') . '_NET_TRM';
            $batch->add(new NetTermApprovedMailJob([
                'recordId' => $finance->id
            ]));
        } else if ($request->accounts_status == 'Rejected') {
            $data['rejectedBy'] = Auth::id();
            $data['rejection_reason'] = $request->rejection_reason;
            $data['rejected_date'] = now();

            $batch = Bus::batch([])->name("Net Terms Rejected by backend")->dispatch();
            $batch->options['queue'] = config('app.website') . '_NET_TRM';
            $batch->add(new NetTermRejectedMailJob([
                'recordId' => $finance->id
            ]));
        }  

        // Handle document upload
        if ($request->hasFile('documents')) {
            $data['documents'] = uploadPdfToS3FromFile(
                $request,
                'documents',
                env('STORAGE_ENV') . '/customer/payment'
            );
        } else {
            $data['documents'] = null;
        }

        $finance->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Finance record updated successfully.',
            'data' => $finance->fresh()
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
            return response()->json([
                'success' => false,
                'message' => 'Finance record not found.'
            ], 404);
        }

        try {
            DB::beginTransaction();

            // Delete related payments
            FinancesPayment::where('finances_id', $finance->id)->delete();

            // Delete the finance record
            $finance->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Finance and related payment records deleted successfully.'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete record.',
                'error'   => $e->getMessage()
            ], 500);
        }
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
     *         required=true,
     *         description="Finance ID",
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
     *                 ),
     *
     *                 @OA\Property(
     *                     property="credit_limit_amount",
     *                     type="number",
     *                     format="float",
     *                     description="Credit limit amount",
     *                     example=10000.00
     *                 ),
     *
     *                 @OA\Property(
     *                     property="rejection_reason",
     *                     type="string",
     *                     description="Reason for rejection",
     *                     example="Insufficient credit history"
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
            'approved_amount'   => 'required|numeric|required_if:accounts_status,Approved',
            'credit_limit_amount'   => 'required|numeric',
            'rejection_reason'   => 'nullable|string'
        ]);

        $finance = Finance::find($id);

        if (!$finance) {
            return response()->json([
                'success' => false,
                'message' => 'Finance record not found.'
            ], 404);
        }
        if ($request->approved_amount > $request->credit_limit_amount) {
            return response()->json([
                'success' => false,
                'message' => 'Approved Amount cannot be greater than Credit Limit Amount.',
            ], 201);
        }
         

        if ($request->accounts_status == 'Approved' && !empty($request->approved_amount)) {
            $next_due_amt = (float) $finance->next_due_amt;
            if (!empty($next_due_amt) && $next_due_amt>0) {
                return response()->json([
                'success' => false,
                'message' => 'Cannot approve the request as there is pending due amount.',
                ], 201);
            }

            $finance->approvalBy = Auth::id();
            $finance->approved_amount = $request->approved_amount;
            $finance->credit_limit_amount = $request->credit_limit_amount;
            $finance->used_credit_amount = null;
            $finance->available_credit_amount = null;
            $finance->next_due_date = null;
            $finance->next_due_amt = null;
            $finance->paid_amount = null;
            $finance->approval_date = now();

            $batch = Bus::batch([])->name("Net Terms Approved by backend")->dispatch();
            $batch->options['queue'] = config('app.website') . '_NET_TRM';
            $batch->add(new NetTermApprovedMailJob([
                'recordId' => $finance->id
            ]));
        } else if ($request->accounts_status == 'Rejected') {

            $finance->rejectedBy = Auth::id();
            $finance->rejection_reason = $request->rejection_reason;
            $finance->rejected_date = now();

            $batch = Bus::batch([])->name("Net Terms Rejected by backend")->dispatch();
            $batch->options['queue'] = config('app.website') . '_NET_TRM';
            $batch->add(new NetTermRejectedMailJob([
                'recordId' => $finance->id
            ]));
        } else {
            $finance->approved_amount = '0';
            $finance->approvalBy = null;
            $finance->approval_date = null;
            $finance->credit_limit_amount = '0';
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
     *         description="Payment ID",
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
        $finance = FinancesPayment::find($id);

        if (!$finance) {
            return response()->json([
                'success' => false,
                'message' => 'Finance record not found.'
            ], 404);
        }
        if (!empty($finance->balance)) {
            return response()->json([
                'success' => true,
                'message' => 'Finance due details fetched.',
                'data' => [
                    'finance_id'   => $finance->id,
                    'customer_id'  => $finance->customer_id,
                    'due_date'     => $finance->due_date ? date('d-m-Y', strtotime($finance->due_date)) : null,
                    'balance'     => $finance->balance,

                ]
            ], 200);
        } else {

            return response()->json([
                'success' => false,
                'message' => 'Finance no due amount.',
                'data' => null
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
     *         description="Payment ID",
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
    public function payAmount(Request $request, $id)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'pay_amount'  => 'required|numeric|min:1',
        ]);

        $pay_amount = (float) $request->pay_amount; // Laravel auto-casts safely

        return DB::transaction(function () use ($id, $pay_amount, $request) {

            // Lock the row to prevent race conditions
            $financesPayment = FinancesPayment::where('id', $id)
                ->where('customer_id', $request->customer_id)
                ->lockForUpdate()
                ->first();

            if (!$financesPayment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Finance payment record not found or access denied.'
                ], 404);
            }

            // Recalculate current remaining balance
            $current_paid = $financesPayment->paid_amount ?? 0;
            $due_amount   = $financesPayment->due_amount ?? 0;
            $remaining    = $due_amount - $current_paid;

            if ($remaining <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'This invoice is already fully paid.'
                ], 400);
            }

            if ($pay_amount > $remaining) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment amount cannot exceed remaining due: ' . number_format($remaining, 2)
                ], 400);
            }

            // Load related finance with lock
            $finance = Finance::where('id', $financesPayment->finances_id)
                ->lockForUpdate()
                ->firstOrFail();

            // === Update FinancesPayment ===
            $financesPayment->paid_amount    += $pay_amount;
            $financesPayment->balance         = $financesPayment->due_amount - $financesPayment->paid_amount;
            $financesPayment->paid_on_date    = now();
            $financesPayment->paid_by         = Auth::id();
            $financesPayment->save();

            // === Update Main Finance Record ===
            $finance->paid_amount   += $pay_amount;
            $finance->next_due_amt   = max(0, $finance->next_due_amt - $pay_amount);
            $finance->status         = $finance->next_due_amt <= 0 ? 'Paid' : 'Pending';
           
            $finance->save();


            PaymentManagement::create([
                'payment_method'   => 'netTerm',
                'payment_mode'     => 'NetTerm',
                'amount'           => $pay_amount,
                'order_id'         => $finance->id,
                'customer_id'      => Auth::id(),
                'status'           => 'Success',
                'payment_date'     => now(),
                'created_by'       => Auth::id(),

            ]);

            // All good!
            return response()->json([
                'success' => true,
                'message' => 'Payment processed successfully.',
                'data' => [
                    'finance_id'      => $finance->id,
                    'paid_amount'     => $pay_amount,
                    'total_paid'      => $financesPayment->paid_amount,                    
                    'balance'         => $financesPayment->balance,
                    'status'          => $finance->status,
                ]
            ]);
        });
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
                'order_number' => $finance->order_number,
                'customer_id' => $finance->customer_id,
                'due_date' => $finance->due_date,
                'due_amount' => $finance->due_amount,
                'paid_on_date' =>  $finance->paid_on_date,
                'paid_amount' => $finance->paid_amount,
                'balance' => $finance->balance,
                'creditTerms' => $finance->creditTerms,
                'payment_mode' => $finance->payment_mode,
                'status' =>$finance->balance <= 0 ? 'Paid' : 'Un-Paid',
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
   
    /**
     * @OA\Get(
     *     path="/api/finances/get-full-due/{id}/{customer_id}",
     *     summary="Get full net term due amount and due date",
     *     tags={"Finance"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Finance ID",
     *         @OA\Schema(type="integer", example=12)
     *     ),
     *
     *     @OA\Parameter(
     *         name="customer_id",
     *         in="path",
     *         required=true,
     *         description="Customer ID",
     *         @OA\Schema(type="integer", example=101)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Due details fetched",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Finance due details fetched."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=12),
     *                 @OA\Property(property="customer_id", type="integer", example=101),
     *                 @OA\Property(property="due_amount", type="number", example=250.00),
     *                 @OA\Property(property="due_date", type="string", example="15-12-2025")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=404, description="Finance not found")
     * )
     */

    public function getFullNetTermDue(Request $request, $id, $customer_id)
    {   
        // Validate route parameters
        $validator = Validator::make(
            ['id' => $id, 'customer_id' => $customer_id],
            [
                'id' => 'required|exists:finances,id',
                'customer_id' => 'required|exists:customers,id'
            ]
        );

        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Fetch finance
        $finance = Finance::where('id', $id)
            ->where('customer_id', $customer_id)
            ->first();

        if (!$finance) {
            return response()->json([
                'success' => false,
                'message' => 'Finance record not found'
            ], 404);
        }

        // If due exists
        $next_due_amt = (float) $finance->next_due_amt;
        if (!empty($next_due_amt) && $next_due_amt>0) {
        
            return response()->json([
                'success' => true,
                'message' => 'Finance due details fetched.',
                'data' => [
                    'id'          => $finance->id,
                    'customer_id' => $finance->customer_id,
                    'due_amount'  => $finance->next_due_amt,
                    'due_date'    => $finance->next_due_date
                        ? date('d-m-Y', strtotime($finance->next_due_date))
                        : null,
                ]
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Finance has no due amount.',
            'data'=>null
        ], 200);
    }


    /**
     * @OA\Post(
     *     path="/api/finances/pay-full-payment/{id}",
     *     summary="Pay full Net Term outstanding amount",
     *     tags={"Finance"},
     *     security={{"bearerAuth":{}}},
     *  @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Finance ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"id", "customer_id", "pay_amount"},
     *             @OA\Property(
     *                 property="customer_id",
     *                 type="integer",
     *                 example=101,
     *                 description="Customer ID"
     *             ),
     *
     *             @OA\Property(
     *                 property="pay_amount",
     *                 type="number",
     *                 format="float",
     *                 example=250.00,
     *                 description="Full payment amount"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Full payment completed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Full payment processed successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="finance_id", type="integer", example=12),
     *                 @OA\Property(property="paid_amount", type="number", format="float", example=500.00),
     *                 @OA\Property(property="remaining_due", type="number", format="float", example=0.00),
     *                 @OA\Property(property="status", type="string", example="Paid")
     *             )
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
     *         description="Finance not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Finance record not found")
     *         )
     *     )
     * )
     */

    public function payfullNetTerm(Request $request,$id)
    {   
        $request->validate([
            'pay_amount' => 'required|numeric|min:0.01',
            'customer_id' => 'required|exists:customers,id'
             
        ]);

        $pay_amount = (float) $request->pay_amount;
        return DB::transaction(function () use ($pay_amount, $request) {

            // Lock the main finance record
            $finance = Finance::where('id', $request->id)->where('customer_id', $request->customer_id)
                ->lockForUpdate()
                ->orderByDesc('id')
                ->firstOrFail();

            // Get all UNPAID or PARTIALLY PAID invoices (oldest first = FIFO)
            $pendingPayments = FinancesPayment::where('finances_id', $finance->id)
                ->where('finances_id', $request->id)
                ->where('customer_id', $request->customer_id)
                ->whereRaw('due_amount > paid_amount') // Only unpaid/partially paid
                ->orderBy('due_date', 'asc') // Oldest first
                ->lockForUpdate() // CRITICAL: prevent race conditions
                ->get();
            if ($pendingPayments->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No outstanding balance to pay.'
                ], 400);
            }

            $remainingPayment = $pay_amount;
            $totalPaidBefore = $finance->paid_amount ?? 0;
            $invoicesPaid = [];

            foreach ($pendingPayments as $payment) {
                if ($remainingPayment <= 0) break;

                $remainingBalance = $payment->due_amount - $payment->paid_amount;

                // How much to apply to this invoice
                $applyAmount = min($remainingPayment, $remainingBalance);

                // Update this invoice
                $payment->paid_amount    += $applyAmount;
                $payment->balance         = $payment->due_amount - $payment->paid_amount;
                $payment->paid_on_date    = now();
                $payment->paid_by         = Auth::id();
                // $payment->status          = $payment->balance <= 0 ? 'Paid' : 'Pending';
                $payment->save();

                $remainingPayment -= $applyAmount;
            }

            // Update main finance record
            $finance->paid_amount   += ($pay_amount - $remainingPayment); // Only what was actually used
            $finance->next_due_amt    = max(0, $finance->next_due_amt - ($pay_amount - $remainingPayment));
            $finance->status          = $finance->next_due_amt <= 0 ? 'Paid' : 'Pending';
            $finance->next_due_date = $finance->next_due_amt <= 0 ? null : $finance->next_due_date;
            //  if($finance->next_due_amt <= 0){            
            //     $finance->used_credit_amount = null;
            //     $finance->available_credit_amount = null;
            //     $finance->next_due_date = null;
            //     $finance->next_due_amt = null;
            //     $finance->paid_amount = null;
            //     $finance->approved_amount = null;
            //     $finance->approval_date = null;
            //     $finance->approvalBy = null;
            // }
            $finance->save();

            // Record in payment history
            PaymentManagement::create([
                'payment_method' => 'netTerm',
                'payment_mode'   => 'NetTerm',
                'amount'         => $pay_amount,
                'order_id'       => $finance->id,
                'customer_id'    => $request->customer_id,
                'status'         => 'Success',
                'payment_date'   => now(),
                'created_by'     => Auth::id(),
                'note'           => $remainingPayment > 0 ? 'Partial payment applied (overpaid: ' . $remainingPayment . ')' : 'Full allocation',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment processed successfully.',
                'data' => [
                    'finance_id'        => $finance->id,
                    'amount_paid'       => $pay_amount,
                    'amount_applied'    => $pay_amount - $remainingPayment,
                    'overpaid_amount'   => $remainingPayment > 0 ? $remainingPayment : 0,
                    'remaining_due'     => $finance->next_due_amt,
                    'status'            => $finance->status,
                    'invoices_updated'  => $invoicesPaid,
                ]
            ]);
        });
    }
}
