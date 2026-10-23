<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SalesStageStoreRequest extends FormRequest
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
            "sales_funnel_id" => ["required", "integer", "exists:sales_funnels,id"],
            "title" => ["required", "string"],
            "type" => ["required", "string"],
            "position" => ["required", "integer"],
            "color" => ["required", "string"]
        ];
    }
}
