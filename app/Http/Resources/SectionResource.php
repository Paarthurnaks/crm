<?php

namespace App\Http\Resources;

use App\Enums\ProhibitedFieldEnum;
use App\Models\SectionField;
use App\Services\FieldAccess;
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
        $isSuperAdmin = FieldAccess::isSuperAdmin($user);
        $isKnownType = FieldAccess::supports($this->type);

        if (! $isKnownType) {
            Log::warning('Unknown section access type', ['section_id' => $this->id, 'type' => $this->type]);
        }

        $allowed = $isKnownType && $role && ! $isSuperAdmin
            ? FieldAccess::allowedIds($user, $this->type)
            : ['fields' => [], 'user_fields' => []];
        $editable = $this->type === 'view' && $role && ! $isSuperAdmin
            ? FieldAccess::allowedIds($user, 'update')
            : ['fields' => [], 'user_fields' => []];

        $fields = $this->fields->filter(function (SectionField $sectionField) use ($isKnownType, $isSuperAdmin, $allowed, $user): bool {
            if (! $isKnownType) {
                return false;
            }

            if ($this->type !== 'view' && in_array($sectionField->field?->code, ProhibitedFieldEnum::values(), true)) {
                return false;
            }

            $isAllowed = $isSuperAdmin
                || ($sectionField->field_id !== null && in_array($sectionField->field_id, $allowed['fields']))
                || ($sectionField->user_field_id !== null && in_array($sectionField->user_field_id, $allowed['user_fields']));

            if (! $isAllowed) {
                Log::warning('section.field.denied', ['actor_id' => $user?->id, 'section_field_id' => $sectionField->id, 'action' => $this->type]);
            }

            return $isAllowed;
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
            'fields' => $fields->map(function (SectionField $field) use ($isSuperAdmin, $editable) {
                $isEditable = ! in_array($field->field?->code, ProhibitedFieldEnum::values(), true) && ($this->type !== 'view' || $isSuperAdmin
                    || ($field->field_id !== null && in_array($field->field_id, $editable['fields']))
                    || ($field->user_field_id !== null && in_array($field->user_field_id, $editable['user_fields'])));

                return new SectionFieldResource($field, $this->type, $isEditable);
            }),
        ];
    }
}
