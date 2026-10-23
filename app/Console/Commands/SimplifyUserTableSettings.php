<?php

namespace App\Console\Commands;

use App\Models\UserTableSettings;
use Illuminate\Console\Command;

class SimplifyUserTableSettings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user-table-settings:simplify';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Упрощает UserTableSettings, удаляя все поля, кроме is_userfield и id из table_columns и filters, а также удаляет дубликаты';

    private function simplifyColumns(array $columns): array
    {
        $uniqueColumns = collect($columns)->unique(function ($column) {
            return $column['id'].'-'.$column['is_userfield'];
        });

        return $uniqueColumns->map(function ($column) {
            return [
                'id' => $column['id'],
                'is_userfield' => $column['is_userfield'],
            ];
        })->values()->toArray();
    }

    /**
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

    public function handle(): void
    {
        $userTableSettingsRecords = UserTableSettings::all();

        foreach ($userTableSettingsRecords as $record) {
            $settings = $record->settings;
            if (empty($settings)) {
                continue;
            }
            $simplifiedSettings = $this->simplifySettings($settings);
            $record->settings = $simplifiedSettings;
            $record->save();
        }

        $this->info('UserTableSettings records have been simplified and duplicates removed.');
    }
}
