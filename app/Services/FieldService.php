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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FieldService
{
    private const ERROR_MESSAGES = [
        'updateError' => 'Возникла ошибка при обновлении поля',
    ];

    public function getFieldsAndUserFields(string $entity, array $queryParams): array
    {
        $prohibited = ProhibitedFieldEnum::values();

        if (! isset($queryParams['type'])) {
            $queryParams['type'] = 'view';
        }

        //        if (Auth::user()->role->title == "Руководитель") {
        return [
            'fields' => Field::where('entity', $entity)->when($queryParams['type'] !== 'view', function ($query) use ($prohibited) {
                $query->whereNotIn('code', $prohibited);
            })->get(),
            'user_fields' => UserField::where('entity', $entity)->when($queryParams['type'] !== 'view', function ($query) use ($prohibited) {
                $query->whereNotIn('code', $prohibited);
            })->get(),
        ];
        //        } else {
        //            return [
        //                "fields" => Auth::user()->role->fields()
        //                    ->wherePivot("type", $queryParams["type"])
        //                    ->where("entity", $entity)
        //                    ->when($queryParams["type"] !== "view", function ($query) use ($prohibited) {
        //                        $query->whereNotIn("code", $prohibited);
        //                    })
        //                    ->get(),
        //                "user_fields" => Auth::user()->role->userFields()
        //                    ->wherePivot("type", $queryParams["type"] ?? "view")
        //                    ->where("entity", $entity)
        //                    ->when($queryParams["type"] !== "view", function ($query) use ($prohibited) {
        //                        $query->whereNotIn("code", $prohibited);
        //                    })
        //                    ->get(),
        //            ];
        //        }
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

        if ($field->is_required) {
            $this->addFieldToFunnels($field, $isUserfield);
        }

        $sections = Section::where('entity', SectionFieldEntityEnum::DEALS->value)
            ->where('is_template', true)
            ->where('main_section', true)
            ->whereDoesntHave('fields', function ($query) use ($field) {
                $query->where('field_id', $field->id)
                    ->orWhere('user_field_id', $field->id);
            })
            ->get();

        foreach ($sections as $section) {
            $sectionFieldsLastPosition = $section->fields()->max('position') ?? 0;

            $fieldIsExists = $section->fields()
                ->where(function ($query) use ($field) {
                    $query->where('field_id', $field->id)
                        ->orWhere('user_field_id', $field->id);
                })
                ->where('position', $sectionFieldsLastPosition + 1)
                ->exists();

            if (! $fieldIsExists) {
                SectionField::create([
                    'section_id' => $section->id,
                    'position' => $sectionFieldsLastPosition + 1,
                    'field_id' => $isUserfield ? null : $field->id,
                    'user_field_id' => $isUserfield ? $field->id : null,
                ]);
            }
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
        $roles = Role::all();

        foreach ($roles as $role) {
            $permissions = [
                'view' => $role->permissions()->where('entity', PermissionEntityEnum::DEALS->value)->where('view', '!=', PermissionTypeEnum::FORBIDDEN->value)->exists(),
                'update' => $role->permissions()->where('entity', PermissionEntityEnum::DEALS->value)->where('update', '!=', PermissionTypeEnum::FORBIDDEN->value)->exists(),
                'create' => $role->permissions()->where('entity', PermissionEntityEnum::DEALS->value)->where('create', '!=', PermissionTypeEnum::FORBIDDEN->value)->exists(),
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
