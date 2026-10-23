<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesStageResource extends JsonResource
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
            "color" => $this->color,
            "position" => $this->position,
            "type" => $this->type,
            "sales_tunnels" => SalesTunnelResource::collection($this->salesTunnels),
            "metrics" => $this->metrics
        ];
    }
}
