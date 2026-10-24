<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SectionFieldResource extends JsonResource
{
    private string $type;

    public function __construct($resource, string $type, private readonly bool $isEditable = false)
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
            'id' => $this->id,
            'field' => $field,
            'value' => $this->value,
            'model' => $this->model,
            'position' => $this->position,
        ];

        if ($this->type != 'create') {
            $data['is_editable'] = $this->type === 'update' || $this->isEditable;
        }

        return $data;
    }

}
