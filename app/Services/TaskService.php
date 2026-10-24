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
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TaskService
{
    private const ERROR_MESSAGES = [
        'createError' => 'Произошла ошибка во время создания задачи. Проверьте переданные вами поля',
    ];

    public function getList(TaskFilter $filter, array $queryParams = []): LengthAwarePaginator
    {
        $user = auth()->user();
        $limit = $queryParams['limit'] ?? config('constants.paginator.limit');

        $taskScopes = [
            PermissionTypeEnum::ALL->value => 'allTasks',
            PermissionTypeEnum::OWN->value => 'ownTasks',
            PermissionTypeEnum::OPENED->value => 'openedTasks',
        ];

        foreach ($taskScopes as $type => $scopeMethod) {
            // для судебных дел
            if (isset($queryParams['type']) &&
                $queryParams['type'] === TaskTypeEnum::DOCKET->value &&
                $user->hasAccess(PermissionEntityEnum::DOCKET->value, 'view', $type)) {
                return Task::$scopeMethod()
                    ->filter($filter)
                    ->paginate($limit);
            }

            // для всех остальных задач
            if ($user->hasAccess(PermissionEntityEnum::TASKS->value, 'view', $type)) {
                return Task::$scopeMethod()->filter($filter)->paginate($limit);
            }
        }

        return new LengthAwarePaginator([], 0, $limit);
    }

    /**
     * @throws Exception
     */
    public function create(array $data): Task
    {
        $type = TaskTypeEnum::tryFrom($data['type'] ?? TaskTypeEnum::STANDARD->value)?->value;
        if ($type === null) {
            throw new \InvalidArgumentException('Недопустимый тип задачи');
        }

        try {
            $user = Auth::user();

            $fields = [
                'creator_id' => $data['creator_id'] ?? $user->id,
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
                $data['executors'] = User::whereHas('role', function ($query) {
                    $query->whereHas('permissions', function ($subQuery) {
                        $subQuery->where('entity', PermissionEntityEnum::HELPDESK)
                            ->where('view', '!=', PermissionTypeEnum::FORBIDDEN->value);
                    });
                })->where('id', '!=', $fields['creator_id'])->pluck('id')->toArray();

                $fields['docket_number'] = $data['docket_number'] ?? null;
                $fields['service'] = $data['service'] ?? null;
                $fields['client_full_name'] = $data['client_full_name'] ?? null;
                $fields['email'] = $data['email'] ?? null;
            }

            return DB::transaction(function () use ($fields, $data) {
                $task = Task::create($fields);

                if (! $task) {
                    throw new Exception($this::ERROR_MESSAGES['createError']);
                }

                if (isset($data['executors'])) {
                    $task->executors()->sync($data['executors']);
                    $this->sendTaskEventNotifications($task, $data['executors']);
                }

                if (isset($data['observers'])) {
                    $task->observers()->sync($data['observers']);
                    $this->sendTaskEventNotifications($task, $data['observers']);
                }

                if (isset($data['attachments'])) {
                    foreach ($data['attachments'] as $attachment) {
                        $task->addMedia($attachment)->toMediaCollection();
                    }
                }

                return $task;
            });
        } catch (\Throwable $e) {
            throw new Exception($this::ERROR_MESSAGES['createError'], 0, $e);
        }
    }

    public function sendTaskEventNotifications(Task $task, array $executors): void
    {
        $users = User::find($executors);
        $taskEvent = $task->events()->latest()->first();

        if ($taskEvent) {
            foreach ($users as $user) {
                $user->notify(new TaskEventNotification($taskEvent));
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

    public function getActivity(Task $task, array $queryParams)
    {
        $comments = $this->getCommentsActivity($task, $queryParams);
        $events = $this->getEventsActivity($task, $queryParams);

        $result = [];

        if (! empty($comments)) {
            $result = collect($result)->merge($comments->map(function ($comment) {
                return [
                    'type' => TaskActivityEnum::COMMENT->value,
                    'data' => new CommentResource($comment),
                ];
            }));
        }

        if (! empty($events)) {
            $result = collect($result)->merge($events->map(function ($event) {
                return [
                    'type' => TaskActivityEnum::EVENT->value,
                    'data' => new TaskEventResource($event),
                ];
            }));
        }

        return $result->sortByDesc(function ($item) {
            return $item['data']->created_at;
        })->values()->all();
    }

    public function getCommentsActivity(Task $task, array $data)
    {
        return $task->comments()
            ->with(['commentator', 'media'])
            ->approved()
            ->when(isset($data['author_id']), function ($query) use ($data) {
                return $query->where('user_id', $data['author_id']);
            })
            ->when(isset($data['search']), function ($query) use ($data) {
                return $query->where('comment', 'like', "%{$data['search']}%");
            })
            ->when(isset($data['dateFrom']), function ($query) use ($data) {
                return $query->where('created_at', '>=', Carbon::parse($data['dateFrom'])->startOfDay());
            })
            ->when(isset($data['dateTo']), function ($query) use ($data) {
                return $query->where('created_at', '<=', Carbon::parse($data['dateTo'])->startOfDay());
            })
            ->orderByDesc('created_at')
            ->get();
    }

    private function getEventsActivity(Task $task, array $data): mixed
    {
        return $task->events()
            ->with('initiator')
            ->when(isset($data['author_id']), function ($query) use ($data) {
                return $query->where('initiator_id', $data['author_id']);
            })
            ->when(isset($data['date_from']), function ($query) use ($data) {
                return $query->where('created_at', '>=', Carbon::parse($data['date_from'])->startOfDay());
            })
            ->when(isset($data['date_to']), function ($query) use ($data) {
                return $query->where('created_at', '<=', Carbon::parse($data['date_to'])->startOfDay());
            })
            ->orderByDesc('created_at')
            ->get();
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
