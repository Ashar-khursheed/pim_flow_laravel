<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Square\SquareClient;
use Square\Payments\Requests\CreatePaymentRequest;
use Square\Types\Money;
use Square\Types\Currency;
use Illuminate\Support\Str;

class SquarePaymentController extends Controller
{
    protected SquareClient $client;

    public function __construct()
    {
        $this->client = new SquareClient(
            token: env('SQUARE_ACCESS_TOKEN'), // ✅ works with SDK v43+
        );
    }

    public function createPayment(Request $request)
    {
        $request->validate([
            'source_id' => 'required|string', // card token from JS
            'amount' => 'required|numeric|min:1',
        ]);

        $money = new Money(
            amount: (int)($request->amount * 100),
            currency: Currency::Usd->value,
        );

        $paymentRequest = new CreatePaymentRequest(
            idempotencyKey: (string) Str::uuid(),
            sourceId: $request->source_id,
            amountMoney: $money,
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
