<?php
// app/Http/Requests/Order/UpdateOrderRequest.php
namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderRequest extends FormRequest
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
            'shipping_option' => 'nullable|string|max:60',
            'shipping_method' => 'nullable|string|max:60',
            'status' => 'nullable|string|max:120',
            'amount' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'shipping_amount' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'coupon_code' => 'nullable|string|max:120',
            'discount_amount' => 'nullable|numeric|min:0',
            'sub_total' => 'nullable|numeric|min:0',
            'is_confirmed' => 'nullable|boolean',
            'discount_description' => 'nullable|string|max:191',
            'is_finished' => 'nullable|boolean',
            'completed_at' => 'nullable|date',
            'payment_id' => 'nullable|exists:payments,id',
            'proof_file' => 'nullable|string|max:191',
            
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
        ];
    }
}