<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Square\SquareClient;
use Square\Payments\Requests\CreatePaymentRequest;
use Square\Types\Money;
use Square\Types\Currency;
use Illuminate\Support\Str;
use OpenApi\Annotations as OA;


class SquarePaymentController extends Controller
{
    protected SquareClient $client;

    public function __construct()
    {
        $this->client = new SquareClient(
            token: env('SQUARE_ACCESS_TOKEN'), // ✅ works with SDK v43+
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
    // public function createPayment(Request $request)
    // {
    //     $request->validate([
    //         'source_id' => 'required|string', // card token from JS
    //         'amount' => 'required|numeric|min:1',
    //     ]);

    //     $money = new Money(
    //         amount: (int)($request->amount * 100),
    //         currency: Currency::Usd->value,
    //     );

    //     $paymentRequest = new CreatePaymentRequest(
    //         idempotencyKey: (string) Str::uuid(),
    //         sourceId: $request->source_id,
    //         amountMoney: $money,
    //     );

    //     $response = $this->client->payments->create($paymentRequest);

    //     if ($response->isSuccess()) {
    //         return response()->json([
    //             'success' => true,
    //             'payment' => $response->getResult()->payment,
    //         ]);
    //     }

    //     return response()->json([
    //         'success' => false,
    //         'errors' => $response->getErrors(),
    //     ], 400);
    // }
    public function createPayment(Request $request)
{
    $request->validate([
        'source_id' => 'required|string', // card token from JS
        'amount' => 'required|numeric|min:1',
    ]);

    $money = new Money();
    $money->setAmount((int) ($request->amount * 100));
    $money->setCurrency('USD');

    $paymentRequest = new CreatePaymentRequest();
    $paymentRequest->setIdempotencyKey((string) Str::uuid());
    $paymentRequest->setSourceId($request->source_id);
    $paymentRequest->setAmountMoney($money);

    $response = $this->client->getPaymentsApi()->createPayment($paymentRequest);

    if ($response->isSuccess()) {
        return response()->json([
            'success' => true,
            'payment' => $response->getResult()->getPayment(),
        ]);
    }

    return response()->json([
        'success' => false,
        'errors' => $response->getErrors(),
    ], 400);
}
}
