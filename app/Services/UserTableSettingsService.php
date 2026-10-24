<?php

namespace App\Services;

use App\Enums\DefaultRolesEnum;
use App\Http\Resources\FieldResource;
use App\Http\Resources\UserFieldResource;
use App\Models\Field;
use App\Models\User;
use App\Models\UserField;
use App\Models\UserTableSettings;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class UserTableSettingsService
{
    private function processColumns(array $columns): array
    {
        $tableColumns = collect($columns);
        $userFields = UserField::whereIn('id', $tableColumns->where('is_userfield', true)->pluck('id'))
            ->with('userFieldEnumValues')->get()->keyBy('id');
        $fields = Field::whereIn('id', $tableColumns->where('is_userfield', false)->pluck('id'))->get()->keyBy('id');

        return $tableColumns->map(function ($column) use ($fields, $userFields) {
            $field = $column['is_userfield'] ? $userFields->get($column['id']) : $fields->get($column['id']);

            if (! $field) {
                return null;
            }

            return $column['is_userfield']
                ? (new UserFieldResource($field))->toArray(request())
                : (new FieldResource($field))->toArray(request());
        })->filter()->values()->toArray();
    }

    private function processSettings(array $settings): array
    {
        foreach (['table_columns', 'filters'] as $key) {
            if (isset($settings[$key]) && is_array($settings[$key])) {
                $settings[$key] = $this->processColumns($settings[$key]);
            }
        }

        return $settings;
    }

    private function simplifyColumns(array $columns): array
    {
        return collect($columns)->map(function ($column) {
            $result = [
                'id' => $column['id'],
                'is_userfield' => $column['is_userfield'],
            ];

            if (isset($column['options'])) {
                $result['options'] = $column['options'];
            }

            return $result;
        })->toArray();
    }

    private function simplifySettings(array $settings): array
    {
        foreach (['table_columns', 'filters'] as $key) {
            if (isset($settings[$key]) && is_array($settings[$key])) {
                $settings[$key] = $this->simplifyColumns($settings[$key]);
            }
        }

        return $settings;
    }

    public function getUserTableSettings(array $queryParams): null|Collection|UserTableSettings
    {
        $user = Auth::user();

        if (! array_key_exists('entity', $queryParams)) {
            return UserTableSettings::where('user_id', $user->id)->get();
        }

        $settingsRecord = UserTableSettings::firstOrCreate([
            'user_id' => $user->id,
            'entity' => $queryParams['entity'],
        ], [
            'settings' => ['table_columns' => []],
        ]);

        if (! isset($settingsRecord->settings)) {
            return null;
        }

        $settings = $settingsRecord->settings;
        if (empty($settings['table_columns'])) {
            $template = UserTableSettings::query()
                ->where('entity', $queryParams['entity'])
                ->whereHas('user.role', function ($query) {
                    $query->where('original_title', DefaultRolesEnum::SUPER_ADMIN->value);
                })
                ->orderBy('id')
                ->first();

            if ($template) {
                $settings = $template->settings;
                Log::debug('Table settings template selected', ['entity' => $queryParams['entity'], 'template_id' => $template->id]);
            }
        }

        $settingsRecord->settings = $this->processSettings($this->filterSettingsForRole($settings, $user));

        return $settingsRecord;
    }

    public function updateUserTableSettings(array $userTableSettingsData): UserTableSettings
    {
        $user = Auth::user();
        $settings = $this->simplifySettings($userTableSettingsData['settings']);
        $settingsRecord = UserTableSettings::updateOrCreate(
            ['user_id' => $user->id, 'entity' => $userTableSettingsData['entity']],
            ['settings' => $settings]
        );

        $settingsRecord->settings = $this->processSettings($this->filterSettingsForRole($settings, $user));

        return $settingsRecord;
    }

    private function filterSettingsForRole(array $settings, User $user): array
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

            $originalCount = count($settings[$key]);
            $settings[$key] = array_values(array_filter($settings[$key], function ($column) use ($fieldIds, $userFieldIds): bool {
                if (! is_array($column) || ! isset($column['id'], $column['is_userfield'])) {
                    return false;
                }

                $allowedIds = $column['is_userfield'] ? $userFieldIds : $fieldIds;

                return in_array((int) $column['id'], $allowedIds, true);
            }));

            Log::debug('Table settings filtered', [
                'actor_id' => $user->id,
                'section' => $key,
                'removed_count' => $originalCount - count($settings[$key]),
            ]);
        }

        return $settings;
    }
}
