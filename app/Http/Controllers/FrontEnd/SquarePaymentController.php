<?php

namespace App\Http\Controllers\FrontEnd;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Square\SquareClient;
use Square\Payments\Models\CreatePaymentRequest;
use Square\Types\Money;
use Square\Types\Currency;
use Illuminate\Support\Str;

class SquarePaymentController extends Controller
{
    protected SquareClient $client;

    public function __construct()
    {
        $this->client = new SquareClient(
            token: env('SQUARE_ACCESS_TOKEN'),
            environment: env('SQUARE_ENV', 'sandbox') // or 'production'
        );
    }

    public function createPayment(Request $request)
    {
        $request->validate([
            'nonce' => 'required|string',
            'amount' => 'required|numeric|min:1',
        ]);

        $money = new Money(
            amount: (int)($request->amount * 100),
            currency: Currency::Usd
        );

        $paymentRequest = new CreatePaymentRequest(
            sourceId: $request->nonce,
            idempotencyKey: (string) Str::uuid(),
            amountMoney: $money
        );

        $response = $this->client->payments->create($paymentRequest);

        if ($response->isSuccess()) {
            return response()->json([
                'success' => true,
                'payment' => $response->getResult()->payment,
            ]);
        }

        return response()->json([
            'success' => false,
            'errors' => $response->getErrors(),
        ], 400);
    }
}
