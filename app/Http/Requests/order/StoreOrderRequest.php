<?php
// app/Http/Requests/Order/StoreOrderRequest.php
namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true; // Adjust based on your authorization logic
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'user_id' => 'required|exists:users,id',
            'shipping_option' => 'nullable|string|max:60',
            'shipping_method' => 'required|string|max:60',
            'status' => 'required|string|max:120',
            'amount' => 'required|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'shipping_amount' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'coupon_code' => 'nullable|string|max:120',
            'discount_amount' => 'nullable|numeric|min:0',
            'sub_total' => 'required|numeric|min:0',
            'is_confirmed' => 'boolean',
            'discount_description' => 'nullable|string|max:191',
            'is_finished' => 'boolean',
            'store_id' => 'nullable|exists:stores,id',
            'payment_id' => 'nullable|exists:payments,id',
            
            // Nested address data
            'address' => 'sometimes|array',
            'address.name' => 'required_with:address|string|max:191',
            'address.phone' => 'nullable|string|max:20',
            'address.email' => 'nullable|email|max:191',
            'address.country' => 'nullable|string|max:120',
            'address.state' => 'nullable|string|max:120',
            'address.city' => 'nullable|string|max:120',
            'address.address' => 'nullable|string|max:191',
            'address.zip_code' => 'nullable|string|max:20',
            'address.type' => 'nullable|string|max:60',
            
            // Nested referral data
            'referral' => 'sometimes|array',
            'referral.ip' => 'nullable|string|max:39',
            'referral.landing_domain' => 'nullable|string|max:191',
            'referral.landing_page' => 'nullable|string|max:191',
            'referral.landing_params' => 'nullable|string|max:191',
            'referral.referral' => 'nullable|string|max:191',
            'referral.gclid' => 'nullable|string|max:191',
            'referral.fclid' => 'nullable|string|max:191',
            'referral.utm_source' => 'nullable|string|max:191',
            'referral.utm_campaign' => 'nullable|string|max:191',
            'referral.utm_medium' => 'nullable|string|max:191',
            'referral.utm_term' => 'nullable|string|max:191',
            'referral.utm_content' => 'nullable|string|max:191',
            'referral.referrer_url' => 'nullable|string',
            'referral.referrer_domain' => 'nullable|string|max:191',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        // Generate a unique order code if not provided
        if (!$this->has('code')) {
            $this->merge([
                'code' => 'ORD-' . time() . '-' . rand(1000, 9999),
            ]);
        }
    }
}