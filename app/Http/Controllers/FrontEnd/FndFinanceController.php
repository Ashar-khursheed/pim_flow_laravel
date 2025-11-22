<?php

namespace App\Http\Controllers\FrontEnd;

use Illuminate\Http\Request;
use App\Models\Finance;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use OpenApi\Annotations as OA;
use App\Http\Controllers\Controller;
class FndFinanceController extends Controller
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
     *                 required={"business_name", "business_address", "country", "city"},  
     *                 @OA\Property(property="payment_options", type="string", example="Net Payment Terms", description="Payment option description"),                
     *                 @OA\Property(property="term_selection", type="string",enum={"Net 30 Days","Net 45 Days","Net 60 Days"}, example="Net 30 Days", description="Net Pay in 30/45/60 Days"),
     *                 @OA\Property(property="amount", type="number", format="float", example=5000.75, description="Enter amount"),
     *                 @OA\Property(property="documents", type="string", format="binary", description="Upload supporting document file"),
     *                 @OA\Property(property="payment_due", type="string", format="date", example="2025-12-31", description="Payment due date add Net 30 Days"),
     *                 @OA\Property(property="type_of_business", type="string", example="E-commerce", description="Type of business (Advertising / E-commerce)"),
     *                 @OA\Property(property="business_name", type="string", example="ABC Pvt Ltd", description="Legal business name"),
     *                 @OA\Property(property="first_name", type="string", example="first name", description="first_name"),
     *                 @OA\Property(property="last_name", type="string", example="last name", description="last name"),
     *                 @OA\Property(property="business_email", type="string", format="email", example="abc@domain.com", description="Business Email"),
     *                 @OA\Property(property="business_address", type="string", example="123 Street, Delhi", description="Business address"),
     *                 @OA\Property(property="country", type="string", example="India"),
     *                 @OA\Property(property="address", type="string", example="8800 Bissonnet Street, Ste A, Houston, Texas 77074"),
     *                 @OA\Property(property="city", type="string", example="Houston"),     *                  
     *                 @OA\Property(property="zip", type="string", example="77074"),
     *                 @OA\Property(property="annual_revenue", type="string", example="10M USD"),
     *                 @OA\Property(property="years_in_business", type="string", example="5 – 10 years"),
     *                 @OA\Property(property="accounts_payable_email", type="string", format="email", example="finance@abc.com"),
     *                 @OA\Property(property="accounts_payable_phone", type="string", example="+91-9876543210"),
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
            'term_selection' => 'nullable|string|in:Net 30 Days,Net 45 Days,Net 60 Days',
            'amount' => 'required|numeric',
            'documents' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png,webp,svg|max:10240',
            'payment_due' => 'nullable|date',
            'type_of_business' => 'nullable|string|max:255',
            'business_name' => 'required|string|max:255',
            'business_email' => 'nullable|email|max:255',            
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'business_address' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'nullable|string|max:255',
            'zip' => 'nullable|string|max:20',
            'annual_revenue' => 'nullable|string',
            'years_in_business' => 'nullable|string',
            'accounts_payable_email' => 'nullable|email',
            'accounts_payable_phone' => 'nullable|string',
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
        $data['created_by'] = Auth::id() ?? 1;
        $data['updated_by'] = '0';          
        $data['accountStatus'] = 'Pending';   
        $nextPaymentDue = "";
        if ($request->term_selection == 'Net 30 Days') {
            $nextPaymentDue = "+30 Days";
        } elseif ($request->term_selection == 'Net 45 Days') {
            $nextPaymentDue = "+45 Days";
        } else if($request->term_selection == 'Net 60 Days'){
            $nextPaymentDue = "+60 Days";
        }
        if(!empty($nextPaymentDue)){
            $data['next_due_date'] =date('Y-m-d', strtotime($nextPaymentDue));
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

        $finance = Finance::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Finance record created successfully.',
            'data' => $finance
        ], 201);
    }
     
}
