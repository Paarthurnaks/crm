<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
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
            "deal" => DealResource::make($this->deal),
            "sum" => $this->sum,
            "invoice_date" => $this->invoice_date,
            "author" => UserResource::make($this->author),
            "created_at" => $this->created_at,
            "updated_at" => $this->updated_at
        ];
    }
}
