<?php

namespace App\Http\Requests\UserField;

use Illuminate\Foundation\Http\FormRequest;

class DeleteAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_field_id' => ['required', 'integer', 'exists:user_fields,id'],
            'entity_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
