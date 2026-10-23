<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserFieldValueResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $attachments = collect($this->getMedia())->map(function ($attachment) {
            return [
                'id' => $attachment->id,
                'url' => $attachment->original_url,
            ];
        });

        return [
            'id' => $this->id,
            'field' => UserFieldResource::make($this->userField),
            'entity_id' => $this->entity_id,
            'value' => $this->userField->type == 'file' ? $attachments : $this->value,
        ];
    }
}
