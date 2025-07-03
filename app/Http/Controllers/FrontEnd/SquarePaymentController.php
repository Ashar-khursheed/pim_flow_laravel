<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Square\SquareClient;
use Square\Environments;
use Square\Payments\Requests\CreatePaymentRequest;
use Square\Types\Money;
use Square\Types\Currency;
use Illuminate\Support\Str;
use OpenApi\Annotations as OA;
use Square\Environment;

// class SquarePaymentController extends Controller
// {
//     // protected SquareClient $client;

//     // public function __construct()
//     // {
//     //     $this->client = new SquareClient(
//     //         token: env('SQUARE_ACCESS_TOKEN'),
//     //         options: [
//     //             'baseUrl' => Environments::Sandbox->value,
//     //         ]
//     //     );
//     // }

//     /**
//      * @OA\Post(
//      *     path="/api/frontend/payment-square",
//      *     summary="Create a payment using Square",
//      *     description="Receives a source_id (card token) and amount to process payment via Square API.",
//      *     tags={"Square Payment"},
//      *     @OA\RequestBody(
//      *         required=true,
//      *         @OA\JsonContent(
//      *             required={"source_id", "amount"},
//      *             @OA\Property(
//      *                 property="source_id",
//      *                 type="string",
//      *                 example="cnon:card-nonce-ok",
//      *                 description="Card token generated from Square Web Payments SDK"
//      *             ),
//      *             @OA\Property(
//      *                 property="amount",
//      *                 type="number",
//      *                 format="float",
//      *                 example=10.00,
//      *                 description="Amount in USD"
//      *             )
//      *         )
//      *     ),
//      *     @OA\Response(
//      *         response=200,
//      *         description="Payment successful",
//      *         @OA\JsonContent(
//      *             @OA\Property(property="success", type="boolean", example=true),
//      *             @OA\Property(
//      *                 property="payment",
//      *                 type="object",
//      *                 description="Square payment object returned after success"
//      *             )
//      *         )
//      *     ),
//      *     @OA\Response(
//      *         response=400,
//      *         description="Payment failed or validation error",
//      *         @OA\JsonContent(
//      *             @OA\Property(property="success", type="boolean", example=false),
//      *             @OA\Property(
//      *                 property="errors",
//      *                 type="array",
//      *                 @OA\Items(type="string", example="Card declined")
//      *             )
//      *         )
//      *     )
//      * )
//      */
//     // public function createPayment(Request $request)
//     // {
//     //     // Validate access token
//     //     $token = env('SQUARE_ACCESS_TOKEN');
//     //     if (!$token) {
//     //         return response()->json([
//     //             'success' => false,
//     //             'errors' => ['Square access token not found in environment'],
//     //         ], 500);
//     //     }

//     //     // Validate request input
//     //     $request->validate([
//     //         'source_id' => 'required|string',
//     //         'amount' => 'required|numeric|min:0.01',
//     //     ]);

//     //     try {
//     //         // Follow the official Square documentation approach exactly
//     //         $response = $this->client->payments->create(
//     //             new CreatePaymentRequest([
//     //                 'idempotencyKey' => (string) Str::uuid(),
//     //                 'amountMoney' => new Money([
//     //                     'amount' => (int)($request->amount * 100),
//     //                     'currency' => Currency::Usd->value,
//     //                 ]),
//     //                 'sourceId' => $request->source_id,
//     //             ])
//     //         );

//     //         if ($response->isSuccess()) {
//     //             return response()->json([
//     //                 'success' => true,
//     //                 'payment' => $response->getResult()->getPayment(),
//     //             ]);
//     //         }

//     //         return response()->json([
//     //             'success' => false,
//     //             'errors' => $response->getErrors(),
//     //         ], 400);

//     //     } catch (\Square\Exceptions\SquareApiException $e) {
//     //         return response()->json([
//     //             'success' => false,
//     //             'errors' => ['Square API Error: ' . $e->getMessage()],
//     //         ], 500);
//     //     } catch (\Exception $e) {
//     //         return response()->json([
//     //             'success' => false,
//     //             'errors' => ['General Error: ' . $e->getMessage()],
//     //         ], 500);
//     //     }
//     // }


// }  



class SquarePaymentController extends Controller
{
    protected SquareClient $client;

    public function __construct()
    {
        $this->client = new SquareClient(
            token: env('SQUARE_ACCESS_TOKEN'),
            options: [
                'baseUrl' => Environments::Sandbox->value,
            ]
        );
        
    }

    /**
     * @OA\Post(
     *     path="/api/frontend/payment-square",
     *     summary="Create a payment using Square",
     *     description="Receives a source_id (card token) and amount to process payment via Square API.",
     *     tags={"Square Payment"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"source_id", "amount"},
     *             @OA\Property(
     *                 property="source_id",
     *                 type="string",
     *                 example="cnon:card-nonce-ok",
     *                 description="Card token generated from Square Web Payments SDK"
     *             ),
     *             @OA\Property(
     *                 property="amount",
     *                 type="number",
     *                 format="float",
     *                 example=10.00,
     *                 description="Amount in USD"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="payment",
     *                 type="object",
     *                 description="Square payment object returned after success"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Payment failed or validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(
     *                 property="errors",
     *                 type="array",
     *                 @OA\Items(type="string", example="Card declined")
     *             )
     *         )
     *     )
     * )
     */
    public function createPayment(Request $request)
    {
        // Validate access token
        $token = env('SQUARE_ACCESS_TOKEN');
        if (!$token) {
            return response()->json([
                'success' => false,
                'errors' => ['Square access token not found in environment'],
            ], 500);
        }

        // Validate request input
        $request->validate([
            'source_id' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
        ]);

        try {
            // Follow the official Square documentation approach exactly
            $response = $this->client->payments->create(
                new CreatePaymentRequest([
                    'idempotencyKey' => (string) Str::uuid(),
                    'amountMoney' => new Money([
                        'amount' => (int)($request->amount * 100),
                        'currency' => Currency::Usd->value,
                    ]),
                    'sourceId' => $request->source_id,
                ])
            );

            // Check if the response has errors (errors property exists and is not empty)
            if (empty($response->getErrors())) {
                // Success - payment was created
                $payment = $response->getPayment();
                
                return response()->json([
                    'success' => true,
                    'payment' => [
                        'id' => $payment->getId(),
                        'status' => $payment->getStatus(),
                        'amount' => $payment->getAmountMoney()->getAmount() / 100, // Convert back to dollars
                        'currency' => $payment->getAmountMoney()->getCurrency(),
                        'created_at' => $payment->getCreatedAt(),
                        'receipt_url' => $payment->getReceiptUrl(),
                    ]
                ]);
            }

            // Has errors - payment failed
            $errors = [];
            foreach ($response->getErrors() as $error) {
                $errors[] = $error->getCode() . ': ' . $error->getDetail();
            }

            return response()->json([
                'success' => false,
                'errors' => $errors,
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'errors' => ['Error: ' . $e->getMessage()],
                'debug_info' => [
                    'exception_class' => get_class($e),
                    'file' => $e->getFile() . ':' . $e->getLine(),
                ]
            ], 500);
        }
    }
} 