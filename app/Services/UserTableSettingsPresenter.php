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
        $settings = $this->filterForRole($this->removeInvalidColumns($settings, $user), $user);

        foreach (['table_columns', 'filters'] as $key) {
            if (isset($settings[$key]) && is_array($settings[$key])) {
                $settings[$key] = $this->enrichColumns($settings[$key], $user);
            }
        }

        return $settings;
    }

    private function removeInvalidColumns(array $settings, User $user): array
    {
        foreach (['table_columns', 'filters'] as $key) {
            if (! isset($settings[$key]) || ! is_array($settings[$key])) {
                continue;
            }

            $settings[$key] = array_values(array_filter($settings[$key], function ($column) use ($user, $key): bool {
                $valid = is_array($column) && isset($column['id'], $column['is_userfield']);
                if (! $valid) {
                    Log::warning('table_settings.column_invalid', ['actor_id' => $user->id, 'section' => $key]);
                }

                return $valid;
            }));
        }

        return $settings;
    }

    private function enrichColumns(array $columns, User $user): array
    {
        $items = collect($columns);
        $userFields = UserField::whereIn('id', $items->where('is_userfield', true)->pluck('id'))
            ->with('userFieldEnumValues')->get()->keyBy('id');
        $fields = Field::whereIn('id', $items->where('is_userfield', false)->pluck('id'))->get()->keyBy('id');

        $enriched = [];
        foreach ($items as $column) {
            $isUserField = (bool) $column['is_userfield'];
            $field = $isUserField ? $userFields->get($column['id']) : $fields->get($column['id']);

            if (! $field) {
                Log::warning('table_settings.field_unresolved', [
                    'actor_id' => $user->id,
                    'field_id' => (int) $column['id'],
                    'is_userfield' => $isUserField,
                ]);

                continue;
            }

            $enriched[] = $isUserField
                ? (new UserFieldResource($field))->toArray(request())
                : (new FieldResource($field))->toArray(request());
        }

        return $enriched;
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
