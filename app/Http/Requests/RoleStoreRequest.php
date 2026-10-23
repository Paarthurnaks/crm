<?php

namespace App\Http\Requests;

use App\Enums\PermissionEntityEnum;
use App\Enums\PermissionTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class RoleStoreRequest extends FormRequest
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
            "title" => ["required", "string", "unique:roles"],
            "available_disk_entities" => ["array"],
            "available_disk_entities.*" => ["integer", "exists:disk_entities,id"],
            "available_view_fields" => ["array"],
            "available_view_fields.*" => ["integer"],
            "available_view_user_fields" => ["array"],
            "available_view_user_fields.*" => ["integer"],
            "available_create_fields" => ["array"],
            "available_create_fields.*" => ["integer"],
            "available_create_user_fields" => ["array"],
            "available_create_user_fields.*" => ["integer"],
            "available_update_fields" => ["array"],
            "available_update_fields.*" => ["integer"],
            "available_update_user_fields" => ["array"],
            "available_update_user_fields.*" => ["integer"],
            "permissions" => ["array"],
            "permissions.*.entity" => ["string"],
            "permissions.*.view" => ["string", new Enum(PermissionTypeEnum::class)],
            "permissions.*.create" => ["string", new Enum(PermissionTypeEnum::class)],
            "permissions.*.update" => ["string", new Enum(PermissionTypeEnum::class)],
            "permissions.*.delete" => ["string", new Enum(PermissionTypeEnum::class)],
            "permissions.*.import" => ["string", new Enum(PermissionTypeEnum::class)],
            "permissions.*.export" => ["string", new Enum(PermissionTypeEnum::class)],
        ];
    }
}
