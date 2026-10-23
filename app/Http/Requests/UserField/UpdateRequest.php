<?php

namespace App\Http\Requests\UserField;

use App\Enums\EntityTypeEnum;
use App\Enums\UserFieldTypeEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
        $types = UserFieldTypeEnum::values();

        return [
            'entity' => ['string', new Enum(EntityTypeEnum::class)],
            'name' => ['string'],
            'type' => ['string', Rule::in($types)],
            'options' => [
                'required_if:type,'.UserFieldTypeEnum::ENUM->value,
                'array',
            ],
            'options.*.id' => ['integer'],
            'options.*.option' => ['required', 'string'],
            'options.*.sort_order' => ['required', 'integer'],
        ];
    }
}
