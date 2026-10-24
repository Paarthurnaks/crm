<?php

namespace App\Http\Resources;

use App\Enums\DefaultRolesEnum;
use App\Enums\ProhibitedFieldEnum;
use App\Models\SectionField;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;

class SectionResource extends JsonResource
{
    public string $type;

    public function __construct($resource, string $type)
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
        $user = $request->user();
        $role = $user?->role;
        $isSuperAdmin = $user?->hasRole(DefaultRolesEnum::SUPER_ADMIN->value) ?? false;
        $isKnownType = in_array($this->type, ['view', 'create', 'update'], true);

        if (! $isKnownType) {
            Log::warning('Unknown section access type', ['section_id' => $this->id, 'type' => $this->type]);
        }

        $allowedFieldIds = [];
        $allowedUserFieldIds = [];
        $editableFieldIds = [];
        $editableUserFieldIds = [];

        if ($isKnownType && $role && ! $isSuperAdmin) {
            $allowedFieldIds = $role->fields()->wherePivot('type', $this->type)->pluck('fields.id')->all();
            $allowedUserFieldIds = $role->userFields()->wherePivot('type', $this->type)->pluck('user_fields.id')->all();

            if ($this->type === 'view') {
                $editableFieldIds = $role->fields()->wherePivot('type', 'update')->pluck('fields.id')->all();
                $editableUserFieldIds = $role->userFields()->wherePivot('type', 'update')->pluck('user_fields.id')->all();
            }
        }

        $fields = $this->fields->filter(function (SectionField $sectionField) use ($isKnownType, $isSuperAdmin, $allowedFieldIds, $allowedUserFieldIds): bool {
            if (! $isKnownType) {
                return false;
            }

            if ($this->type !== 'view' && in_array($sectionField->field?->code, ProhibitedFieldEnum::values(), true)) {
                return false;
            }

            return $isSuperAdmin
                || ($sectionField->field_id !== null && in_array($sectionField->field_id, $allowedFieldIds))
                || ($sectionField->user_field_id !== null && in_array($sectionField->user_field_id, $allowedUserFieldIds));
        })->values();

        Log::debug('Section fields filtered', [
            'section_id' => $this->id,
            'actor_id' => $user?->id,
            'type' => $this->type,
            'visible_count' => $fields->count(),
        ]);

        return [
            'id' => $this->id,
            'entity' => $this->entity,
            'main_section' => $this->main_section,
            'type' => $this->resource->type,
            'title' => $this->title,
            'position' => $this->position,
            'fields' => $fields->map(function (SectionField $field) use ($isSuperAdmin, $editableFieldIds, $editableUserFieldIds) {
                $isEditable = ! in_array($field->field?->code, ProhibitedFieldEnum::values(), true) && ($this->type !== 'view' || $isSuperAdmin
                    || ($field->field_id !== null && in_array($field->field_id, $editableFieldIds))
                    || ($field->user_field_id !== null && in_array($field->user_field_id, $editableUserFieldIds)));

                return new SectionFieldResource($field, $this->type, $isEditable);
            }),
        ];
    }
}
