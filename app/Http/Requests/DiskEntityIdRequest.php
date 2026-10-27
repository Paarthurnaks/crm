<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DiskEntityIdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['disk_entity_id' => ['required', 'integer', 'min:1']];
    }
}
