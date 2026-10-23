<?php

namespace App\Http\Requests\ChatMessage;

use Illuminate\Foundation\Http\FormRequest;

class IndexRequest extends FormRequest
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
            'direction' => ['sometimes', 'required', 'in:upward,downward,both'],
            'last_message_id' => ['sometimes', 'required', 'integer', 'exists:chat_messages,id'],
            'search_term' => ['nullable'],
        ];
    }
}
