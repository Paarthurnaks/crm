<?php

namespace Tests\Feature;

use App\Enums\PermissionTypeEnum;
use App\Models\Permission;
use App\Models\Report;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportApiWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_report_crud_and_download_deny_unlisted_user(): void
    {
        $author = $this->user();
        $other = $this->user(false);
        $this->actingAs($author, 'api');

        $created = $this->postJson('/api/reports', [
            'title' => 'Private report',
            'filter' => [['key' => 'title', 'condition' => 'Равно', 'value' => 'No matching deal']],
            'report_fields' => [],
            'user_access' => [$author->id],
        ])->assertOk()->assertJsonPath('data.title', 'Private report');
        $reportId = $created->json('data.id');
        $this->assertDatabaseHas('reports', ['id' => $reportId, 'author_id' => $author->id]);
        $this->getJson('/api/reports/'.$reportId)->assertOk()->assertJsonPath('data.id', $reportId);
        $this->getJson('/api/reports')->assertOk()->assertJsonPath('data.total', 1);

        $this->actingAs($other, 'api')->getJson('/api/reports/'.$reportId)->assertForbidden();
        $this->getJson('/api/reports/'.$reportId.'/download')->assertForbidden();
        $this->putJson('/api/reports/'.$reportId, ['title' => 'Tampered'])->assertForbidden();
        $this->assertDatabaseHas('reports', ['id' => $reportId, 'title' => 'Private report']);

        $this->actingAs($author, 'api')->putJson('/api/reports/'.$reportId, [
            'title' => 'Updated report',
        ])->assertOk()->assertJsonPath('data.title', 'Updated report');
        $this->deleteJson('/api/reports/'.$reportId)->assertOk();
        $this->assertDatabaseMissing('reports', ['id' => $reportId]);
    }

    public function test_invalid_report_is_not_created(): void
    {
        $this->actingAs($this->user(), 'api');
        $this->postJson('/api/reports', ['title' => '', 'period_from' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors(['title', 'period_from', 'filter']);
        $this->assertSame(0, Report::count());
    }

    private function user(bool $canWrite = true): User
    {
        $role = Role::create(['title' => 'Analyst '.fake()->unique()->word()]);
        Permission::create([
            'role_id' => $role->id, 'entity' => 'analytics',
            'view' => PermissionTypeEnum::ALL->value,
            'create' => $canWrite ? PermissionTypeEnum::ALL->value : PermissionTypeEnum::FORBIDDEN->value,
            'update' => $canWrite ? PermissionTypeEnum::ALL->value : PermissionTypeEnum::FORBIDDEN->value,
            'delete' => $canWrite ? PermissionTypeEnum::ALL->value : PermissionTypeEnum::FORBIDDEN->value,
        ]);

        return User::withoutEvents(fn (): User => User::create([
            'name' => 'Report', 'surname' => 'Tester',
            'email' => fake()->unique()->safeEmail(), 'password' => 'password',
            'role_id' => $role->id,
        ]));
    }
}
