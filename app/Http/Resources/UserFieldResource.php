<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserFieldResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'is_userfield' => true,
            'entity' => $this->entity,
            'name' => $this->name,
            'type' => $this->type,
            'code' => $this->code,
            'options' => UserFieldEnumValuesResource::collection($this->userFieldEnumValues),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'is_required' => $this->is_required,
        ];
    }
}
