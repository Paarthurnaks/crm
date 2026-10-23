<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DealResource extends JsonResource
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
            'sales_funnel' => SalesFunnelResource::make($this->salesFunnel),
            'sales_stage' => SalesStageResource::make($this->salesStage),
            'creator' => UserResource::make($this->creator),
            'responsible' => UserResource::make($this->responsible),
            'note' => $this->note,
            'amount' => $this->amount,
            'client' => $this->client instanceof User ? UserResource::make($this->client) : $this->client,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'user_field_values' => UserFieldValueResource::collection($this->userFieldValues),
        ];
    }
}
