<?php

namespace App\Http\Resources;

use App\Enums\DefaultRolesEnum;
use App\Enums\ProhibitedFieldEnum;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SectionResource extends JsonResource
{
    public string $type;

    public function __construct($resource, $type)
    {
        $this->type = $type;
        parent::__construct($resource);
    }
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if ($this->type === "view") $availableFields = $this->availableFieldsView;
        else if ($this->type === "create") $availableFields = $this->availableFieldsCreate;
        else if ($this->type === "update") $availableFields = $this->availableFieldsUpdate;

//        if (auth()->user()->hasRole(DefaultRolesEnum::SUPER_ADMIN->value)) {
            if($this->type !== "view") {
                $availableFields = $this->fields()->whereDoesntHave('field', function ($query) {
                    $query->whereIn('code', ProhibitedFieldEnum::values());
                })->get();
            } else {
                $availableFields = $this->fields;
            }
//        }

        return [
            "id" => $this->id,
            "entity" => $this->entity,
            "main_section" => $this->main_section,
            "type" => $this->resource->type,
            "title" => $this->title,
            "position" => $this->position,
            "fields" => $availableFields->map(function ($field) {
                return new SectionFieldResource($field, $this->type);
            })
        ];
    }
}
