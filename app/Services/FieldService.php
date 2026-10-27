<?php

namespace App\Services;

use App\Enums\PermissionEntityEnum;
use App\Enums\PermissionTypeEnum;
use App\Enums\ProhibitedFieldEnum;
use App\Enums\SectionFieldEntityEnum;
use App\Models\Field;
use App\Models\Role;
use App\Models\SalesFunnel;
use App\Models\Section;
use App\Models\SectionField;
use App\Models\UserField;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FieldService
{
    private const ERROR_MESSAGES = [
        'updateError' => 'Возникла ошибка при обновлении поля',
    ];

    /** @return array{fields: \Illuminate\Support\Collection, user_fields: \Illuminate\Support\Collection} */
    public function getFieldsAndUserFields(string $entity, array $queryParams, User $actor): array
    {
        $action = $queryParams['type'] ?? 'view';
        $fields = Field::where('entity', $entity);
        $userFields = UserField::where('entity', $entity);

        if (! FieldAccess::supports($action) || ! $actor->role) {
            Log::warning('field.list.denied', ['actor_id' => $actor->id, 'action' => $action]);

            return ['fields' => collect(), 'user_fields' => collect()];
        }

        if ($action !== 'view') {
            $prohibited = ProhibitedFieldEnum::values();
            $fields->whereNotIn('code', $prohibited);
            $userFields->whereNotIn('code', $prohibited);
        }

        if (! FieldAccess::isSuperAdmin($actor)) {
            $allowed = FieldAccess::allowedIds($actor, $action);
            $fields->whereIn('id', $allowed['fields']);
            $userFields->whereIn('id', $allowed['user_fields']);
        }

        $result = ['fields' => $fields->get(), 'user_fields' => $userFields->get()];
        Log::debug('field.list.loaded', ['actor_id' => $actor->id, 'action' => $action, 'count' => $result['fields']->count() + $result['user_fields']->count()]);

        return $result;
    }

    public function update(array $data, UserField|Field $field): void
    {
        try {
            DB::transaction(function () use ($data, $field) {
                $field->update($data['field']);
                $this->addRequiredFieldToTemplates($field);
                $this->setupRolesByField($field);
            });
        } catch (\Throwable $e) {
            throw new RuntimeException(self::ERROR_MESSAGES['updateError'], 0, $e);
        }
    }

    private function addRequiredFieldToTemplates(UserField|Field $field): void
    {
        $isUserfield = $field instanceof UserField;

        if (! $field->is_required) {
            return;
        }

        $this->addFieldToFunnels($field, $isUserfield);

        $sections = Section::where('entity', SectionFieldEntityEnum::DEALS->value)
            ->where('is_template', true)
            ->where('main_section', true)
            ->whereDoesntHave('fields', function ($query) use ($field, $isUserfield) {
                $query->where($isUserfield ? 'user_field_id' : 'field_id', $field->id);
            })
            ->get();

        foreach ($sections as $section) {
            $sectionFieldsLastPosition = $section->fields()->max('position') ?? 0;
            SectionField::create([
                'section_id' => $section->id,
                'position' => $sectionFieldsLastPosition + 1,
                'field_id' => $isUserfield ? null : $field->id,
                'user_field_id' => $isUserfield ? $field->id : null,
            ]);
        }
    }

    private function addFieldToFunnels(UserField|Field $field, bool $isUserfield): void
    {
        $funnels = SalesFunnel::all();

        foreach ($funnels as $funnel) {
            $relation = $isUserfield ? $funnel->userFields() : $funnel->fields();
            $fieldIsAlreadyExists = $relation->whereKey($field->id)->exists();

            if ($fieldIsAlreadyExists) {
                continue;
            }

            if ($isUserfield) {
                $funnel->userFields()->attach($field->id, ['sales_funnel_id' => $funnel->id]);
            } else {
                $funnel->fields()->attach($field->id, ['sales_funnel_id' => $funnel->id]);
            }

            Log::debug('Field added to funnel', ['field_id' => $field->id, 'funnel_id' => $funnel->id, 'is_user_field' => $isUserfield]);
        }
    }

    private function setupRolesByField(Field|UserField $field): void
    {
        $roles = Role::with(['permissions' => function ($query): void {
            $query->where('entity', PermissionEntityEnum::DEALS->value);
        }])->get();

        foreach ($roles as $role) {
            $permissions = [
                'view' => $role->permissions->contains(fn ($permission) => $permission->view !== null && $permission->view !== PermissionTypeEnum::FORBIDDEN->value),
                'update' => $role->permissions->contains(fn ($permission) => $permission->update !== null && $permission->update !== PermissionTypeEnum::FORBIDDEN->value),
                'create' => $role->permissions->contains(fn ($permission) => $permission->create !== null && $permission->create !== PermissionTypeEnum::FORBIDDEN->value),
            ];

            $isUserField = $field instanceof UserField;
            $relationMethod = $isUserField ? 'userFields' : 'fields';

            foreach ($permissions as $permissionType => $hasPermission) {
                if ($hasPermission) {
                    $existingPermission = $role->$relationMethod()
                        ->where($isUserField ? 'user_fields.id' : 'fields.id', $field->id)
                        ->wherePivot('type', $permissionType)
                        ->exists();

                    if (! $existingPermission) {
                        $role->$relationMethod()->attach($field->id, ['type' => $permissionType]);
                        Log::debug('Field permission added', ['field_id' => $field->id, 'role_id' => $role->id, 'action' => $permissionType]);
                    }
                }
            }
        }
    }
}
