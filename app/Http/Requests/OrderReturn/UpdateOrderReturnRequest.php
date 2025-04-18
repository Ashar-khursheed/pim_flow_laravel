<?php
// app/Http/Requests/OrderReturn/UpdateOrderReturnRequest.php
namespace App\Http\Requests\OrderReturn;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderReturnRequest extends FormRequest
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
            'reason' => 'nullable|string',
            'return_status' => 'nullable|string|max:191',
        ];
    }
}