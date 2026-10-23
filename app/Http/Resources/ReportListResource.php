<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportListResource extends JsonResource
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
            "description" => $this->description,
            "period_from" => $this->period_from,
            "period_to" => $this->period_to,
            "author" => UserResource::make($this->author),
            "user_access" => UserResource::collection($this->userAccess),
            "created_at" => $this->created_at,
            "updated_at" => $this->updated_at
        ];
    }
}
