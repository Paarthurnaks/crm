<?php

namespace App\Http\Resources;

use App\Enums\DefaultRolesEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "id" => $this->id,
            "title" => $this->title,
            "original_title" => $this->original_title,
            'is_client' => $this->original_title === DefaultRolesEnum::CLIENT->value,
            'is_partner' => $this->original_title === DefaultRolesEnum::PARTNER->value,
            "permissions" => PermissionResource::collection($this->permissions),
            "available_disk_entities" => DiskEntityResource::collection($this->availableDiskEntities),
            "available_view_fields" => FieldResource::collection($this->fieldsView),
            "available_view_user_fields" => UserFieldResource::collection($this->userFieldsView),
            "available_create_fields" => FieldResource::collection($this->fieldsCreate),
            "available_create_user_fields" => UserFieldResource::collection($this->userFieldsCreate),
            "available_update_fields" => FieldResource::collection($this->fieldsUpdate),
            "available_update_user_fields" => UserFieldResource::collection($this->userFieldsUpdate)
        ];
    }
}
