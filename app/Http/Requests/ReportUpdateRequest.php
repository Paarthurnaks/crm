<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReportUpdateRequest extends FormRequest
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
            'title' => ['string'],
            'description' => ['string'],
            'period_from' => ['date_format:Y-m-d H:i:s'],
            'period_to' => ['date_format:Y-m-d H:i:s'],
            'filter' => [''],
            'user_access' => ['array'],
            'user_access.*' => ['integer', 'exists:users,id'],
            'report_fields' => ['array'],
            'report_fields.*.id' => ['integer'],
            'report_fields.*.field_id' => ['integer'],
            'report_fields.*.user_field_id' => ['integer'],
            'report_fields.*.position' => ['integer'],
        ];
    }
}
