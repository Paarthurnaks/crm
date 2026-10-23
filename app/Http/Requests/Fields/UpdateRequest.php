<?php

namespace App\Http\Requests\Fields;

use App\Models\Field;
use App\Models\UserField;
use Illuminate\Foundation\Http\FormRequest;

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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'is_userfield' => ['required', 'boolean'],
            'id' => [
                'required',
                function ($attribute, $value, $fail) {
                    $isUserfield = $this->input('is_userfield');
                    if ($isUserfield) {
                        if (! UserField::where('id', $value)->exists()) {
                            $fail('The selected id is invalid in user_fields.');
                        }
                    } else {
                        if (! Field::where('id', $value)->exists()) {
                            $fail('The selected id is invalid in fields.');
                        }
                    }
                },
            ],
            'field' => ['required', 'array'],
            'field.is_required' => ['sometimes', 'boolean'],
        ];
    }
}
