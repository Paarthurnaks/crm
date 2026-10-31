<?php

namespace App\Services;

use App\Enums\PermissionEntityEnum;
use App\Enums\PermissionTypeEnum;
use App\Enums\TaskActivityEnum;
use App\Enums\TaskStatusEnum;
use App\Enums\TaskTypeEnum;
use App\Http\Filters\TaskFilter;
use App\Http\Resources\CommentResource;
use App\Http\Resources\TaskEventResource;
use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskEventNotification;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TaskService
{
    private const ERROR_MESSAGES = [
        'createError' => 'Произошла ошибка во время создания задачи. Проверьте переданные вами поля',
    ];

    public function getList(TaskFilter $filter, array $queryParams, User $actor): LengthAwarePaginator
    {
        $limit = $queryParams['limit'] ?? config('constants.paginator.limit');
        Log::debug('task.list.started', ['actor_id' => $actor->id, 'limit' => $limit]);

        $taskScopes = [
            PermissionTypeEnum::ALL->value => 'allTasks',
            PermissionTypeEnum::OWN->value => 'ownTasks',
            PermissionTypeEnum::OPENED->value => 'openedTasks',
        ];

        $docketRequested = ($queryParams['type'] ?? null) === TaskTypeEnum::DOCKET->value;

        foreach ($taskScopes as $type => $scopeMethod) {
            $canViewDocket = $docketRequested && $actor->hasAccess(PermissionEntityEnum::DOCKET->value, 'view', $type);
            $canViewTasks = $actor->hasAccess(PermissionEntityEnum::TASKS->value, 'view', $type);
            if ($canViewDocket || $canViewTasks) {
                $tasks = $scopeMethod === 'ownTasks' ? Task::ownTasks($actor->id) : Task::$scopeMethod();

                return $tasks->filter($filter)->paginate($limit);
            }
        }

        return new LengthAwarePaginator([], 0, $limit);
    }

    /**
     * @throws Exception
     */
    public function create(array $data, User $actor): Task
    {
        $type = TaskTypeEnum::tryFrom($data['type'] ?? TaskTypeEnum::STANDARD->value)?->value;
        if ($type === null) {
            throw new \InvalidArgumentException('Недопустимый тип задачи');
        }

        try {
            $fields = $this->taskFields($data, $actor, $type);
            if ($type === TaskTypeEnum::HELPDESK->value) {
                $data['executors'] = $this->helpdeskExecutorIds($fields['creator_id']);
            }

            Log::debug('task.create.scenario_selected', ['actor_id' => $actor->id, 'type' => $type]);

            return DB::transaction(function () use ($fields, $data) {
                $task = Task::create($fields);

                if (isset($data['executors'])) {
                    $task->executors()->sync($data['executors']);
                    DB::afterCommit(fn () => $this->sendTaskEventNotifications($task, $data['executors']));
                }

                if (isset($data['observers'])) {
                    $task->observers()->sync($data['observers']);
                    DB::afterCommit(fn () => $this->sendTaskEventNotifications($task, $data['observers']));
                }

                $this->addAttachments($task, $data['attachments'] ?? []);

                return $task;
            });
        } catch (\Throwable $e) {
            Log::error('task.create.failed', ['actor_id' => $actor->id, 'exception' => $e::class]);
            throw new Exception($this::ERROR_MESSAGES['createError'], 0, $e);
        }
    }

    private function taskFields(array $data, User $actor, string $type): array
    {
        $fields = [
            'creator_id' => $data['creator_id'] ?? $actor->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => TaskStatusEnum::NEW->value,
            'type' => $type,
        ];

        if (isset($data['deal_id'])) {
            $fields['deal_id'] = $data['deal_id'];
        }

        if ($type === TaskTypeEnum::STANDARD->value || $type === TaskTypeEnum::DOCKET->value) {
            $fields['deadline'] = isset($data['deadline']) ? Carbon::parse($data['deadline'])->format('Y-m-d H:i:s') : null;
            $fields['start_time'] = isset($data['start_time']) ? Carbon::parse($data['start_time'])->format('Y-m-d H:i:s') : null;

            if ($fields['deadline'] !== null && Carbon::parse($fields['deadline'])->isPast()) {
                $fields['status'] = TaskStatusEnum::EXPIRED->value;
            }
        }

        if ($type === TaskTypeEnum::HELPDESK->value) {
            foreach (['docket_number', 'service', 'client_full_name', 'email'] as $field) {
                $fields[$field] = $data[$field] ?? null;
            }
        }

        return $fields;
    }

    private function helpdeskExecutorIds(int $creatorId): array
    {
        return User::whereHas('role.permissions', function ($query) {
            $query->where('entity', PermissionEntityEnum::HELPDESK->value)
                ->where('view', '!=', PermissionTypeEnum::FORBIDDEN->value);
        })->where('id', '!=', $creatorId)->pluck('id')->toArray();
    }

    public function sendTaskEventNotifications(Task $task, array $executors): void
    {
        $users = User::find($executors);
        $taskEvent = $task->events()->latest()->first();

        if ($taskEvent) {
            foreach ($users as $user) {
                try {
                    $user->notify(new TaskEventNotification($taskEvent));
                } catch (\Throwable $exception) {
                    Log::error('task.notification.failed', ['task_id' => $task->id, 'user_id' => $user->id, 'exception' => $exception::class]);
                }
            }
        }
    }

    public function update(Task $task, array $data): Task
    {
        $task->update($data);

        if (isset($data['executors'])) {
            $task->executors()->sync($data['executors']);
        }

        if (isset($data['observers'])) {
            $task->observers()->sync($data['observers']);
        }

        return $task;
    }

    public function delete(Task $task): void
    {
        $task->delete();
    }

    public function addAttachments(Task $task, array $attachments): void
    {
        $createdMedia = [];
        try {
            foreach ($attachments as $attachment) {
                $media = $task->addMedia($attachment)->toMediaCollection();
                $createdMedia[] = $media;
                Log::info('task.attachment.added', ['task_id' => $task->id, 'media_id' => $media->id]);
            }
        } catch (\Throwable $exception) {
            foreach ($createdMedia as $media) {
                $media->delete();
            }
            Log::error('task.attachment.add_failed', ['task_id' => $task->id, 'exception' => $exception::class]);
            throw $exception;
        }
    }

    public function deleteAttachment(Task $task, int $attachmentId): bool
    {
        $media = $task->media()->find($attachmentId);
        if (! $media) {
            Log::warning('task.attachment.missing', ['task_id' => $task->id, 'media_id' => $attachmentId]);

            return false;
        }

        $task->deleteMedia($media);
        Log::info('task.attachment.deleted', ['task_id' => $task->id, 'media_id' => $attachmentId]);

        return true;
    }

    public function getActivity(Task $task, array $queryParams): array
    {
        $comments = $this->getCommentsActivity($task, $queryParams);
        $events = $this->getEventsActivity($task, $queryParams);

        $result = collect();

        $result = $result->merge($comments->map(function ($comment) {
            return [
                'type' => TaskActivityEnum::COMMENT->value,
                'data' => new CommentResource($comment),
            ];
        }));

        $result = $result->merge($events->map(function ($event) {
            return [
                'type' => TaskActivityEnum::EVENT->value,
                'data' => new TaskEventResource($event),
            ];
        }));

        return $result->sortByDesc(function ($item) {
            return $item['data']->created_at;
        })->values()->all();
    }

    public function getCommentsActivity(Task $task, array $data): EloquentCollection
    {
        [$dateFrom, $dateTo] = $this->activityDateBounds($data);

        return $task->comments()
            ->with(['commentator', 'media'])
            ->approved()
            ->when(isset($data['author_id']), function ($query) use ($data) {
                return $query->where('user_id', $data['author_id']);
            })
            ->when(isset($data['search']), function ($query) use ($data) {
                return $query->where('comment', 'like', "%{$data['search']}%");
            })
            ->when($dateFrom !== null, function ($query) use ($dateFrom) {
                return $query->where('created_at', '>=', Carbon::parse($dateFrom)->startOfDay());
            })
            ->when($dateTo !== null, function ($query) use ($dateTo) {
                return $query->where('created_at', '<=', Carbon::parse($dateTo)->startOfDay());
            })
            ->orderByDesc('created_at')
            ->get();
    }

    private function getEventsActivity(Task $task, array $data): EloquentCollection
    {
        [$dateFrom, $dateTo] = $this->activityDateBounds($data);

        return $task->events()
            ->with('initiator')
            ->when(isset($data['author_id']), function ($query) use ($data) {
                return $query->where('initiator_id', $data['author_id']);
            })
            ->when($dateFrom !== null, function ($query) use ($dateFrom) {
                return $query->where('created_at', '>=', Carbon::parse($dateFrom)->startOfDay());
            })
            ->when($dateTo !== null, function ($query) use ($dateTo) {
                return $query->where('created_at', '<=', Carbon::parse($dateTo)->startOfDay());
            })
            ->orderByDesc('created_at')
            ->get();
    }

    private function activityDateBounds(array $data): array
    {
        return [
            $data['date_from'] ?? $data['dateFrom'] ?? null,
            $data['date_to'] ?? $data['dateTo'] ?? null,
        ];
    }

    public function addComment(Task $task, array $data): Comment
    {
        $text = $data['comment'] ?? '';
        $attachments = $data['attachments'] ?? [];

        return DB::transaction(function () use ($task, $text, $attachments) {
            $comment = $task->comment($text);

            $comment->approve();

            if ($attachments) {
                foreach ($attachments as $attachment) {
                    $comment->addMedia($attachment)
                        ->toMediaCollection();
                }
            }

            $comment->load(['commentator', 'media']);

            return $comment;
        });
    }
}
