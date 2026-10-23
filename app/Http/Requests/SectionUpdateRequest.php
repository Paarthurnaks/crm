<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SectionUpdateRequest extends FormRequest
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
            "title" => ["string"],
            "position" => ["integer"],
            "section_fields" => ["array"],
            "section_fields.*.id" => ["integer"],
            "section_fields.*.field_id" => ["integer", "exists:fields,id"],
            "section_fields.*.user_field_id" => ["integer", "exists:user_fields,id"],
            "section_fields.*.position" => ["integer"]
        ];
    }
}
