<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Filters\TaskFilter;
use App\Http\Requests\Task\AddCommentRequest;
use App\Http\Requests\Task\GetActivityRequest;
use App\Http\Requests\Task\StoreRequest;
use App\Http\Requests\Task\UpdateRequest;
use App\Http\Requests\TaskAddAttachmentRequest;
use App\Http\Resources\CommentResource;
use App\Http\Resources\ListResource;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Services\ResponseService;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TaskController extends Controller
{
    public function __construct(private readonly TaskService $service) {}

    public function index(Request $request, TaskFilter $filter): JsonResponse
    {
        $queryParams = $request->all();
        $tasks = $this->service->getList($filter, $queryParams, $request->user());
        $total = $tasks->toArray()['total'];

        return ResponseService::success(
            ListResource::make(TaskResource::class, $tasks, $total)
        );
    }

    public function show(int $id): JsonResponse
    {
        $task = Task::find($id);

        if (! $task) {
            return ResponseService::notFound(
                message: 'Задача не найдена'
            );
        }

        return ResponseService::success(
            TaskResource::make($task)
        );
    }

    public function store(StoreRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $task = $this->service->create($validated, $request->user());

            return ResponseService::created(
                TaskResource::make($task)
            );
        } catch (\Throwable $e) {
            return ResponseService::fromException($e, 'task.store');
        }
    }

    public function update(int $id, UpdateRequest $request): JsonResponse
    {
        $task = Task::find($id);

        if (! $task) {
            return ResponseService::notFound(
                message: __('Задача не найдена')
            );
        }

        $validated = $request->validated();

        $account = $this->service->update($task, $validated);

        return ResponseService::success(
            TaskResource::make($account)
        );
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $task = Task::find($id);

            if (! $task) {
                return ResponseService::notFound(message: __('Задача не найдена'));
            }

            $this->service->delete($task);

            return ResponseService::success(message: __('Задача удалена'));
        } catch (\Throwable $e) {
            return ResponseService::fromException($e, 'task.destroy');
        }
    }

    public function addComment(int $taskId, AddCommentRequest $request): JsonResponse
    {
        $task = Task::find($taskId);

        if (! $task) {
            return ResponseService::notFound(
                message: __('Задача не найдена')
            );
        }

        $validated = $request->validated();

        try {
            $comment = $this->service->addComment($task, $validated);

            return ResponseService::success(
                CommentResource::make($comment)
            );
        } catch (\Throwable $e) {
            return ResponseService::fromException($e, 'task.comment.store');
        }
    }

    public function getComments(int $taskId, Request $request): JsonResponse
    {
        $task = Task::find($taskId);

        if (! $task) {
            return ResponseService::notFound(
                message: __('Задача не найдена')
            );
        }

        $comments = $task->comments()->latest()->paginate(
            $request->input('limit', config('constants.paginator.limit'))
        );

        $total = $comments->toArray()['total'];

        return ResponseService::success(
            ListResource::make(CommentResource::class, $comments, $total)
        );
    }

    public function addAttachment(TaskAddAttachmentRequest $request, Task $task): JsonResponse
    {
        foreach ($request->validated('attachments') as $attachment) {
            $media = $task->addMedia($attachment)->toMediaCollection();
            Log::info('Task attachment added', ['task_id' => $task->id, 'media_id' => $media->id]);
        }

        return ResponseService::success(
            TaskResource::make($task)
        );
    }

    public function deleteAttachment(Task $task, int $attachmentId): JsonResponse
    {
        $media = $task->media()->find($attachmentId);
        if (! $media) {
            Log::warning('Task attachment not found', ['task_id' => $task->id, 'media_id' => $attachmentId]);

            return ResponseService::notFound();
        }

        $task->deleteMedia($media);
        Log::info('Task attachment deleted', ['task_id' => $task->id, 'media_id' => $attachmentId]);
        $task->refresh();

        return ResponseService::success(
            TaskResource::make($task)
        );
    }

    public function getActivity(Task $task, GetActivityRequest $request): JsonResponse
    {
        $queryParams = $request->all();
        $activity = $this->service->getActivity($task, $queryParams);

        $data = [
            'data' => collect($activity),
            'total' => collect($activity)->count(),
        ];

        return ResponseService::success($data);
    }
}
