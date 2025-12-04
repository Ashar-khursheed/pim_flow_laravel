<?php

namespace App\Http\Controllers\FrontEnd;

use Illuminate\Http\Request;

use App\Models\FrontEnd\Finance;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use OpenApi\Annotations as OA;
use App\Http\Controllers\Controller;
use App\Models\FrontEnd\FinancesPayment;

class FinanceController extends Controller
{

    /**
     * @OA\Post(
     *     path="/api/frontend/finances",
     *     summary="Create a new finance record",
     *     tags={"Frontend Finance"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"term_selection", "requested_amount","customer_address_id"},  
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
    { //dd($request->all());
        $validator = Validator::make($request->all(), [

            'payment_options' => 'nullable|string',
            'term_selection' => 'required|string|in:Net 30 Days,Net 45 Days,Net 60 Days',
            'requested_amount' => 'required|numeric',
            'legal_business_name' => 'nullable|string',
            'doing_business' => 'nullable|string',            
            'documents' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png,webp,svg|max:10240',
            'type_of_business' => 'nullable|string|max:255',
            'accounts_payable_email' => 'required|email|string|max:255',
            'accounts_payable_phone' => 'required|string|max:255',
            'customer_address_id' => 'required|numeric',
            'annual_revenue' => 'nullable|string',
            'years_in_business' => 'nullable|string',
            'duns_number' => 'nullable|string',
            'role_at_business' => 'nullable|string',
            'customer_id' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        $customer_id = Auth::id();
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
     *     path="/api/frontend/finances/apply",
     *     summary="Check finance application by customer ID",
     *     tags={"Frontend Finance"},
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
     *         name="orderAmount",
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
            'orderAmount' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $customerId = $request->customer_id;
        $orderAmount = $request->orderAmount;
        $finance = Finance::where('customer_id', $customerId)
            ->where('accountsStatus', 'Approved')
            ->orderBy('id', 'desc')
            ->first();
        if (!$finance) {
            return response()->json([
                'success' => false,
                'message' => 'Net Term finance is either not approved or not active'
            ], 422);
        }

        $orderCredit = $orderAmount + $finance->used_credit_amount;

        if ($orderCredit > $finance->approved_amount) {

            return response()->json([
                'success' => false,
                'message' => "The order amount (" . number_format($orderCredit, 2) . ") is less than the approved amount (" . number_format($finance->approved_amount, 2) . ").",
            ], 422);
        }
        $data = array(
            'credit_limit_amount' => $finance->approved_amount,
        );
        return response()->json([
            'success' => true,
            'message' => 'Finance record retrieved successfully.',
            'data' => $data
        ], 200);
    }


    /**
     * @OA\Get(
     *     path="/api/frontend/finances/check",
     *     summary="Check finance application by customer ID",
     *     tags={"Frontend Finance"},
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

        $finance = Finance::where('customer_id', $customerId)
            ->orderBy('id', 'desc')
            ->first();
        if (!$finance) {
            return response()->json([
                'success' => false,
                'message' => 'Net Term finance is either not approved or not active'
            ], 422);
        }
        if ($finance) {
            return response()->json([
                'success' => true,
                'message' => 'Finance status successfully.',
                'accoutsStatus' => $finance->accountsStatus
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Finance not found.',
                'accoutsStatus' => null
            ], 200);
        }
    }
}
