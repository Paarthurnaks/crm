<?php

namespace App\Http\Requests;

use App\Models\DiskEntity;
use Illuminate\Foundation\Http\FormRequest;

class StoreFolderRequest extends FormRequest
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
            'title' => ['required', 'string'],
            'path' => ['required', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (DiskEntity::where('path', rtrim($this->input('path'), '/'))
                ->where('title', $this->input('title'))
                ->where('deleted', false)
                ->exists()) {
                $validator->errors()->add('title', __('Папка уже существует'));
            }
        });
    }
}
