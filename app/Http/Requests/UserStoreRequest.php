<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            "name" => ["required", "string"],
            "surname" => ["required", "string"],
            "post" => ["string"],
            "email" => ["required", "string", "email", "unique:users"],
            "role_id" => ["integer", "exists:roles,id"],
            "division_id" => ["integer", "exists:divisions,id"],
            'avatar' => ['mimes:jpg,bmp,png'],
        ];
    }
}
