<?php
// app/Http/Requests/OrderReturn/StoreOrderReturnRequest.php
namespace App\Http\Requests\OrderReturn;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderReturnRequest extends FormRequest
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
            'order_id' => 'required|exists:ec_orders,id',
            'user_id' => 'nullable|exists:users,id',
            'reason' => 'required|string',
            
            // Validate return items
            'items' => 'required|array|min:1',
            'items.*.order_product_id' => 'required|integer',
            'items.*.product_id' => 'required|integer',
            'items.*.product_name' => 'required|string|max:191',
            'items.*.product_image' => 'nullable|string|max:191',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.reason' => 'nullable|string',
            'items.*.refund_amount' => 'nullable|numeric|min:0',
        ];
    }
}