<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SectionFieldResource extends JsonResource
{
    private string $type;
    public function __construct($resource, string $type)
    {
        parent::__construct($resource);
        $this->type = $type;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $field = $this->userField ? UserFieldResource::make($this->userField) : FieldResource::make($this->field);
        $data = [
            "id" => $this->id,
            "field" => $field,
            "value" => $this->value,
            "model" => $this->model,
            "position" => $this->position
        ];

        if ($this->type != "create") {
            $data["is_editable"] = $this->getIsEditable($field);
        }

        return $data;
    }

    public function getIsEditable($field): bool
    {
//        if (Auth::user()->role->title == "Руководитель")
//        {
            return true;
//        }
//
//        if ($this->userField) {
//            return in_array($field->id, $this->section->availableFieldsUpdate->pluck("user_field_id")->toArray());
//        } else {
//            return in_array($field->id, $this->section->availableFieldsUpdate->pluck("field_id")->toArray());
//        }
    }
}
