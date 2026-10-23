<?php

namespace App\Http\Requests\Task;

use App\Enums\TaskTypeEnum;
use App\Models\Task;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreRequest extends FormRequest
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
        $rules = [
            'creator_id' => ['sometimes', 'integer', 'exists:users,id'],
            'title' => ['required', 'string'],
            'description' => ['required', 'string', 'max:65535'],
            'attachments' => ['array'],
            'attachments.*' => ['file'],
            'type' => ['string', new Enum(TaskTypeEnum::class)],
        ];

        $type = $this->input('type', TaskTypeEnum::STANDARD->value);

        if($type === TaskTypeEnum::STANDARD->value || $type === TaskTypeEnum::DOCKET->value) {
            $rules['deadline'] = ['sometimes', 'date_format:Y-m-d H:i:s'];
            $rules['start_time'] = ['sometimes', 'date_format:Y-m-d H:i:s'];
            $rules['deal_id'] = ['sometimes', 'integer', 'exists:deals,id'];
            $rules['executors'] = ['sometimes', 'array'];
            $rules['executors.*'] = ['integer', 'exists:users,id'];
            $rules['observers'] = ['sometimes', 'array'];
            $rules['observers.*'] = ['integer', 'exists:users,id'];
        } else if($type === TaskTypeEnum::HELPDESK->value) {
            $rules['service'] = ['string', 'max:255'];
            $rules['email'] = ['email', 'max:255'];
            $rules['client_full_name'] = ['string', 'max:255'];
            $rules['docket_number'] = ['integer'];
        }

        return $rules;
    }
}
