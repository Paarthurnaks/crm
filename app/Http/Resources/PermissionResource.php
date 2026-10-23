<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PermissionResource extends JsonResource
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
            'entity' => $this->entity,
            'view' => $this->view,
            'create' => $this->create,
            'update' => $this->update,
            'delete' => $this->delete,
            'import' => $this->import,
            'export' => $this->export,
        ];
    }
}
