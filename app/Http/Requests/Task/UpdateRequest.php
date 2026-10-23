<?php

namespace App\Http\Requests\Task;

use App\Enums\TaskStatusEnum;
use App\Enums\TaskTypeEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateRequest extends FormRequest
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
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'creator_id' => ['sometimes', 'integer', 'exists:users,id'],
            'deal_id' => ['sometimes', 'integer', 'exists:deals,id'],
            'title' => ['sometimes', 'string'],
            'status' => ['sometimes', 'string', new Enum(TaskStatusEnum::class)],
            'type' => ['sometimes', 'string', new Enum(TaskTypeEnum::class)],
            'description' => ['sometimes', 'string'],
            'attachments' => ['sometimes', 'array'],
            'attachments.*' => ['file'],
            'executors' => ['sometimes', 'array'],
            'executors.*' => ['integer', 'exists:users,id'],
            'observers' => ['sometimes', 'array'],
            'observers.*' => ['integer', 'exists:users,id'],
            'deadline' => ['sometimes', 'date_format:Y-m-d H:i:s'],
            'service' => ['string', 'max:255'],
            'email' => ['email', 'max:255'],
            'client_full_name' => ['string', 'max:255'],
            'docket_number' => ['integer', 'max:255'],
        ];
    }
}
