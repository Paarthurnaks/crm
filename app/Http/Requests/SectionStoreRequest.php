<?php

namespace App\Http\Requests;

use App\Enums\EntityTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class SectionStoreRequest extends FormRequest
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
            'entity' => ['required', 'string', new Enum(EntityTypeEnum::class)],
            'title' => ['string'],
            'position' => ['integer'],
            'deal_id' => ['integer', 'exists:deals,id'],
            'section_fields' => ['array'],
            'section_fields.*.field_id' => ['integer'],
            'section_fields.*.user_field_id' => ['integer'],
            'section_fields.*.position' => ['integer'],
            'sales_funnel_id' => ['integer', 'exists:sales_funnels,id'],
        ];
    }
}
