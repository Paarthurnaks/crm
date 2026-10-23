<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportFieldsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $field = $this->userField ? UserFieldResource::make($this->userField) : FieldResource::make($this->field); 
        return [
            "id" => $this->id,
            "field" => $field,
            "position" => $this->position
        ];
    }
}
