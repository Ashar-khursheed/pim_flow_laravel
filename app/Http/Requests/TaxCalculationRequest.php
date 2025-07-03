<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TaxCalculationRequest extends FormRequest
{
    // Authorization logic
    public function authorize(): bool
    {
        return true; // Allow all users (you can add auth logic here)
    }

    // Validation rules
    public function rules(): array
    {
        return [
            'to_country' => 'required|string|size:2',
            'to_zip' => 'required|string|max:10',
            'amount' => 'required|numeric|min:0',
            // ... more rules
        ];
    }
}