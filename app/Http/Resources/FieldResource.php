<?php

namespace App\Http\Resources;

use App\Enums\TaskStatusEnum;
use App\Enums\TaskTypeEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FieldResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            "id" => $this->id,
            "is_userfield" => false,
            "entity" => $this->entity,
            "name" => $this->name,
            "code" => $this->code,
            "type" => $this->type,
            "is_required" => $this->is_required,
            "created_at" => $this->created_at,
            "updated_at" => $this->updated_at
        ];


        if($this->entity === "tasks" && $this->type === "enum") {
            if($this->code === "status") {
                $data["options"] = TaskStatusEnum::getOptions();
            } else if($this->code === "type") {
                $data["options"] = TaskTypeEnum::getOptions();
            }
        }

        return $data;
    }
}
