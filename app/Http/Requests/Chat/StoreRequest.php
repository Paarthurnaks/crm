<?php

namespace App\Http\Requests\Chat;

use App\Enums\ChatTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(Request $request): array
    {
        $rules = [
            'type' => ['required', 'string', new Enum(ChatTypeEnum::class)],
            'description' => ['nullable', 'string', 'max:1000'],
            'user_ids.*' => ['required', 'distinct', 'integer', 'exists:users,id'],
        ];

        if ($request->input('type') === ChatTypeEnum::GROUP->value) {
            $rules['name'] = ['required', 'string', 'max:255'];
            $rules['avatar'] = ['sometimes', 'image'];
            $rules['user_ids'] = ['required', 'array', 'min:1'];
        } else {
            $rules['user_ids'] = ['required', 'array', 'size:1'];
        }

        return $rules;
    }
}
