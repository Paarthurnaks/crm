<?php

namespace App\Services;

use App\Http\Resources\FieldResource;
use App\Http\Resources\UserFieldResource;
use App\Models\Field;
use App\Models\UserField;
use App\Models\UserTableSettings;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class UserTableSettingsService
{
    private function processColumns(array $columns): array
    {
        $tableColumns = collect($columns);

        $userFieldIds = $tableColumns->where('is_userfield', true)->pluck('id');
        $fieldIds = $tableColumns->where('is_userfield', false)->pluck('id');

        $userFields = UserField::whereIn('id', $userFieldIds)->get()->map(function ($field) {
            return new UserFieldResource($field);
        });

        $fields = Field::whereIn('id', $fieldIds)->get()->map(function ($field) {
            return new FieldResource($field);
        });

        return $userFields->concat($fields)->toArray();
    }

    /**
     * Процесс, обратный simplySettings.
     * Получает все данные по полям is_userfield и id
     */
    private function processSettings(array $settings): array
    {
        if (! empty($settings['table_columns'])) {
            $settings['table_columns'] = $this->processColumns($settings['table_columns']);
        }

        if (isset($settings['filters'])) {
            $settings['filters'] = $this->processColumns($settings['filters']);
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

    /**
     * Процесс, обратный processSettings().
     * Урезает все лишнее, кроме is_userfield и id
     */
    private function simplifySettings(array $settings): array
    {
        if (isset($settings['table_columns'])) {
            $settings['table_columns'] = $this->simplifyColumns($settings['table_columns']);
        }

        if (isset($settings['filters'])) {
            $settings['filters'] = $this->simplifyColumns($settings['filters']);
        }

        return $settings;
    }

    public function getUserTableSettings(array $queryParams): null|Collection|UserTableSettings
    {
        if (array_key_exists('entity', $queryParams)) {
            $userTableSettings = UserTableSettings::firstOrCreate([
                'user_id' => Auth::user()->id,
                'entity' => $queryParams['entity'],
            ], [
                'settings' => ['table_columns' => []],
            ]);

            if (! isset($userTableSettings->settings)) {
                return null;
            }
            if (empty($userTableSettings->settings['table_columns'])) {
                $userTableSettings = UserTableSettings::firstOrCreate([
                    'user_id' => 1,
                    'entity' => $queryParams['entity'],
                ], [
                    'settings' => ['table_columns' => []],
                ]);
            }

            $userTableSettings->settings = $this->processSettings($userTableSettings->settings);

            $clearTableColumns = [];
            if (isset($userTableSettings->settings['table_columns'])) {
                foreach ($userTableSettings->settings['table_columns'] as $column) {
                    //                    if (in_array($column["id"], Auth::user()->role->fieldsView->pluck("id")->toArray()) && ($column["is_userfield"] == false)) {
                    $clearTableColumns[] = $column;
                    //                    }
                    //                    else if (in_array($column["id"], Auth::user()->role->userFieldsView->pluck("id")->toArray()) && ($column["is_userfield"] == false)) {
                    //                        $clearTableColumns[] = $column;
                    //                    }
                }

                //                if (Auth::user()->role->title != "Руководитель") {
                //                    $clearSettings = $userTableSettings->settings;
                //                    $clearSettings["table_columns"] = $clearTableColumns;
                //
                //                    $userTableSettings->update(["settings" => $clearSettings]);
                //                }
            }
            $clearTableFilters = [];
            if (isset($userTableSettings->settings['filters'])) {
                foreach ($userTableSettings->settings['filters'] as $column) {
                    if (in_array($column['id'], Auth::user()->role->fieldsView->pluck('id')->toArray()) && ($column['is_userfield'] == false)) {
                        $clearTableFilters[] = $column;
                    } elseif (in_array($column['id'], Auth::user()->role->userFieldsView->pluck('id')->toArray()) && ($column['is_userfield'] == false)) {
                        $clearTableFilters[] = $column;
                    }
                }

                //                if (Auth::user()->role->title != "Руководитель") {
                //                    $clearSettings = $userTableSettings->settings;
                //                    $clearSettings["table_columns"] = $clearTableColumns;
                //                    $clearSettings["filters"] = $clearTableFilters;
                //
                //                    $userTableSettings->update(["settings" => $clearSettings]);
                //                }
            }
        } else {
            $userTableSettings = UserTableSettings::where('user_id', Auth::user()->id)->get();
        }

        return $userTableSettings;
    }

    public function updateUserTableSettings(array $userTableSettingsData): UserTableSettings
    {
        $userTableSettingsData['settings'] = $this->simplifySettings($userTableSettingsData['settings']);

        $userTableSettings = UserTableSettings::updateOrCreate(['user_id' => Auth::user()->id, 'entity' => $userTableSettingsData['entity']],
            ['settings' => $userTableSettingsData['settings']]);

        $userTableSettings->settings = $this->processSettings($userTableSettings->settings);

        return $userTableSettings;
    }
}
