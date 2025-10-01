<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\PaymobService;
use Illuminate\Http\Request;

class PaymobController extends Controller
{
    protected $paymob;

    public function __construct(PaymobService $paymob)
    {
        $this->paymob = $paymob;
    }

    /**
     * Initialize payment - Create intention and return client secret
     */
    public function initiate(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'email' => 'required|email',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'phone' => 'required|string',
        ]);

        try {
            $billingData = [
                'first_name'   => $request->first_name,
                'last_name'    => $request->last_name,
                'email'        => $request->email,
                'phone_number' => $request->phone,
                'apartment'    => 'NA',
                'floor'        => 'NA',
                'street'       => 'NA',
                'building'     => 'NA',
                'shipping_method' => 'NA',
                'postal_code'  => 'NA',
                'city'         => 'Cairo',
                'country'      => 'EG',
                'state'        => 'NA',
            ];

            // Optional: Add custom items
            $items = [
                [
                    'name'        => $request->item_name ?? 'Product Purchase',
                    'amount'      => (int) round($request->amount * 100), // in cents
                    'description' => $request->item_description ?? 'Order payment',
                    'quantity'    => $request->quantity ?? 1,
                ]
            ];

            // Create intention using new API
            $intention = $this->paymob->createIntention(
                $request->amount,
                $billingData,
                $items
            );

            return response()->json([
                'success' => true,
                'payment_token' => $intention['client_secret'], // This is what Pixel SDK needs
                'intention_id' => $intention['id'] ?? null,
                'public_key' => $this->paymob->getPublicKey(), // Send public key to frontend
            ]);

        } catch (\Exception $e) {
            \Log::error('Payment initiation failed', [
                'error' => $e->getMessage(),
                'user' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Payment initialization failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Webhook - Handle payment notifications from Paymob
     */
    public function webhook(Request $request)
    {
        try {
            // Log the webhook data
            \Log::info('Paymob Webhook Received', $request->all());

            // Get HMAC for verification
            $hmac = $request->header('hmac');
            
            if ($hmac) {
                // Verify HMAC
                $calculatedHmac = $this->calculateHmac($request->all());
                
                if ($hmac !== $calculatedHmac) {
                    \Log::warning('Invalid HMAC in webhook');
                    return response()->json(['error' => 'Invalid HMAC'], 401);
                }
            }

            // Process webhook data
            $data = $request->all();
            
            // Check payment status
            $isSuccess = isset($data['success']) && ($data['success'] === true || $data['success'] === 'true');
            $transactionId = $data['id'] ?? null;
            $orderId = $data['order'] ?? null;
            $amount = isset($data['amount_cents']) ? $data['amount_cents'] / 100 : null;

            if ($isSuccess) {
                // Payment successful - Update your database
                \Log::info('Payment successful', [
                    'transaction_id' => $transactionId,
                    'order_id' => $orderId,
                    'amount' => $amount
                ]);

                // TODO: Your business logic here
                // - Update order status
                // - Send confirmation email
                // - Update user balance
                // - etc.

            } else {
                // Payment failed
                \Log::error('Payment failed', [
                    'transaction_id' => $transactionId,
                    'data' => $data
                ]);
            }

            return response()->json(['message' => 'Webhook processed'], 200);

        } catch (\Exception $e) {
            \Log::error('Webhook processing error', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Calculate HMAC for webhook verification
     */
    private function calculateHmac($data)
    {
        $hmacSecret = config('services.paymob.hmac_secret');
        
        if (!$hmacSecret) {
            return null;
        }

        // Concatenate specific fields in order (adjust based on Paymob's documentation)
        $string = 
            ($data['amount_cents'] ?? '') .
            ($data['created_at'] ?? '') .
            ($data['currency'] ?? '') .
            ($data['error_occured'] ?? '') .
            ($data['has_parent_transaction'] ?? '') .
            ($data['id'] ?? '') .
            ($data['integration_id'] ?? '') .
            ($data['is_3d_secure'] ?? '') .
            ($data['is_auth'] ?? '') .
            ($data['is_capture'] ?? '') .
            ($data['is_refunded'] ?? '') .
            ($data['is_standalone_payment'] ?? '') .
            ($data['is_voided'] ?? '') .
            ($data['order'] ?? '') .
            ($data['owner'] ?? '') .
            ($data['pending'] ?? '') .
            ($data['source_data_pan'] ?? '') .
            ($data['source_data_sub_type'] ?? '') .
            ($data['source_data_type'] ?? '') .
            ($data['success'] ?? '');

        return hash_hmac('sha512', $string, $hmacSecret);
    }

    /**
     * Check transaction status (optional)
     */
    public function checkStatus($transactionId)
    {
        try {
            $transaction = $this->paymob->getTransaction($transactionId);
            
            return response()->json([
                'success' => true,
                'transaction' => $transaction
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}