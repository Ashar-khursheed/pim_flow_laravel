<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MadeToOrder;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

use Illuminate\Support\Facades\Bus;
use Illuminate\Bus\Batch;

use App\Jobs\Quote\RequestQuoteMailJob;

class MadeToOrderController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/frontend/made-to-orders",
     *     summary="Create a new made-to-order request",
     *     description="This API allows users to create a new made-to-order request with customer and product details.",
     *     tags={"Frontend Made to Orders"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Made to Order form data",
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"product_id", "quantity", "name", "email","phone_number","notes"},
     *
     *                 @OA\Property(property="product_id", type="integer", example=1808, description="ID of the product to order"),
     *                 @OA\Property(property="quantity", type="integer", example=2, description="Quantity of the product"),
     *                 @OA\Property(property="name", type="string", example="John Doe", description="Customer full name"),
     *                 @OA\Property(property="email", type="string", format="email", example="john@example.com", description="Customer email address"),
     *                 @OA\Property(property="address", type="string", example="123 Main Street, Connaught Place", description="Shipping address"),
     *                 @OA\Property(property="city", type="string", example="New Delhi", description="City name"),
     *                 @OA\Property(property="state", type="string", example="Delhi", description="State name"),
     *                 @OA\Property(property="country", type="string", example="India", description="Country name"),
     *                 @OA\Property(property="zipcode", type="string", example="110001", description="Postal or ZIP code"),
     *                 @OA\Property(property="phone_number", type="string", example="9876543210", description="Customer contact number"),
     *                 @OA\Property(property="notes", type="string", example="Need delivery before 25th December", description="Optional order notes"),
     *
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Made to Order request created successfully"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error or bad request"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Invalid or missing authentication token"
     *     )
     * )
     */
    public function store(Request $request)
    {
        try {

            $validator = Validator::make($request->all(), [
                'product_id' => 'required|exists:ec_products,id',
                'quantity' => 'required|integer|min:1',
                'name' => 'required|string|max:255',
                'email' => 'required|email:strict',
                'address' => 'nullable|string',
                'city' => 'nullable|string|max:100',
                'state' => 'nullable|string|max:100',
                'country' => 'nullable|string|max:100',
                'zipcode' => 'nullable|string|max:20',
                'phone_number' => 'required|string|regex:/^[0-9\-\+\(\)\s]+$/',
                'notes' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();
            $orderQuote = MadeToOrder::create($data);

            $batch = Bus::batch([])->name("Order Request Quote - #{$orderQuote->id}")->dispatch();
            $batch->options['queue'] = config('app.website') . '_REQ_QOT';
            $batch->add(new RequestQuoteMailJob([
                'recordId' => $orderQuote->id
            ]));

            if ($orderQuote) {
                $originalValues = [];
                $newValues = $orderQuote->getAttributes();
                $changes = [];
                foreach ($newValues as $field => $newValue) {
                    $changes[$field] = [
                        'old' => $originalValues[$field] ?? null,
                        'new' => $newValue,
                    ];
                }
                $versionData = [
                    'version_id' => $orderQuote->id,
                    'created_by' => Auth::id() ?? 1,
                    'module' => 'MadeToOrder',
                    'action' => 'Create',
                    'description' => json_encode($changes),
                ];

                app(\App\Services\VersionService::class)
                    ->createVersion($versionData);
            }


            return response()->json([
                'success' => true,
                'message' => 'Made to Order successfully.',
                'data' => $orderQuote
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'The given data was invalid.',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Something went wrong while creating the payment.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
 }
