<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\Services\StaxService;

class StaxPaymentController extends Controller
{
    protected $stax;

    public function __construct(StaxService $stax)
    {
        $this->stax = $stax;
    }

    /**
     * @OA\Post(
     *     path="/api/frontend/auth/Stax",
     *     tags={"Payments"},
     *     summary="Process a checkout payment",
     *     description="Takes a Stax.js payment token and amount, then processes the charge via Stax API.",
     *     operationId="checkout",
     *     
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"token","amount"},
     *             @OA\Property(property="token", type="string", example="tok_abc123XYZ", description="Payment token from Stax.js"),
     *             @OA\Property(property="amount", type="number", format="float", example=100.50, description="Charge amount in USD")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful transaction",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="transaction",
     *                 type="object",
     *                 example={"id": "txn_12345", "status": "succeeded", "amount": 100.50, "currency": "USD"}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed transaction",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string", example="Card declined")
     *         )
     *     )
     * )
     */
  public function checkout(Request $request)
{
    $request->validate([
        'token'  => 'required|string',  // Stax.js payment token
        'amount' => 'required|numeric|min:1',
        'currency' => 'sometimes|string|size:3', // optional, default USD
    ]);

    try {
        $data = [
            'amount' => intval($request->amount * 100), // amount in cents
            'currency' => $request->currency ?? 'USD',
            'payment_method' => $request->token,
        ];

        // Optional: handle ACH/bank payments
        if ($request->method === 'bank') {
            $data['bank_account'] = $request->bank_account;
            $data['bank_routing'] = $request->bank_routing;
            $data['bank_type'] = $request->bank_type ?? 'checking';
            $data['bank_holder_type'] = $request->bank_holder_type ?? 'personal';
        }

        // Call StaxService to process charge
        $result = $this->stax->charge($data);

        return response()->json([
            'success' => true,
            'transaction' => $result,
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
        ], 500);
    }
}

}
