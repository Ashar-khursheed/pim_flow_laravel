<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class StaxPaymentController extends Controller
{
    private $baseUrl;
    private $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.stax.base_url');
        $this->apiKey = config('services.stax.api_key');
    }

    /**
     * Process a payment with Stax
     */
    public function processPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_method_id' => 'required|string',
            'total' => 'required|numeric|min:0.01',
            'firstname' => 'required|string',
            'lastname' => 'required|string',
            'email' => 'nullable|email',
            'phone' => 'required|string',
            'address_1' => 'required|string',
            'address_city' => 'required|string',
            'address_state' => 'required|string',
            'address_zip' => 'required|string',
            'address_country' => 'required|string',
            'meta' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/charge', [
                'payment_method_id' => $request->payment_method_id,
                'total' => $request->total,
                'firstname' => $request->firstname,
                'lastname' => $request->lastname,
                'email' => $request->email,
                'phone' => $request->phone,
                'address_1' => $request->address_1,
                'address_2' => $request->address_2,
                'address_city' => $request->address_city,
                'address_state' => $request->address_state,
                'address_zip' => $request->address_zip,
                'address_country' => $request->address_country,
                'meta' => $request->meta ?? [],
            ]);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'data' => $response->json()
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'Payment failed',
                'error' => $response->json()
            ], $response->status());

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment processing error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get customer payment methods
     */
    public function getPaymentMethods($customerId)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->get($this->baseUrl . '/customer/' . $customerId . '/payment-method');

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'data' => $response->json()
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payment methods',
                'error' => $response->json()
            ], $response->status());

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving payment methods',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a customer
     */
    public function createCustomer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'firstname' => 'required|string',
            'lastname' => 'required|string',
            'email' => 'required|email',
            'phone' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/customer', [
                'firstname' => $request->firstname,
                'lastname' => $request->lastname,
                'email' => $request->email,
                'phone' => $request->phone,
                'address_1' => $request->address_1,
                'address_2' => $request->address_2,
                'address_city' => $request->address_city,
                'address_state' => $request->address_state,
                'address_zip' => $request->address_zip,
                'address_country' => $request->address_country,
            ]);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'data' => $response->json()
                ], 201);
            }

            return response()->json([
                'success' => false,
                'message' => 'Customer creation failed',
                'error' => $response->json()
            ], $response->status());

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Customer creation error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get transaction details
     */
    public function getTransaction($transactionId)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->get($this->baseUrl . '/transaction/' . $transactionId);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'data' => $response->json()
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'Transaction not found',
                'error' => $response->json()
            ], $response->status());

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving transaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Refund a transaction
     */
    public function refundTransaction(Request $request, $transactionId)
    {
        $validator = Validator::make($request->all(), [
            'total' => 'required|numeric|min:0.01',
            'reason' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/transaction/' . $transactionId . '/refund', [
                'total' => $request->total,
                'reason' => $request->reason,
            ]);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'data' => $response->json()
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'Refund failed',
                'error' => $response->json()
            ], $response->status());

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Refund processing error',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}