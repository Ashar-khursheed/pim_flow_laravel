<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TaxRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'zip' => 'required|string|max:10',
            'country' => 'string|size:2',
            'state' => 'string|max:3',
            'city' => 'string|max:255',
            'street' => 'string|max:255',
        ];
    }
}

class TaxCalculationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'to_country' => 'required|string|size:2',
            'to_zip' => 'required|string|max:10',
            'to_state' => 'string|max:3',
            'to_city' => 'string|max:255',
            'to_street' => 'string|max:255',
            'amount' => 'required|numeric|min:0',
            'shipping' => 'required|numeric|min:0',
            'from_country' => 'string|size:2',
            'from_zip' => 'string|max:10',
            'from_state' => 'string|max:3',
            'from_city' => 'string|max:255',
            'from_street' => 'string|max:255',
            'line_items' => 'array',
            'line_items.*.id' => 'string',
            'line_items.*.quantity' => 'integer|min:1',
            'line_items.*.product_tax_code' => 'string',
            'line_items.*.unit_price' => 'numeric|min:0',
            'line_items.*.discount' => 'numeric|min:0',
        ];
    }
}