<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserUpdateRequest extends FormRequest
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
            "name" => ["string"],
            "surname" => ["string"],
            "email" => ["string", "email", "unique:users"],
            "post" => ["string"],
            "password" => ["prohibited"],
            "role_id" => ["integer", "exists:roles,id"],
            "division_id" => ["integer", "exists:divisions,id"]
        ];
    }
}
