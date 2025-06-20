<?php
namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Square\SquareClient;
use Square\Models\Money;
use Square\Models\QuickPay;
use Square\Models\CreatePaymentLinkRequest;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class SquarePaymentController extends Controller
{
    protected $client;

    public function __construct()
    {
        $this->client = new SquareClient([
            'accessToken' => env('SQUARE_ACCESS_TOKEN'),
            'environment' => 'sandbox', // Change to 'production' in live mode
        ]);
    }

    public function createPaymentLink(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'currency' => 'required|string|size:3',
            'title' => 'required|string|max:255',
        ]);

        $amount = (int) ($request->amount * 100); // Convert to cents
        $idempotencyKey = Str::uuid();

        $quickPay = new QuickPay([
            'name' => $request->title,
            'priceMoney' => new Money([
                'amount' => $amount,
                'currency' => strtoupper($request->currency),
            ]),
            'locationId' => env('SQUARE_LOCATION_ID'),
        ]);

        $createRequest = new CreatePaymentLinkRequest([
            'idempotencyKey' => $idempotencyKey,
            'quickPay' => $quickPay,
        ]);

        $apiResponse = $this->client->getCheckoutApi()->createPaymentLink($createRequest);

        if ($apiResponse->isSuccess()) {
            $result = $apiResponse->getResult();
            return response()->json([
                'success' => true,
                'payment_url' => $result->getPaymentLink()->getUrl(),
            ]);
        } else {
            return response()->json([
                'success' => false,
                'errors' => $apiResponse->getErrors(),
            ], 400);
        }
    }
}
