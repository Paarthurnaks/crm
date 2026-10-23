<?php

namespace App\Http\Requests;

use App\Rules\Deal\ValidateClient;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class DealStoreUpdateRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(Request $request): array
    {
        return [
            'title' => ['string'],
            'sales_funnel_id' => ['integer', 'exists:sales_funnels,id'],
            'sales_stage_id' => ['integer', 'exists:sales_stages,id'],
            'responsible_id' => ['integer', 'exists:users,id'],
            'creator_id' => ['prohibited'],
            'note' => ['string', 'nullable'],
            'amount' => ['nullable', 'numeric', function ($attribute, $value, $fail) {
                $valueWithoutDot = str_replace('.', '', (string) $value);
                if (strlen($valueWithoutDot) > 50) {
                    $fail($attribute.' must not exceed 50 characters excluding the decimal point.');
                }
            }],
            'client_id' => ['nullable', new ValidateClient($request)],
            'user_field_values' => ['array'],
        ];
    }
}
