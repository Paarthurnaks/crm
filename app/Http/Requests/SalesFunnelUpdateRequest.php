<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SalesFunnelUpdateRequest extends FormRequest
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
            "stages" => ["array"],
            "stages.*.id" => ["integer", "exists:sales_stages,id"],
            "stages.*.title" => ["string"],
            "stages.*.type" => ["string"],
            "stages.*.position" => ["integer"],
            "stages.*.color" => ["string"],
            "fields" => ["array"],
            "fields.*.field_id" => ["integer", "exists:fields,id"],
            "fields.*.user_field_id" => ["integer", "exists:user_fields,id"],
        ];
    }
}
