<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [
            'email' => 'required|email:strict|unique:users,email,' . $this->route('id'),
            'role_id' => 'nullable|integer|exists:roles,id',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'avatar' => 'nullable|file|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ];

        if ($this->filled('old_password') || $this->filled('new_password')) {
            $rules['old_password'] = 'required|string';
            $rules['new_password'] = 'required|string|min:6';
            $rules['confirm_password'] = 'required|string|same:new_password';
        }

        return $rules;
    }

}