<?php

namespace App\Http\Requests\UserField;

use App\Enums\UserFieldTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_field_id' => ['required', 'integer', Rule::exists('user_fields', 'id')->where('type', UserFieldTypeEnum::FILE->value)],
            'entity_id' => ['required', 'integer', 'min:1'],
            'attachments' => ['required', 'array', 'min:1'],
            'attachments.*' => ['required', 'file'],
        ];
    }
}
