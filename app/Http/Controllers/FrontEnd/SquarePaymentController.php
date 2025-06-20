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
            accessToken: env('SQUARE_ACCESS_TOKEN'),
            environment: env('SQUARE_ENV', 'sandbox')
        );
    }
    

    public function createPayment(Request $request)
    {
        $request->validate([
            'nonce' => 'required|string',
            'amount' => 'required|numeric|min:1',
        ]);

        $money = new Money();
        $money->setAmount((int) ($request->amount * 100)); // Convert to cents
        $money->setCurrency('USD');

        $paymentRequest = new CreatePaymentRequest(
            $request->nonce,
            Str::uuid(),
            $money
        );

        $paymentRequest->setLocationId(env('SQUARE_LOCATION_ID'));

        $response = $this->client->getPaymentsApi()->createPayment($paymentRequest);

        if ($response->isSuccess()) {
            return response()->json([
                'success' => true,
                'payment' => $response->getResult()->getPayment(),
            ]);
        } else {
            return response()->json([
                'success' => false,
                'errors' => $response->getErrors(),
            ], 400);
        }
    }
}
