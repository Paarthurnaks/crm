<?php

namespace App\Http\Requests\Document;

use App\Enums\EntityTypeEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadRequest extends FormRequest
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
    public function rules(): array
    {
        $entities = EntityTypeEnum::values();

        return [
            'entity' => ['required', 'string', Rule::in($entities)],
            'template' => [
                'required',
                'mimetypes:application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/docx,application/pdf',
            ],
            "title" => ['required', 'string'],
        ];
    }
}
