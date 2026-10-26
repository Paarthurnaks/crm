<?php

namespace App\Services;

use App\Enums\DefaultRolesEnum;
use App\Http\Resources\FieldResource;
use App\Http\Resources\UserFieldResource;
use App\Models\Field;
use App\Models\User;
use App\Models\UserField;
use Illuminate\Support\Facades\Log;

class UserTableSettingsPresenter
{
    public function present(array $settings, User $user): array
    {
        $settings = $this->filterForRole($settings, $user);

        foreach (['table_columns', 'filters'] as $key) {
            if (isset($settings[$key]) && is_array($settings[$key])) {
                $settings[$key] = $this->enrichColumns($settings[$key], $user);
            }
        }

        return $settings;
    }

    private function enrichColumns(array $columns, User $user): array
    {
        $items = collect($columns);
        $userFields = UserField::whereIn('id', $items->where('is_userfield', true)->pluck('id'))
            ->with('userFieldEnumValues')->get()->keyBy('id');
        $fields = Field::whereIn('id', $items->where('is_userfield', false)->pluck('id'))->get()->keyBy('id');

        return $items->map(function ($column) use ($fields, $userFields, $user) {
            if (! is_array($column) || ! isset($column['id'], $column['is_userfield'])) {
                Log::warning('table_settings.column_invalid', ['actor_id' => $user->id]);

                return null;
            }

            $isUserField = (bool) $column['is_userfield'];
            $field = $isUserField ? $userFields->get($column['id']) : $fields->get($column['id']);

            if (! $field) {
                Log::warning('table_settings.field_unresolved', [
                    'actor_id' => $user->id,
                    'field_id' => (int) $column['id'],
                    'is_userfield' => $isUserField,
                ]);

                return null;
            }

            return $isUserField
                ? (new UserFieldResource($field))->toArray(request())
                : (new FieldResource($field))->toArray(request());
        })->filter()->values()->toArray();
    }

    private function filterForRole(array $settings, User $user): array
    {
        if ($user->hasRole(DefaultRolesEnum::SUPER_ADMIN->value)) {
            return $settings;
        }

        $role = $user->role;
        $fieldIds = $role ? $role->fieldsView()->pluck('fields.id')->all() : [];
        $userFieldIds = $role ? $role->userFieldsView()->pluck('user_fields.id')->all() : [];

        foreach (['table_columns', 'filters'] as $key) {
            if (! isset($settings[$key]) || ! is_array($settings[$key])) {
                continue;
            }

            $settings[$key] = array_values(array_filter($settings[$key], function ($column) use ($fieldIds, $userFieldIds, $user, $key): bool {
                if (! is_array($column) || ! isset($column['id'], $column['is_userfield'])) {
                    Log::warning('table_settings.column_invalid', ['actor_id' => $user->id, 'section' => $key]);

                    return false;
                }

                $allowedIds = $column['is_userfield'] ? $userFieldIds : $fieldIds;
                $allowed = in_array((int) $column['id'], $allowedIds, true);

                if (! $allowed) {
                    Log::warning('table_settings.field_denied', [
                        'actor_id' => $user->id,
                        'field_id' => (int) $column['id'],
                        'is_userfield' => (bool) $column['is_userfield'],
                        'section' => $key,
                    ]);
                }

                return $allowed;
            }));
        }

        return $settings;
    }
}
