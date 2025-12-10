<?php

namespace App\Http\Controllers\FrontEnd;

use Illuminate\Http\Request;

use App\Models\FrontEnd\Finance;
use App\Models\FrontEnd\FinanceHistory;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use OpenApi\Annotations as OA;
use App\Http\Controllers\Controller;
use App\Models\PaymentManagement;
use App\Models\FrontEnd\FinancesPayment;
use App\Models\FrontEnd\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Bus;
use App\Models\FrontEnd\Order;
use App\Models\FrontEnd\Invoice;
use Illuminate\Bus\Batch;
use Illuminate\Validation\Rule;

use App\Jobs\NetTerm\NetTermMailJob;

class FinanceController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/frontend/finances",
     *     summary="Create a new finance record",
     *     tags={"Frontend-Finance"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"term_selection", "requested_amount","customer_address_id","type_of_business","accounts_payable_email","accounts_payable_phone","annual_revenue","legal_business_name","years_in_business"},
     *                 @OA\Property(property="payment_options", type="string", example="netTerm", description="Payment option netTerm"),
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
     *                 @OA\Property(property="annual_revenue", type="string", enum={"Less then 1,000,000","1,000,000 to 2,000,000","2,000,000 to 5,000,000","5,000,000 to 25,000,000","More than 25,000,000"}, example="Less then 1,000,000"),
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
     *                 @OA\Property(property="duns_number", type="string", example="123456789")
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

            'payment_options' => 'nullable|string',
            'term_selection' => 'required|string|in:Net 30 Days,Net 45 Days,Net 60 Days',
            'requested_amount' => 'required|numeric|min:0.01|max:999999999999',
            'legal_business_name' => 'required|string',
            'doing_business' => 'nullable|string',
            'documents' => 'nullable|file|mimes:pdf|max:10240',
            'accounts_payable_email' => 'required|email|string|max:255',
            'accounts_payable_phone' => 'required|string|max:255',
            'customer_address_id' => 'required|numeric',
            'duns_number' => 'nullable|string',
            'role_at_business' => 'required|string',
            'type_of_business' => 'nullable|string',
            'years_in_business' => 'nullable|string',
            'annual_revenue' => 'nullable|string'

        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        $customer_id = Auth::id();


        // Check last finance
        $lastFinance = Finance::where('customer_id', $customer_id)
            ->orderBy('id', 'desc')
            ->first();

        // If previous finance is not Paid + Approved → stop
        if ($lastFinance && !($lastFinance->status === "Paid" && $lastFinance->accounts_status === "Approved")) {
            return response()->json([
                'success' => false,
                'message' => 'Finance cannot be created. Previous net term is Pending, Rejected, or Overdue.'
            ], 422);
        }
        $data = $validator->validated();
        $data['customer_id'] = Auth::id();
        $data['created_by'] = '0';
        $data['updated_by'] = '0';
        $data['accounts_status'] = 'Pending';
        $data['status'] = 'Pending';

        $data['business_address'] = $request->address;

        if ($request->hasFile('documents')) {

            $data['documents'] = uploadPdfToS3FromFile(
                $request,
                'documents',
                env('STORAGE_ENV') . '/customer/payment'
            );
        } else {
            $data['documents'] = null;
        }
        $finance = Finance::create($data);

        $batch = Bus::batch([])->name("Net Terms Application")->dispatch();
        $batch->options['queue'] = config('app.website') . '_NET_TRM';
        $batch->add(new NetTermMailJob([
            'recordId' => $finance->id
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Application submitted successfully.',
            'data' => $finance
        ], 201);
    }


    /**
     * @OA\Get(
     *     path="/api/frontend/finances",
     *     summary="Get all finance records of logged-in customer",
     *     tags={"Frontend-Finance"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Finance records fetched successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Finance records loaded successfully."),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function index()
    {
        $customer_id = Auth::id(); // or Auth::guard('api')->id()

        $finance = Finance::where('customer_id', $customer_id)
            ->orderBy('id', 'desc')
            ->first();

        if (!$finance) {
            return response()->json([
                'success' => false,
                'message' => 'No finance record found.',
                'data' => null
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Finance record loaded successfully.',
            'data' => $finance
        ], 200);
    }




    /**
     * @OA\Get(
     *     path="/api/frontend/finances/apply",
     *     summary="Check finance application by customer ID",
     *     tags={"Frontend-Finance"},
     *     security={{"bearerAuth":{}}},
     *    @OA\Parameter(
     *         name="order_amount",
     *         in="query",
     *         required=true,
     *         description="Order Amount for validating finance",
     *         @OA\Schema(type="number", example=300)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Finance record retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Finance record retrieved successfully."),
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
     *     )
     * )
     */


    public function getFinance(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_amount' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }


        $finance = Finance::where('customer_id', Auth::id())
            ->where('accounts_status', 'Approved')
            ->orderBy('id', 'desc')
            ->first();
        if (!$finance) {
            return response()->json([
                'success' => false,
                'message' => 'Net Term finance is either not approved or is currently inactive'
            ], 422);
        }
        if (!$finance->approved_amount) {
            return response()->json([
                'success' => false,
                'message' => 'Your request amount has not been approved.'
            ], 422);
        }

        if ($request->order_amount > $finance->approved_amount) {
            return response()->json([
                'success' => false,
                'message' => "The order amount (" . number_format($request->order_amount, 2) . ") is less than the approved amount (" . number_format($finance->approved_amount, 2) . ").",
            ], 422);
        }



        $data = array(
            'approved_amount' => $finance->approved_amount,
            'credit_limit_amount' => $finance->credit_limit_amount,
            'requested_amount' => $finance->requested_amount,
            'used_credit_amount' => $finance->used_credit_amount,
            'available_credit_amount' => $finance->available_credit_amount,
        );
        return response()->json([
            'success' => true,
            'message' => 'Net Term record retrieved successfully.',
            'data' => $data
        ], 200);
    }
    /**
     * @OA\Put(
     *     path="/api/frontend/finances/{id}/updateCreditAmount",
     *     summary="Update finance credit amounts",
     *     tags={"Frontend-Finance"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Finance ID",
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"used_credit_amount", "available_credit_amount"},
     *
     *                 @OA\Property(
     *                     property="used_credit_amount",
     *                     type="number",
     *                     description="Used credit amount",
     *                     example=300
     *                 ),
     *
     *                 @OA\Property(
     *                     property="available_credit_amount",
     *                     type="number",
     *                     description="Available credit amount",
     *                     example=500
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Credit amounts updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Credit amounts updated successfully."),
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
     *     )
     * )
     */


    public function updateCreditAmount(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'used_credit_amount' => 'required|numeric',
            'available_credit_amount' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Remove dd() and fix query
        $finance = Finance::where('id', $id)
            ->where('customer_id', Auth::id())
            ->first();

        if (!$finance) {
            return response()->json([
                'success' => false,
                'message' => 'Finance record not found'
            ], 404);
        }

        $finance->available_credit_amount = $request->available_credit_amount;
        $finance->used_credit_amount = $request->used_credit_amount;

        if ($finance->save()) {
            return response()->json([
                'success' => true,
                'message' => 'Credit amounts updated successfully.',
                'data' => $finance
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to update credit amounts.'
        ], 500);
    }



    /**
     * @OA\Get(
     *     path="/api/frontend/finances/check",
     *     summary="Check finance application by customer ID",
     *     tags={"Frontend-Finance"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Finance record retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Finance record retrieved successfully."),
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
     *     )
     * )
     */


    public function financeCheck(Request $request)
    {
        $customer_id = Auth::id();
        $finance = Finance::where('customer_id', $customer_id)
            ->orderBy('id', 'desc')
            ->first();
        if (!$finance) {
            return response()->json([
                'success' => false,
                'message' => 'Net Term finance is either not approved or is currently inactive'
            ], 422);
        }


        if ($finance) {
            return response()->json([
                'success' => true,
                'message' => 'Net Term status fetch successfully..',
                'accouts_status' => $finance->accounts_status
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Net term record not found.',
                'accoutsStatus' => null
            ], 200);
        }
    }


    /**
     * @OA\Get(
     *     path="/api/frontend/finances/get-customer-details",
     *     summary="Check finance application by customer ID",
     *     tags={"Frontend-Finance"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Finance record retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Finance record retrieved successfully."),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */

    public function getCustomerDetails(Request $request)
    {
        // Logged-in user id
        $customerId = Auth::id();
        $finance = Finance::with([
            'customer',
            'createdBy',
            'updatedBy',
            'customerAddress',
            'approvalUser'
        ])->where('customer_id', $customerId)->orderBy('id', 'desc')->first();

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
     *     path="/api/frontend/finances/order",
     *     summary="Check finance application by customer ID",
     *     tags={"Frontend-Finance"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="order_amount",
     *                 type="number",
     *                 example=300,
     *                 description="Order amount for validating finance"
     *             ),
     *             @OA\Property(
     *                 property="term_selection",
     *                 type="string",
     *                 enum={"Net 30 Days","Net 45 Days","Net 60 Days"},
     *                 example="Net 30 Days",
     *                 description="Net Term selection"
     *             ),
     *             @OA\Property(
     *                 property="order_number",
     *                 type="string",                
     *                 example="575",
     *                 description="order number"
     *             ),
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Finance record retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Finance record retrieved successfully."),
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
     *     )
     * )
     */
     
    public function financeOrder(Request $request)
    {
        // Step 1: Validate request
        $validator = Validator::make($request->all(), [
            'order_amount'   => 'required|numeric|min:0.01',
            'term_selection' => 'required|in:Net 30 Days,Net 45 Days,Net 60 Days',
            'order_number'   => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        $customerId    = auth()->id();
        $orderAmount   = (float) $request->order_amount;
        $termSelection = $request->term_selection;

        // Step 2: Mark overdue finance records
        $today = now()->toDateString();
        Finance::where('customer_id', $customerId)
            ->where('status', 'Pending')
            ->whereDate('next_due_date', '<', $today)
            ->update(['status' => 'Overdue']);

        // Step 3: Transaction to prevent double writes
        return DB::transaction(function () use ($customerId, $orderAmount, $termSelection, $request) {

            // Lock the finance record
            $finance = Finance::where('customer_id', $customerId)
                ->where('accounts_status', 'Approved')
                ->lockForUpdate()
                ->orderByDesc('id')
                ->first();

            if (!$finance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Net Term credit is not active or approved.'
                ], 422);
            }

            if (!$finance->approved_amount || $finance->approved_amount <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Approved credit limit is missing or zero.'
                ], 422);
            }

         
            $usedCredit      = (float)($finance->used_credit_amount ?? 0);
            $approvedAmount  = (float)($finance->approved_amount ?? 0);
            $availableCredit = $approvedAmount - $usedCredit;

            // Proper numeric comparison
            if ($orderAmount > $availableCredit) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient credit limit. '
                        . 'Requested: ' . $orderAmount
                        . ', Available: ' . $availableCredit
                ], 422);
            }

           
            $days = match ($termSelection) {
                'Net 30 Days' => 30,
                'Net 45 Days' => 45,
                'Net 60 Days' => 60
            };

            $dueDate = now()->addDays($days)->format('Y-m-d');

          
            $finance->used_credit_amount       += $orderAmount;
            $finance->available_credit_amount   = $finance->approved_amount - $finance->used_credit_amount;
            $finance->status                    = 'Pending';
            $finance->term_selection            = $termSelection;           
            $finance->next_due_amt              += $orderAmount;

            // Keep earliest due date for multiple orders
            if (!$finance->next_due_date || $finance->next_due_date > $dueDate) {
                $finance->next_due_date = $dueDate;
            }

            $finance->save();

            
            FinancesPayment::create([
                'order_number'  => $request->order_number,
                'finances_id'   => $finance->id,
                'customer_id'   => $customerId,
                'due_amount'    => $orderAmount,
                'paid_amount'   => 0,
                'balance'       => $orderAmount,
                'due_date'      => $dueDate,
                'created_by'    => $customerId,
            ]);

           
            return response()->json([
                'success' => true,
                'message' => 'Order placed successfully on Net Terms!',
                'data'    => [
                    'order_amount'       => $orderAmount,
                    'credit_used'        => $finance->used_credit_amount,
                    'available_credit'   => $finance->available_credit_amount,
                    'next_due_date'      => $finance->next_due_date,
                    'next_due_amount'    => $finance->next_due_amt,
                    'term'               => $termSelection,
                ]
            ], 200);
        });
    }


    /**
     * @OA\Get(
     *     path="/api/frontend/finances/payment-order-history",
     *     summary="Get payment history",
     *     tags={"Frontend-Finance"},
     *     security={{"bearerAuth":{}}},     
     *     @OA\Response(
     *         response=200,
     *         description="Payment history fetched",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="invoice_id", type="integer"),
     *                 @OA\Property(property="order_id", type="integer"),
     *                 @OA\Property(property="due_date", type="string"),
     *                 @OA\Property(property="due_amount", type="number", format="float"),
     *                 @OA\Property(property="status", type="string", example="Paid/Un-Paid")
     *             )),
     *             @OA\Property(property="total_pages", type="integer"),
     *             @OA\Property(property="total_records", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=404, description="No payment history found")
     * )
     */
    public function getPaymentOrderHistory(Request $request)
    {
        $customer_id = Auth::id();

        // Pagination
        $page  = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 10);

        $paymentQuery = FinancesPayment::where('customer_id', $customer_id)
            ->where('balance', '>', 0)
            ->orderBy('id', 'desc');

        $totalRecords = $paymentQuery->count();
        $totalPages   = ceil($totalRecords / $limit);

        $paymentHistory = $paymentQuery
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        if ($paymentHistory->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No payment history found.',
                'data' => [],
                'total_pages' => 0,
                'total_records' => 0
            ], 404);
        }

        $paymentData = $paymentHistory->map(function ($payment) {

            // Initialize to prevent undefined variable error
            $order   = null;
            $invoice = null;

            if ($payment->order_number) {
                $order = Order::where('order_number', $payment->order_number)->first();

                if ($order) {
                    $invoice = Invoice::where('order_id', $order->id)->first();
                }
            }

            return [
                'finance_id'      => $payment->finances_id ?? null,
                'invoice_id'      => $invoice->id ?? null,
                'invoice_number'  => $invoice->invoice_number ?? null,
                'order_id'        => $order->id ?? null,
                'order_number'    => $order->order_number ?? null,
                'due_date'        => $payment->due_date
                    ? date('d-m-Y', strtotime($payment->due_date))
                    : null,

                'status'          => ($payment->balance <= 0) ? 'Paid' : 'Un-Paid',
                'payment_method'  => ($payment->balance <= 0) ? 'NetTerm' : '',
                'due_amount'      => (float) ($payment->due_amount ?? 0),
                'paid_amount'      => (float) ($payment->paid_amount ?? 0),
                'balance'      => (float) ($payment->balance ?? 0),
                'paid_on_date'        => $payment->paid_on_date
                    ? date('d-m-Y', strtotime($payment->paid_on_date))
                    : null,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => __('msg_rec_list'),
            'data' => $paymentData,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords,
        ]);
    }
     
    /**
     * @OA\Get(
     *     path="/api/frontend/finances/payment-paid-invoice",
     *     summary="Get payment history",
     *     tags={"Frontend-Finance"},
     *     security={{"bearerAuth":{}}},     
     *     @OA\Response(
     *         response=200,
     *         description="Payment history fetched",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="invoice_id", type="integer"),
     *                 @OA\Property(property="order_id", type="integer"),
     *                 @OA\Property(property="due_date", type="string"),
     *                 @OA\Property(property="due_amount", type="number", format="float"),
     *                 @OA\Property(property="status", type="string", example="Paid/Un-Paid")
     *             )),
     *             @OA\Property(property="total_pages", type="integer"),
     *             @OA\Property(property="total_records", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=404, description="No payment history found")
     * )
     */
    
    public function getPaymentPaidInvoice(Request $request)
    {
        $customer_id = Auth::id();

        // Pagination
        $page  = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 10);

        $paymentQuery = FinancesPayment::where('customer_id', $customer_id)
            ->where('balance', '<=', 0)
            ->orderBy('id', 'desc');

        $totalRecords = $paymentQuery->count();
        $totalPages   = ceil($totalRecords / $limit);

        $paymentHistory = $paymentQuery
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        if ($paymentHistory->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No payment history found.',
                'data' => [],
                'total_pages' => 0,
                'total_records' => 0
            ], 404);
        }

        $paymentData = $paymentHistory->map(function ($payment) {

            // Initialize to prevent undefined variable error
            $order   = null;
            $invoice = null;

            if ($payment->order_number) {
                $order = Order::where('order_number', $payment->order_number)->first();

                if ($order) {
                    $invoice = Invoice::where('order_id', $order->id)->first();
                }
            }

            return [
                'invoice_id'      => $invoice->id ?? null,
                'invoice_number'  => $invoice->invoice_number ?? null,
                'order_id'        => $order->id ?? null,
                'order_number'    => $order->order_number ?? null,
                'due_date'        => $payment->due_date
                    ? date('d-m-Y', strtotime($payment->due_date))
                    : null,

                'status'          => ($payment->balance <= 0) ? 'Paid' : 'Un-Paid',
                'payment_method'  => ($payment->balance <= 0) ? 'NetTerm' : '',
                'due_amount'      => (float) ($payment->due_amount ?? 0),
                'paid_amount'      => (float) ($payment->paid_amount ?? 0),
                'balance'      => (float) ($payment->balance ?? 0),
                'paid_on_date'        => $payment->paid_on_date
                    ? date('d-m-Y', strtotime($payment->paid_on_date))
                    : null,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => __('msg_rec_list'),
            'data' => $paymentData,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/frontend/finances/payment-history",
     *     summary="Get payment history",
     *     tags={"Frontend-Finance"},
     *     security={{"bearerAuth":{}}},
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
    public function getPaymentHistory(Request $request)
    {
        $customer_id = Auth::id();

        // Pagination
        $page  = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 10);

        $paymentQuery = FinanceHistory::where('customer_id', $customer_id)
            ->orderBy('id', 'desc');

        $totalRecords = $paymentQuery->count();
        $totalPages   = ceil($totalRecords / $limit);

        $paymentHistory = $paymentQuery
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        if ($paymentHistory->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No payment history found.',
                'data' => [],
                'total_pages' => 0,
                'total_records' => 0
            ], 404);
        }

        $paymentData = $paymentHistory->map(function ($payment) {

            // Initialize to prevent undefined variable error
            $order   = null;
            $invoice = null;

            if ($payment->order_number) {
                $order = Order::where('order_number', $payment->order_number)->first();

                if ($order) {
                    $invoice = Invoice::where('order_id', $order->id)->first();
                }
            }

            return [
                'payment_id'      => $payment->payment_id ?? null,
                'invoice_id'      => $invoice->id ?? null,
                'invoice_number'  => $invoice->invoice_number ?? null,
                'order_id'        => $order->id ?? null,
                'order_number'    => $order->order_number ?? null,
                'due_date'        => $payment->due_date
                    ? date('d-m-Y', strtotime($payment->due_date))
                    : null,
                'status'          => ($payment->balance <= 0) ? 'Paid' : 'Un-Paid',
                'payment_method'  => ($payment->balance <= 0) ? 'NetTerm' : '',
                'due_amount'      => (float) ($payment->due_amount ?? 0),
                'paid_amount'      => (float) ($payment->paid_amount ?? 0),
                'balance'      => (float) ($payment->balance ?? 0),
                'paid_on_date'        => $payment->paid_on_date
                    ? date('d-m-Y', strtotime($payment->paid_on_date))
                    : null,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => __('msg_rec_list'),
            'data' => $paymentData,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords,
        ]);
    }
}
