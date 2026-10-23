<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentTemplateResource extends JsonResource
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
            'title' => $this->title,
            'creator' => UserResource::make($this->creator),
            'template' => $this->getMedia()?->last()?->getUrl(),
            'entity' => $this->entity,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
