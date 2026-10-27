<?php

namespace Tests\Feature;

use App\Models\Field;
use App\Models\Report;
use App\Models\ReportField;
use App\Models\Role;
use App\Models\RoleUserField;
use App\Models\User;
use App\Models\UserField;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportFieldVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_visible_fields_are_shared_by_report_view_and_export_preparation(): void
    {
        $role = Role::create(['title' => 'Analyst', 'original_title' => 'analyst']);
        $actor = User::withoutEvents(fn (): User => User::create([
            'name' => 'Test',
            'surname' => 'User',
            'email' => 'report-fields@example.test',
            'password' => 'unused',
            'role_id' => $role->id,
        ]));
        $allowed = UserField::create(['name' => 'Allowed', 'entity' => 'deals', 'type' => 'string', 'code' => 'allowed']);
        $denied = UserField::create(['name' => 'Denied', 'entity' => 'deals', 'type' => 'string', 'code' => 'denied']);
        $system = Field::create(['name' => 'Title', 'entity' => 'deals', 'type' => 'string', 'code' => 'title']);
        $report = Report::create([
            'title' => 'Visibility',
            'period_from' => '2020-01-01 00:00:00',
            'period_to' => '2030-01-01 00:00:00',
            'filter' => [],
            'author_id' => $actor->id,
        ]);
        ReportField::create(['report_id' => $report->id, 'field_id' => $system->id, 'position' => 1]);
        ReportField::create(['report_id' => $report->id, 'user_field_id' => $allowed->id, 'position' => 2]);
        ReportField::create(['report_id' => $report->id, 'user_field_id' => $denied->id, 'position' => 3]);
        RoleUserField::insert(['role_id' => $role->id, 'user_field_id' => $allowed->id]);

        app(ReportService::class)->loadVisibleFields($report, $actor);

        $this->assertSame([$system->id], $report->fields->pluck('field_id')->filter()->values()->all());
        $this->assertSame([$allowed->id], $report->fields->pluck('user_field_id')->filter()->values()->all());
    }
}
