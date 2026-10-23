<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserFieldEnumValuesResource extends JsonResource
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
            "field_id" => $this->field_id,
            "option" => $this->option,
            "sort_order" => $this->sort_order,
            "created_at" => $this->created_at,
            "updated_at" => $this->updated_at
        ];
    }
}
