<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserTableSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SimplifyUserTableSettingsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_processes_multiple_chunks_and_is_idempotent(): void
    {
        $user = User::withoutEvents(fn (): User => User::create([
            'name' => 'Test',
            'surname' => 'User',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
        ]));
        $settings = json_encode([
            'table_columns' => [
                ['id' => 1, 'is_userfield' => false, 'name' => 'Old'],
                ['id' => 1, 'is_userfield' => false, 'name' => 'Duplicate'],
            ],
        ]);

        $rows = [];
        for ($index = 0; $index < 201; $index++) {
            $rows[] = [
                'entity' => 'entity_'.$index,
                'user_id' => $user->id,
                'settings' => $settings,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('user_table_settings')->insert($rows);

        $this->assertSame(0, Artisan::call('user-table-settings:simplify'));
        $first = UserTableSettings::query()->orderBy('id')->get();
        $this->assertCount(201, $first);
        $this->assertCount(1, $first->last()->settings['table_columns']);

        $this->assertSame(0, Artisan::call('user-table-settings:simplify'));
        $this->assertStringContainsString('updated 0', Artisan::output());
        $this->assertSame($first->last()->updated_at->toDateTimeString(), UserTableSettings::find($first->last()->id)->updated_at->toDateTimeString());
    }
}
