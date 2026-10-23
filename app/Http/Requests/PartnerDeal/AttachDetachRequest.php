<?php

namespace App\Http\Requests\PartnerDeal;

use Illuminate\Foundation\Http\FormRequest;

class AttachDetachRequest extends FormRequest
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
            'client_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $clientId = $this->input('client_id');

            if (! auth()->user()->clients()->where('client_id', $clientId)->exists()) {
                $validator->errors()->add('client_id', 'Пользователь не является вашим клиентом');
            }
        });
    }
}
