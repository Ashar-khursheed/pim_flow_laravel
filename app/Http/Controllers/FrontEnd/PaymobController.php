<?php
namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\PaymobService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymobController extends Controller
{
    protected $paymob;

    public function __construct(PaymobService $paymob)
    {
        $this->paymob = $paymob;
    }

    /**
     * Step 1: Create Intention (frontend will use client_secret to continue payment)
     */
    public function initiate(Request $request)
    {
        try {
            $amount = $request->amount;

            $billingData = [
                "first_name"   => $request->first_name,
                "last_name"    => $request->last_name,
                "email"        => $request->email,
                "phone_number" => $request->phone,
                "city"         => "Dubai",
                "country"      => "UAE",
            ];

            $intention = $this->paymob->createIntention($amount, $billingData);

            return response()->json([
                'success' => true,
                'intention_id' => $intention['id'],
                'client_secret' => $intention['client_secret'],
                'public_key' => $this->paymob->getPublicKey(),
            ]);
        } catch (\Exception $e) {
            Log::error("Paymob initiate error", ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Webhook callback (Paymob will notify here after payment)
     */
    public function webhook(Request $request)
    {
        // TODO: Add HMAC verification (check signature to confirm authenticity)
        Log::info('Paymob Webhook received', $request->all());

        return response()->json(['status' => 'ok']);
    }
}
