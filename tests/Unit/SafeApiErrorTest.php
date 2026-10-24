<?php

namespace Tests\Unit;

use App\Services\ResponseService;
use App\Services\TaskService;
use App\Enums\TaskStatusEnum;
use App\Models\Task;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use ReflectionFunction;
use RuntimeException;
use Tests\TestCase;

class SafeApiErrorTest extends TestCase
{
    public function test_unexpected_error_does_not_expose_internal_details(): void
    {
        $response = ResponseService::fromException(
            new RuntimeException('Internal storage path: C:\\private\\document.docx'),
            'document.export'
        );

        $this->assertSame(500, $response->status());
        $this->assertStringNotContainsString('private', $response->getContent());
        $this->assertStringNotContainsString('document.docx', $response->getContent());
    }

    public function test_authorization_error_keeps_forbidden_status(): void
    {
        $response = ResponseService::fromException(new AuthorizationException('Sensitive policy name'), 'report.delete');

        $this->assertSame(403, $response->status());
        $this->assertStringNotContainsString('Sensitive policy name', $response->getContent());
    }

    public function test_invalid_task_type_is_rejected_before_database_work(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new TaskService)->create(['type' => 'unknown', 'title' => 'Example']);
    }

    public function test_missing_deadline_does_not_mark_new_task_as_expired(): void
    {
        Auth::shouldReceive('user')->once()->andReturn((object) ['id' => 1]);
        DB::shouldReceive('transaction')->once()->andReturnUsing(function ($callback) {
            $fields = (new ReflectionFunction($callback))->getStaticVariables()['fields'];

            return new Task($fields);
        });

        $task = (new TaskService)->create(['title' => 'Example']);

        $this->assertSame(TaskStatusEnum::NEW->value, $task->status);
        $this->assertNull($task->deadline);
    }
}
