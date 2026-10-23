<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesFunnelResource extends JsonResource
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
            'position' => $this->position,
            'stages' => SalesStageResource::collection($this->sortedSalesStages()),
            'fields' => array_merge(
                FieldResource::collection($this->fields)->toArray(request()),
                UserFieldResource::collection($this->userFields)->toArray(request())
            ),
        ];
    }
}
