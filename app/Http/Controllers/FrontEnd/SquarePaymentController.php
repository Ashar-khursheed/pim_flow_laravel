<?php
namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Square\SquareClient;
use Square\Models\CreatePaymentRequest;
use Square\Models\Money;
use Illuminate\Support\Str;

class SquarePaymentController extends Controller
{
    protected $client;

    public function __construct()
    {
        $this->client = new SquareClient(
            token: env('SQUARE_ACCESS_TOKEN'),
            environment: env('SQUARE_ENV', 'sandbox')
        );
    }

    public function createPayment(Request $request)
    {
        $request->validate([
            'nonce' => 'required|string',
            'amount' => 'required|numeric|min:1',
        ]);

        $body = new CreatePaymentRequest([
            'sourceId' => $request->nonce,
            'idempotencyKey' => Str::uuid(),
            'amountMoney' => new Money([
                'amount' => (int)($request->amount * 100), // cents
                'currency' => 'USD',
            ]),
        ]);

        $apiResponse = $this->client->payments->create($body);

        if ($apiResponse->isSuccess()) {
            return response()->json([
                'success' => true,
                'payment' => $apiResponse->getResult()->getPayment(),
            ]);
        }

        return response()->json([
            'success' => false,
            'errors' => $apiResponse->getErrors(),
        ], 400);
    }
}
