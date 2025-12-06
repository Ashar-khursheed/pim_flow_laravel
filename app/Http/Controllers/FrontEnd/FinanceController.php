<?php

namespace App\Http\Controllers\FrontEnd;

use Illuminate\Http\Request;

use App\Models\FrontEnd\Finance;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use OpenApi\Annotations as OA;
use App\Http\Controllers\Controller;
use App\Models\PaymentManagement;
use App\Models\FrontEnd\FinancesPayment;
use App\Models\FrontEnd\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Bus;
use Illuminate\Bus\Batch;

use App\Jobs\NetTermMailJob;

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
            'requested_amount' => 'required|numeric',
            'legal_business_name' => 'required|string',
            'doing_business' => 'nullable|string',
            'documents' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png,webp,svg|max:10240',
            'type_of_business' => 'required|string|max:255',
            'accounts_payable_email' => 'required|email|string|max:255',
            'accounts_payable_phone' => 'required|string|max:255',
            'customer_address_id' => 'required|numeric',
            'annual_revenue' => 'required|string',
            'years_in_business' => 'required|string',
            'duns_number' => 'nullable|string',
            'role_at_business' => 'required|string'
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
        $data['business_address'] = $request->address;


        if ($request->hasFile('documents')) {
            $data['documents'] = uploadImageToWebpS3FromFile(
                $request,
                'documents',
                env('STORAGE_ENV') . '/documents'
            );
        } else {
            $data['documents'] = null;
        }

        $finance = Finance::updateOrCreate(
            ['customer_id' => $customer_id],
            $data
        );

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
        $customer_id = auth()->id(); // get logged-in customer

        $finances = Finance::where('customer_id', $customer_id)
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Finance records loaded successfully.',
            'data' => $finances
        ], 200);
    }




    /**
     * @OA\Get(
     *     path="/api/frontend/finances/apply",
     *     summary="Check finance application by customer ID",
     *     tags={"Frontend-Finance"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="customer_id",
     *         in="query",
     *         required=true,
     *         description="Customer ID to fetch finance details",
     *         @OA\Schema(type="integer", example=19)
     *     ),
     *
     *     @OA\Parameter(
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
            'customer_id' => 'required|exists:customers,id',
            'order_amount' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $customerId = $request->customer_id;
        $orderAmount = $request->order_amount;
        $finance = Finance::where('customer_id', $customerId)
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
        if ($request->order_amount > !empty($finance->available_credit_amount)) {

            return response()->json([
                'success' => false,
                'message' => "Offer Split Payment Option Or Force Card Payment Only",
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
     * @OA\Get(
     *     path="/api/frontend/finances/check",
     *     summary="Check finance application by customer ID",
     *     tags={"Frontend-Finance"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="customer_id",
     *         in="query",
     *         required=true,
     *         description="Customer ID to fetch finance details",
     *         @OA\Schema(type="integer", example=19)
     *     ),
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
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $customerId = $request->customer_id;
        $customer_id = Auth::id();

        if ($customer_id != $customerId) {
            return response()->json([
                'success' => false,
                'message' => 'Customer verification failed: customer ID does not match the finance record'
            ], 422);
        }
        $finance = Finance::where('customer_id', $customerId)
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
     *     @OA\Parameter(
     *         name="customer_id",
     *         in="query",
     *         required=true,
     *         description="Customer ID to fetch finance details",
     *         @OA\Schema(type="integer", example=19)
     *     ),
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

    public function getCustomerDetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        $customerId = $request->customer_id;

        // Logged-in user id
        $loggedInCustomerId = Auth::id();

        // Check if same customer
        if ($loggedInCustomerId != $customerId) {
            return response()->json([
                'success' => false,
                'message' => 'Customer verification failed: customer ID does not match'
            ], 422);
        }
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
     *
     *     @OA\Parameter(
     *         name="customer_id",
     *         in="query",
     *         required=true,
     *         description="Customer ID to fetch finance details",
     *         @OA\Schema(type="integer", example=19)
     *     ),
     *
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
     *             )
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
    $validator = Validator::make($request->all(), [
        // 'customer_id'    => 'required|integer|exists:customers,id',
        'order_amount'   => 'required|numeric|min:0.01',
        'term_selection' => 'required|in:Net 30 Days,Net 45 Days,Net 60 Days',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors'  => $validator->errors()
        ], 422);
    }

    $customerId     = (int) $request->customer_id;
    $orderAmount    = (float) $request->order_amount;
    $termSelection  = $request->term_selection;

    // Security: Ensure user owns this customer ID
    if (auth()->id() !== $customerId) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized: You can only place orders for your own account.'
        ], 403);
    }

    return DB::transaction(function () use ($customerId, $orderAmount, $termSelection) {

        // Lock the finance record to prevent race conditions
        $finance = Finance::where('customer_id', Auth::id())
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

        // Calculate current credit usage
        $usedCredit        = (float) ($finance->used_credit_amount ?? 0);
        $availableCredit   = $finance->approved_amount - $usedCredit;

        if ($orderAmount > $availableCredit) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient credit limit. '
                    . 'Requested: ' . number_format($orderAmount, 2)
                    . ', Available: ' . number_format($availableCredit, 2)
            ], 422);
        }

        // Calculate due date from TODAY
        $days = match ($termSelection) {
            'Net 30 Days' => 30,
            'Net 45 Days' => 45,
            'Net 60 Days' => 60,
            default       => 30,
        };

        $dueDate = now()->addDays($days)->format('Y-m-d');

        // Update Finance Record
        $finance->used_credit_amount        += $orderAmount;
        $finance->available_credit_amount    = $finance->approved_amount - $finance->used_credit_amount;
        $finance->status                     = 'Pending';
        $finance->term_selection             = $termSelection;

        // Update next due amount & date
        $finance->next_due_amt              += $orderAmount;

        // Keep the EARLIEST due date (important for multiple orders)
        if (!$finance->next_due_date || $finance->next_due_date > $dueDate) {
            $finance->next_due_date = $dueDate;
        }

        $finance->save();

        // Create invoice record (FinancesPayment)
        FinancesPayment::create([
            'finances_id'   => $finance->id,
            'customer_id'   => $customerId,
            'due_amount'    => $orderAmount,
            'paid_amount'   => 0,
            'balance'       => $orderAmount,
            'due_date'      => $dueDate,
            'status'        => 'Pending',
            'created_by'    => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Order placed successfully on Net Terms!',
            'data'    => [
                'order_amount'        => $orderAmount,
                'credit_used'         => $finance->used_credit_amount,
                'available_credit'    => $finance->available_credit_amount,
                'next_due_date'       => $finance->next_due_date,
                'next_due_amount'     => $finance->next_due_amt,
                'term'                => $termSelection,
            ]
        ], 200);
    });
}
   
   
    // public function financeOrder(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'customer_id' => 'required|exists:customers,id',
    //         'order_amount' => 'required|numeric',
    //         'term_selection' => 'required|string|in:Net 30 Days,Net 45 Days,Net 60 Days',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Validation failed',
    //             'errors' => $validator->errors()
    //         ], 422);
    //     }
    //     $customerId = $request->customer_id;
    //     $orderAmount = $request->order_amount;
    //     $finance = Finance::where('customer_id', $customerId)
    //         ->where('accounts_status', 'Approved')
    //         ->orderBy('id', 'desc')
    //         ->first();
    //     if (!$finance) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Net Term finance is either not approved or is currently inactive'
    //         ], 422);
    //     }

    //     if (!$finance->approved_amount) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Your request amount is not approved.'
    //         ], 422);
    //     }
    //     if (!$finance->credit_limit_amount) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Your credit limit amount has not been approved.'
    //         ], 422);
    //     }


    //     if (auth()->id() != $customerId) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Customer verification failed: customer ID does not match the finance record'
    //         ], 422);
    //     }


    //     if ($request->order_amount > $finance->approved_amount) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => "The order amount (" . number_format($request->order_amount, 2) . ") is less than the approved amount (" . number_format($finance->approved_amount, 2) . ").",
    //         ], 422);
    //     }


    //     if ($finance->used_credit_amount > 0) {
    //         if ($request->order_amount > $finance->available_credit_amount) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => "The order amount (" . number_format($request->order_amount, 2) . ") is less than the available credit amount (" . number_format($finance->available_credit_amount, 2) . ").",
    //             ], 422);
    //         }
    //     }
    //     $used_credit_amount = $finance->used_credit_amount + $request->order_amount;
    //     if ($used_credit_amount > $finance->approved_amount) {

    //         return response()->json([
    //             'success' => false,
    //             'message' => "The order amount (" . number_format($request->order_amount, 2) . ") is less than the user credit amount (" . number_format($used_credit_amount, 2) . ").",
    //         ], 422);
    //     }

    //     if ($finance->approved_amount == $request->order_amount) {
    //         if ($finance->used_credit_amount > 0 && $finance->available_credit_amount > 0) {
    //             $finance->used_credit_amount = $finance->used_credit_amount +  $request->order_amount;
    //             $finance->available_credit_amount = $finance->available_credit_amount -  $request->order_amount;
    //             $finance->status = "Pending";
    //             $due = $this->getDueDays($finance->term_selection);
    //             if ($due) {
    //                 $finance->next_due_date = date('Y-m-d', strtotime($due));
    //                 $finance->next_due_amt = $finance->next_due_amt + $request->order_amount;
    //                 $this->payFinancesPayment($finance->id, $finance->customer_id, $request->order_amount, date('Y-m-d', strtotime($due)));
    //             }
    //         } else if ($finance->used_credit_amount == '0.00') {

    //             $finance->used_credit_amount = $finance->used_credit_amount +  $request->order_amount;
    //             $finance->available_credit_amount = $finance->approved_amount -  $request->order_amount;
    //             $finance->status = "Pending";
    //             $nextPaymentDue = "";
    //             $due = $this->getDueDays($finance->term_selection);
    //             if ($due) {
    //                 $finance->next_due_date = date('Y-m-d', strtotime($due));
    //                 $finance->next_due_amt = $finance->next_due_amt + $request->order_amount;
    //                 $this->payFinancesPayment($finance->id, $finance->customer_id, $request->order_amount, date('Y-m-d', strtotime($due)));
    //             }
    //         }
    //     }

    //     if ($finance->approved_amount > $request->order_amount) {

    //         if ($finance->used_credit_amount > 0 && $finance->available_credit_amount >= $request->order_amount) {
    //             $finance->used_credit_amount = $finance->used_credit_amount +  $request->order_amount;

    //             $finance->available_credit_amount = $finance->available_credit_amount -  $request->order_amount;

    //             $finance->status = "Pending";

    //             $due = $this->getDueDays($finance->term_selection);
    //             if ($due) {
    //                 $finance->next_due_date = date('Y-m-d', strtotime($due));
    //                 $finance->next_due_amt = $finance->next_due_amt + $request->order_amount;
    //                 $this->payFinancesPayment($finance->id, $finance->customer_id, $request->order_amount, date('Y-m-d', strtotime($due)));
    //             }
    //         } else if ($finance->used_credit_amount == '0.00') {

    //             $finance->used_credit_amount = $finance->used_credit_amount +  $request->order_amount;

    //             $finance->available_credit_amount = $finance->approved_amount -  $request->order_amount;
    //             $finance->status = "Pending";
    //             $nextPaymentDue = "";
    //             $due = $this->getDueDays($finance->term_selection);
    //             if ($due) {
    //                 $finance->next_due_date = date('Y-m-d', strtotime($due));
    //                 $finance->next_due_amt = $finance->next_due_amt + $request->order_amount;
    //                 $this->payFinancesPayment($finance->id, $finance->customer_id, $request->order_amount, date('Y-m-d', strtotime($due)));
    //             }
    //         }
    //     }



    //     if ($finance->save()) {
    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Your order has been successfully placed.',

    //         ], 200);
    //     } else {
    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Your order has not been successfully placed.',

    //         ], 200);
    //     }
    // }

    private function getDueDays($term)
    {
        return match ($term) {
            'Net 30 Days' => '+30 Days',
            'Net 45 Days' => '+45 Days',
            'Net 60 Days' => '+60 Days'
        };
    }
    private function payFinancesPayment($finance_id, $customer_id, $order_amount, $due)
    {
        $data = [
            'finances_id'  => $finance_id,
            'customer_id' => trim($customer_id),
            'due_amount'      => $order_amount,
            'due_date'      => $due,
        ];
        FinancesPayment::create($data);
    }

    /**
     * @OA\Get(
     *     path="/api/frontend/finances/{id}/payment-history",
     *     summary="Get payment history",
     *     tags={"Frontend-Finance"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Customer ID",
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
        $paymentHistory = FinancesPayment::where('customer_id', $id)
            ->with(['paidByUser', 'updatedBy'])
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


    /**
     * @OA\Get(
     *     path="/api/frontend/finances/{id}/due",
     *     summary="Get finance due amount and due date",
     *     tags={"Frontend-Finance"},
     *     security={{"bearerAuth":{}}},

     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Finance ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),

     *     @OA\Parameter(
     *         name="customer_id",
     *         in="query",
     *         description="Customer ID",
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

    public function getDueDetails(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        $finance = Finance::where('id', $id)->where('customer_id', $request->customer_id)->orderBy('id', 'desc')->first();

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
     *     path="/api/frontend/finances/pay/{id}",
     *     summary="Pay finance amount",
     *     tags={"Frontend-Finance"},
     *     security={{"bearerAuth":{}}},
     * @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="payment id",
     *         required=true,
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"customer_id", "pay_amount"},             
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
        'pay_amount' => 'required|numeric|min:0.01',
    ]);

    $pay_amount = (float) $request->pay_amount;  

    return DB::transaction(function () use ($id, $pay_amount) {

       
        $financesPayment = FinancesPayment::where('id', $id)
            ->where('customer_id', Auth::id())
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
                'remaining_due'   => $finance->next_due_amt,
                'balance'         => $financesPayment->balance,
                'status'          => $finance->status,
            ]
        ]);
    });
    }
}
