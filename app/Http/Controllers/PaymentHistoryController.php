<?php

namespace App\Http\Controllers;

use Doctrine\Common\Annotations\Annotation\Required;
use Illuminate\Http\Request;
use App\Models\ProductAccessory;
use App\Models\Product;
use App\Models\AccessoryItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use App\Models\PaymentManagement;
use Illuminate\Support\Facades\Bus;
use Illuminate\Bus\Batch;
use OpenApi\Annotations as OA;

class PaymentHistoryController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/delivery/payment-history",
     *     summary="Get list of delivery payment history",
     *     tags={"Delivery Payment History"},
     *     @OA\Parameter(
     *         name="order_id",
     *         in="query",
     *         required=false,
     *         description="Filter by order ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         description="Filter by status",
     *         @OA\Schema(type="string", enum={"pending","completed","failed","cancelled","refunded","all"}, example="all")
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
            $query = PaymentManagement::with(['createdBy','updatedBy']);            
            if ($request->input('order_id') != "" ) {
                $query->where('order_id', $request->input('order_id'));
            }
          
            if ($request->input('status') != "all") {
                $query->where('status', $request->status);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {                   
                    $q->where('order_id', 'like', "%{$search}%")
                        ->orWhere('transaction_id', 'like', "%{$search}%");
                });
            }

            $searchableColumns = ['id', 'order_id', 'status', 'transaction_id'];
            $sortableColumns = array_merge($searchableColumns, ['created_at', 'updated_at']);
            $sortBy = in_array($request->input('sort_by'), $sortableColumns) ? $request->input('sort_by') : 'id';
            $sortDir = strtolower($request->input('sort_direction', 'desc')) === 'asc' ? 'asc' : 'desc';

            $perPage = $request->get('per_page', 15);
            $paymentManagement = $query->orderBy($sortBy, $sortDir)->paginate($perPage);

            $formattedProducts = $paymentManagement->getCollection()->map(function ($pyment) {
              
 
                return [
                    'id' => $pyment->id,
                    'payment_method' => $pyment->payment_method,
                    'order_id' => $pyment->order_id,
                    'transaction_id' => $pyment->transaction_id,
                    'payment_mode' => $pyment->payment_mode,
                    'amount' => $pyment->amount,
                    'status' => $pyment->status,
                    'notes' => $pyment->notes,
                    'payment_details' => json_decode($pyment->payment_details),
                    'payment_img' => $pyment->payment_img,
                    'rider_name' => $pyment->rider_name,
                    'payment_date' => date('d-m-Y h:i:s',strtotime($pyment->payment_date)),
                    'created_by' => $pyment->createdBy?->username??null,
                    'updated_by' => $pyment->updatedBy?->username??null,
                    'created_at' => date('d-m-Y',strtotime($pyment->created_at)),
                    'updated_at' => date('d-m-Y',strtotime($pyment->updated_at)),
                    
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Product accessories retrieved successfully',
                'data' => [
                    'current_page' => $paymentManagement->currentPage(),
                    'per_page' => $paymentManagement->perPage(),
                    'total' => $paymentManagement->total(),
                    'data' => $formattedProducts,
                ]
            ]);
         
    }

    /**
	 * @OA\Post(
	 *     path="/api/delivery/payment-history",
	 *     summary="Create a new cash delivery payment",
	 *     description="Create a new cash delivery payment record for an authenticated customer",
	 *     operationId="createCashPayment",
	 *     tags={"Delivery Payment History"},
	 *     security={{"bearerAuth": {}}},
	 *     @OA\RequestBody(
	 *         required=true,
	 *         description="Payment data with optional file attachment",
	 *         @OA\MediaType(
	 *             mediaType="multipart/form-data",
	 *             @OA\Schema(
	 *                 required={"order_id", "payment_mode", "amount", "status", "payment_date"},
	 *                 @OA\Property(property="order_id", type="integer", example=123),
	 *                 @OA\Property(property="transaction_id", type="string", example="TXN456789"),
	 *                 @OA\Property(property="payment_mode", type="string", enum={"Bank Transfer","Stripe","Razorpay","Cash on Delivery","CCAvenue"}, example="Cash on Delivery"),
	 *                 @OA\Property(property="amount", type="number", format="float", example=299.99),
	 *                 @OA\Property(property="status", type="string", enum={"pending","completed","failed","cancelled","refunded"}, example="completed"),
	 *                 @OA\Property(property="rider_name", type="string", example="Jon Jones"),
	 *                 @OA\Property(property="payment_date", type="string", format="date", example="2024-06-24"),
	 *                 @OA\Property(property="notes", type="string", example="First installment paid"),
	 *                  @OA\Property(
	 *                     property="payment_details",
	 *                     type="object",
	 *                     description="Additional payment gateway details",
	 *                     @OA\Property(property="bank", type="string", example="XYZ Bank"),
	 *                     @OA\Property(property="ref", type="string", example="12345XYZ"),
	 *                     @OA\Property(property="gateway_response", type="string", example="success")
	 *                 ),
	 *                 @OA\Property(
	 *                     property="payment_img",
	 *                     description="Upload receipt or proof of payment",
	 *                     type="string",
	 *                     format="binary"
	 *                 )
	 *             )
	 *         )
	 *     ),
	 *     @OA\Response(
	 *         response=201,
	 *         description="Payment created successfully",
	 *     ),
	 *     @OA\Response(
	 *         response=401,
	 *         description="Unauthorized - Invalid or missing authentication token"
	 *     )
	 * )
	 */

	public function store(Request $request)
	{   //dd($request->all());
		try {
			// Validate the incoming request
			$validated = $request->validate([
				'order_id' => 'required|integer|exists:orders,id', // Ensure order exists
				'transaction_id' => 'nullable|string|max:255|unique:payments_management,transaction_id', // Ensure unique transaction
				'payment_mode' => 'required|string|in:Credit Card,Debit Card,PayPal,Bank Transfer,Cash on Delivery,Stripe,Razorpay',
				'amount' => 'required|numeric|min:0.01|max:999999.99',
				'status' => 'required|string|in:pending,completed,failed,cancelled,refunded',
				'payment_date' => 'required|date|before_or_equal:today',
				'notes' => 'nullable|string|max:1000',
				'payment_details' => 'nullable|json|max:2000',
				'payment_method' => 'nullable|string|max:255'
			]);

			// Add authenticated user ID (assumes customer authentication)
			if (!auth()->check()) {
				return response()->json([
					'message' => 'Authentication required.'
				], 401);
			}

			$validated['created_by'] = auth::id();
			$validated['rider_name'] = $request->rider_name;

			if (isset($validated['payment_details'])) {
				$validated['payment_details'] =json_encode($validated['payment_details']);
			}

			$validated['payment_img'] = uploadImageToWebpS3FromFile(
				$request,
				'payment_img',
				env('STORAGE_ENV') . '/customer/payment'
			);
 
			// Create the payment record
			$payment = PaymentManagement::create($validated);
		 

			// Return success response with 201 status
			return response()->json([
				'message' => 'Payment created successfully.',
				'data' => $payment
			], 201);

		} catch (ValidationException $e) {
			// Handle validation errors
			return response()->json([
				'message' => 'The given data was invalid.',
				'errors' => $e->errors()
			], 422);

		} catch (\Exception $e) {
			// Handle any other errors
			return response()->json([
				'message' => 'Something went wrong while creating the payment.',
				'error' => $e->getMessage()
			], 500);
		}
	}
 
    /**
     * @OA\Get(
     *     path="/api/delivery/payment-history/{id}",
     *     summary="Edit get Payment History",
     *     tags={"Delivery Payment History"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Delivery Payment History order ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Payment History retrieved successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Payment History not found"
     *     ),
     *      security={{"bearerAuth":{}}}
     * )
     */
    public function show($id)
    {
        try {
            
            $paymentManagement = PaymentManagement::with(['createdBy','updatedBy'])->where('order_id',$id)->get();   
 
            // Map items properly
            $paymentManagementList = $paymentManagement->map(function ($pyment) {
           
                return [
                    'id' => $pyment->id,
                    'payment_method' => $pyment->payment_method,
                    'order_id' => $pyment->order_id,
                    'transaction_id' => $pyment->transaction_id,
                    'payment_mode' => $pyment->payment_mode,
                    'amount' => $pyment->amount,
                    'status' => $pyment->status,
                    'notes' => $pyment->notes,
                    'payment_details' => json_decode($pyment->payment_details),
                    'payment_img' => $pyment->payment_img,
                    'rider_name' => $pyment->rider_name,
                    'payment_date' => date('d-m-Y h:i:s',strtotime($pyment->payment_date)),
                    'created_by' => $pyment->createdBy?->username??null,
                    'updated_by' => $pyment->updatedBy?->username??null,
                    'created_at' => date('d-m-Y',strtotime($pyment->created_at)),
                    'updated_at' => date('d-m-Y',strtotime($pyment->updated_at)),
                    
                ];
            });

           
            return response()->json([
                'success' => true,
                'message' => 'Payment History successfully',
                'data' => $paymentManagementList
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment History not found',
                'error' => $e->getMessage()
            ], 404);
        }

    }
           
}
