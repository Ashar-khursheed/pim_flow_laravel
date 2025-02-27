<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\EcProductCategory;

class UpdateAttributeFamilyRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'sometimes|string|max:255',
            'category_id' => [
                'sometimes',
                'exists:ec_product_categories,id',
                function ($attribute, $value, $fail) {
                    $category = EcProductCategory::where('id', $value)->where('parent_id', 0)->exists();
                    if (!$category) {
                        $fail('The selected category_id must belong to a category with parent_id = 0.');
                    }
                }
            ],
        ];
    }
}
