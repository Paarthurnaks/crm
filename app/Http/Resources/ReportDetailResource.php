<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportDetailResource extends JsonResource
{   

    public function __construct(public $resource)
    {
    }
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $total = $this->deals ? $this->deals->count() : 0;
        return [
            "id" => $this->id,
            "title" => $this->title,
            "description" => $this->description,
            "period_from" => $this->period_from,
            "period_to" => $this->period_to,
            "deals" => ListResource::make(DealResource::class, $this->deals, $total),
            "fields" => ReportFieldsResource::collection($this->fields),
            "author" => UserResource::make($this->author),
            "user_access" => UserResource::collection($this->userAccess),
            "total" => $total
        ];
    }
}
