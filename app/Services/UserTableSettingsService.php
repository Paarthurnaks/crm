<?php

namespace App\Services;

use App\Enums\DefaultRolesEnum;
use App\Models\UserTableSettings;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class UserTableSettingsService
{
    private readonly UserTableSettingsPresenter $presenter;

    public function __construct(?UserTableSettingsPresenter $presenter = null)
    {
        $this->presenter = $presenter ?? new UserTableSettingsPresenter;
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

    public function getUserTableSettings(array $queryParams, User $actor): null|Collection|UserTableSettings
    {
        if (! array_key_exists('entity', $queryParams)) {
            return UserTableSettings::where('user_id', $actor->id)->get();
        }

        $settingsRecord = UserTableSettings::firstOrCreate([
            'user_id' => $actor->id,
            'entity' => $queryParams['entity'],
        ], [
            'settings' => ['table_columns' => []],
        ]);

        // Существующие записи могут иметь NULL в nullable JSON-колонке settings.
        if ($settingsRecord->settings === null) {
            return null;
        }

        $settings = $settingsRecord->settings;
        $source = 'user';
        $template = null;
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
                $source = 'template';
            }
        }

        Log::debug('table_settings.source_selected', [
            'actor_id' => $actor->id,
            'entity' => $queryParams['entity'],
            'source' => $source,
            'template_id' => $template?->id ?? null,
        ]);

        $settingsRecord->settings = $this->presenter->present($settings, $actor);

        return $settingsRecord;
    }

    public function updateUserTableSettings(array $userTableSettingsData, User $actor): UserTableSettings
    {
        $settings = $this->simplifySettings($userTableSettingsData['settings']);
        $settingsRecord = UserTableSettings::updateOrCreate(
            ['user_id' => $actor->id, 'entity' => $userTableSettingsData['entity']],
            ['settings' => $settings]
        );

        $settingsRecord->settings = $this->presenter->present($settings, $actor);

        return $settingsRecord;
    }
}
