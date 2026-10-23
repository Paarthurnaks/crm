<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SalesTunnelStoreRequest extends FormRequest
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
            "from_stage_id" => ["integer", "exists:sales_stages,id"],
            "to_stage_id" => ["integer", "exists:sales_stages,id"]
        ];
    }
}
