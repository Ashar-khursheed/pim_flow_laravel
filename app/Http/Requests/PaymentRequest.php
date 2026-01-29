<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'order_id' => 'required|string|max:50',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|in:INR,USD,EUR,GBP,AED',
            'language' => 'sometimes|string|in:EN,HI',

            // Billing information (optional)
            'billing_name' => 'sometimes|string|max:100',
            'billing_address' => 'sometimes|string|max:200',
            'billing_city' => 'sometimes|string|max:50',
            'billing_state' => 'sometimes|string|max:50',
            'billing_zip' => 'sometimes|string|max:10',
            'billing_country' => 'sometimes|string|max:50',
            'billing_tel' => 'sometimes|string|max:15',
            'billing_email' => 'sometimes|email:strict|max:100',

            // Shipping information (optional)
            'delivery_name' => 'sometimes|string|max:100',
            'delivery_address' => 'sometimes|string|max:200',
            'delivery_city' => 'sometimes|string|max:50',
            'delivery_state' => 'sometimes|string|max:50',
            'delivery_zip' => 'sometimes|string|max:10',
            'delivery_country' => 'sometimes|string|max:50',
            'delivery_tel' => 'sometimes|string|max:15',

            // Merchant parameters (optional)
            'merchant_param1' => 'sometimes|string|max:200',
            'merchant_param2' => 'sometimes|string|max:200',
            'merchant_param3' => 'sometimes|string|max:200',
            'merchant_param4' => 'sometimes|string|max:200',
            'merchant_param5' => 'sometimes|string|max:200',

            'promo_code' => 'sometimes|string|max:50',
            'customer_identifier' => 'sometimes|string|max:50',
        ];
    }
}
