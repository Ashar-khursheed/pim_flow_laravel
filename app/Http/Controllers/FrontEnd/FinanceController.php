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
     *                 required={"term_selection", "requestedAmount"},  
     *                 @OA\Property(property="payment_options", type="string", example="netTerm", description="Payment option netTerm"),                
     *                 @OA\Property(property="term_selection", type="string",enum={"Net 30 Days","Net 45 Days","Net 60 Days"}, example="Net 30 Days", description="Net Pay in 30/45/60 Days"),
     *                 @OA\Property(property="requestedAmount", type="number", format="float", example=5000.75, description="Enter amount"),
     *                 @OA\Property(property="documents", type="string", format="binary", description="Upload supporting document file"),
     *                 
     *                 @OA\Property(property="type_of_business", type="string", example="E-commerce", description="Type of business (Advertising / E-commerce)"),                
     *                 @OA\Property(property="accountsPayableEmail", type="string", example="pay@gmail.com", description="accountsPayableEmail"),                
     *                 @OA\Property(property="accountsPayablePhone", type="string", example="123456789", description="Accounts Payable Phone"),                
     *                 @OA\Property(property="customer_address_id", type="interger", example="23", description="customer address id"),                
     *                 @OA\Property(property="annual_revenue", type="string", example="10M USD"),
     *                 @OA\Property(property="years_in_business", type="string", example="5 – 10 years"),               
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
            'requestedAmount' => 'required|numeric',
            'documents' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png,webp,svg|max:10240',

            'type_of_business' => 'nullable|string|max:255',
            'accountsPayableEmail' => 'required|email|string|max:255',
            'accountsPayablePhone' => 'required|string|max:255',
            'customer_address_id' => 'required|numeric',
            'annual_revenue' => 'nullable|string',
            'years_in_business' => 'nullable|string',
            'duns_number' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $data['customer_id'] = Auth::id() ?? 1;
        $data['created_by'] = '0';
        $data['updated_by'] = '0';

      
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
            ->where('status', 'Active')
            ->orderBy('id', 'desc')
            ->first();
        if(!$finance){
            return response()->json([
            'success' => false,
            'message' => 'Net Term finance is either not approved or not active'
            ], 422);
            }

            $orderCredit = $orderAmount + $finance->usedCreditAmount;

				if ($orderCredit > $finance->approvedAmount) {

					return response()->json([
						'success' => false,
						'message' => "The order amount (" . number_format($orderCredit, 2) . ") is less than the approved amount (" . number_format($finance->approvedAmount, 2) . ").",
					], 422);
				}
            $data = array(
                'creditLimitAmount'=>$finance->approvedAmount,
            );
        return response()->json([
            'success' => true,
            'message' => 'Finance record retrieved successfully.',
            'data' => $data
        ], 200);
    }


}
