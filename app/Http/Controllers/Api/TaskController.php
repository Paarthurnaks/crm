<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Filters\TaskFilter;
use App\Http\Requests\Task\AddCommentRequest;
use App\Http\Requests\Task\GetActivityRequest;
use App\Http\Requests\Task\StoreRequest;
use App\Http\Requests\Task\UpdateRequest;
use App\Http\Resources\CommentResource;
use App\Http\Resources\ListResource;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Services\ResponseService;
use App\Services\TaskService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class TaskController extends Controller
{
    public function __construct(private readonly TaskService $service) {}

    /**
     * @OA\Get(
     *     path="/api/tasks",
     *     operationId="getTasks",
     *     tags={"Task"},
     *     description="Получение списка задач",
     *     summary="Получение списка задач",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="page",
     *          description="Номер страницы",
     *          in="query",
     *
     *          @OA\Schema(
     *              type="int",
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="limit",
     *          description="Количество элементов на странице",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="int",
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="search",
     *          description="поиск",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="creator",
     *          description="id постановщика",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="int",
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="deal",
     *          description="id привязанной сделки",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="int",
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="executor",
     *          description="id ответственного",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="int",
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="deadline_from",
     *          description="фильтр по крайнему сроку (от)",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string",
     *          )
     *      ),
     *
     *     @OA\Parameter(
     *          name="deadline_to",
     *          description="фильтр по крайнему сроку (до)",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string",
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="status",
     *          description="фильтр по статусу (new, pending, paused, closed)",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string",
     *          )
     *      ),
     *
     *     @OA\Parameter(
     *          name="title",
     *          description="фильтр по заголовку",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string",
     *          )
     *      ),
     *
     *     @OA\Parameter(
     *          name="description",
     *          description="фильтр по описанию (через like)",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string",
     *          )
     *      ),
     *
     *     @OA\Parameter(
     *          name="created_at_from",
     *          description="фильтр по дате создания (от)",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string",
     *          )
     *      ),
     *
     *     @OA\Parameter(
     *          name="created_at_to",
     *          description="фильтр по дате создания (до)",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string",
     *          )
     *      ),
     *
     *     @OA\Parameter(
     *          name="updated_at_from",
     *          description="фильтр по дате обновления (от)",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string",
     *          )
     *      ),
     *
     *     @OA\Parameter(
     *          name="updated_at_to",
     *          description="фильтр по дате обновления (до)",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string",
     *          )
     *      ),
     *
     *     @OA\Response(response="200",
     *          description="OK",
     *
     *          @OA\MediaType(
     *              mediaType="application/json",
     *
     *              @OA\Schema(
     *
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *
     *                     @OA\Items(
     *                        ref="#/components/schemas/Task"
     *                     )
     *                 ),
     *              )
     *          ),
     *      )
     * )
     */
    public function index(Request $request, TaskFilter $filter): JsonResponse
    {
        $queryParams = $request->all();
        $tasks = $this->service->getList($filter, $queryParams);
        $total = $tasks->toArray()['total'];

        return ResponseService::success(
            ListResource::make(TaskResource::class, $tasks, $total)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/tasks/{id}",
     *     operationId="getTask",
     *     tags={"Task"},
     *     description="Метод для получения конкретной задачи",
     *     summary="Метод для получения конкретной задачи",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="id",
     *          description="id задачи",
     *          required=true,
     *          in="path",
     *
     *          @OA\Schema(
     *              type="string",
     *              example=19
     *          )
     *      ),
     *
     *     @OA\Response(response="200",
     *          description="OK",
     *
     *          @OA\MediaType(
     *              mediaType="application/json",
     *
     *              @OA\Schema(
     *
     *                 @OA\Property(
     *                     property="data",
     *                     ref="#/components/schemas/Task"
     *                 ),
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(response="404",
     *          description="NOT FOUND"
     *      ),
     * )
     */
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

    /**
     * @OA\Post(
     *     path="/api/tasks",
     *     operationId="createTask",
     *     tags={"Task"},
     *     description="Метод для создания задачи",
     *     summary="Метод для создания задачи",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\RequestBody(
     *
     *       @OA\MediaType(
     *           mediaType="muptipart/form-data",
     *
     *           @OA\Schema(
     *              required={"title", "executors"},
     *
     *              @OA\Property(property="title", type="string", example="242_test", description="Если type = helpdesk, то сюда фио"),
     *              @OA\Property(property="description", type="string", example="242_test"),
     *              @OA\Property(property="creator_id", type="int", example=98),
     *              @OA\Property(property="deadline", type="string", example="2023-02-01 15:00:00"),
     *              @OA\Property(property="client_full_name", type="string", example="Иванов Иван Иваныч"),
     *              @OA\Property(
     *                   property="service",
     *                   type="string",
     *                   example="IT Support",
     *                   description="Только при type = helpdesk"
     *               ),
     *               @OA\Property(
     *                   property="docket_number",
     *                   type="string",
     *                   example="D123456",
     *                   description="Только при type = helpdesk"
     *               ),
     *               @OA\Property(
     *                   property="email",
     *                   type="string",
     *                   format="email",
     *                   example="user@example.com",
     *                   description="Только при type = helpdesk"
     *               ),
     *              @OA\Property(
     *                  property="executors",
     *                  type="array",
     *
     *                  @OA\Items(
     *                      type="integer",
     *                      example=12
     *                  ),
     *                  description="При type = helpdesk передавать не нужно"
     *              ),
     *
     *              @OA\Property(
     *                   property="observers",
     *                   type="array",
     *
     *                   @OA\Items(
     *                       type="integer",
     *                       example=12
     *                   ),
     *                   description="При type = helpdesk передавать не нужно"
     *              ),
     *
     *              @OA\Property(
     *                   property="type",
     *                   type="string",
     *                   enum={"standard", "helpdesk", "docket"},
     *                   description="Тип задачи",
     *                   example="standard",
     *                   default="standard"
     *              ),
     *              @OA\Property(
     *                  property="attachments",
     *                  type="array",
     *
     *                  @OA\Items(
     *                      type="string",
     *                      format="file"
     *                  )
     *              )
     *           ),
     *       ),
     *     ),
     *
     *     @OA\Response(response="200",
     *          description="OK",
     *
     *          @OA\MediaType(
     *              mediaType="application/json",
     *
     *              @OA\Schema(
     *
     *                 @OA\Property(
     *                     property="data",
     *                     ref="#/components/schemas/Task"
     *                 ),
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(response="403",
     *          description="FORBIDDEN"
     *     ),
     * )
     */
    public function store(StoreRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $task = $this->service->create($validated);

            return ResponseService::created(
                TaskResource::make($task)
            );
        } catch (Exception $e) {
            return ResponseService::badRequest(
                $e->getMessage()
            );
        }
    }

    /**
     * @OA\Put(
     *     path="/api/tasks/{id}",
     *     operationId="updateTask",
     *     tags={"Task"},
     *     description="Метод для изменения задачи",
     *     summary="Метод для изменения задачи",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="id",
     *          description="id задачи",
     *          required=true,
     *          in="path",
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *     @OA\RequestBody(
     *
     *       @OA\MediaType(
     *           mediaType="application/json",
     *
     *           @OA\Schema(
     *
     *              @OA\Property(property="title", type="string", example="242_test"),
     *              @OA\Property(property="description", type="string", example="242_test"),
     *              @OA\Property(property="creator_id", type="int", example=98),
     *              @OA\Property(property="deadline", type="string", example="2023-02-01 15:00:00"),
     *              @OA\Property(property="start_time", type="string", example="2023-02-01 15:00:00"),
     *              @OA\Property(property="status", type="string", example="closed"),
     *              @OA\Property(property="client_full_name", type="string", example="Иванов Иван Иваныч"),
     *              @OA\Property(property="docket_number", type="int64", example="23"),
     *              @OA\Property(property="service", type="string", example="Автоматизация"),
     *              @OA\Property(property="email", type="string", example="example@gmail.com"),
     *              @OA\Property(
     *                  property="executors",
     *                  type="array",
     *
     *                  @OA\Items(
     *                      type="integer",
     *                      example=19
     *                  )
     *              ),
     *
     *              @OA\Property(
     *                    property="observers",
     *                    type="array",
     *
     *                    @OA\Items(
     *                        type="integer",
     *                        example=12
     *                    ),
     *                    description="При type = helpdesk передавать не нужно"
     *               ),
     *           ),
     *       ),
     *     ),
     *
     *     @OA\Response(response="200",
     *          description="OK",
     *
     *          @OA\MediaType(
     *              mediaType="application/json",
     *
     *              @OA\Schema(
     *
     *                 @OA\Property(
     *                     property="data",
     *                     ref="#/components/schemas/Task"
     *                 ),
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(response="404",
     *          description="NOT FOUND"
     *      ),
     *      @OA\Response(response="403",
     *          description="FORBIDDEN"
     *     ),
     * )
     */
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

    /**
     * @OA\Delete(
     *     path="/api/tasks/{id}",
     *     operationId="deleteTask",
     *     tags={"Task"},
     *     description="Метод для удаления задачи",
     *     summary="Метод для удаления задачи",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="id",
     *          description="id задачи",
     *          required=true,
     *          in="path",
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *     @OA\Response(response="200",
     *          description="OK",
     *     ),
     *     @OA\Response(response="404",
     *          description="NOT FOUND"
     *     ),
     *     @OA\Response(response="403",
     *          description="FORBIDDEN"
     *     ),
     * )
     */
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
            return ResponseService::badRequest($e->getMessage());
        }
    }

    /**
     * @OA\Post(
     *     path="/api/tasks/{id}/comments",
     *     operationId="addCommentForTask",
     *     tags={"Task"},
     *     description="Метод для добавления комментария к задаче",
     *     summary="Метод для добавления комментария к задаче",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="id",
     *          description="id задачи",
     *          required=true,
     *          in="path",
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *     @OA\RequestBody(
     *
     *       @OA\MediaType(
     *           mediaType="application/json",
     *
     *           @OA\Schema(
     *
     *              @OA\Property(property="comment", type="string", example="my first comment"),
     *              @OA\Property(property="attachments", type="array", @OA\Items(type="string", example="filename.jpg")),
     *           ),
     *       ),
     *     ),
     *
     *     @OA\Response(response="200",
     *          description="OK",
     *
     *          @OA\MediaType(
     *              mediaType="application/json",
     *
     *              @OA\Schema(
     *
     *                 @OA\Property(
     *                     property="data",
     *                     ref="#/components/schemas/Comment"
     *                 ),
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(response="404",
     *          description="NOT FOUND"
     *      ),
     *      @OA\Response(response="403",
     *          description="FORBIDDEN"
     *     ),
     * )
     */
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
            return ResponseService::badRequest($e->getMessage());
        }
    }

    /**
     * @OA\Get(
     *     path="/api/tasks/{id}/comments",
     *     operationId="getTasksComments",
     *     tags={"Task"},
     *     description="Метод для просмотра списка комментариев к задаче",
     *     summary="Метод для просмотра списка комментариев к задаче",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="page",
     *          description="Номер страницы",
     *          in="query",
     *
     *          @OA\Schema(
     *              type="int"
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="limit",
     *          description="Максимальное количество элементов на странице",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="int"
     *          )
     *      ),
     *
     *     @OA\Response(response="200",
     *          description="OK",
     *
     *          @OA\MediaType(
     *              mediaType="application/json",
     *
     *              @OA\Schema(
     *
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *
     *                     @OA\Items(
     *                          ref="#/components/schemas/Comment"
     *                      )
     *                 ),
     *              )
     *          ),
     *      )
     * )
     */
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

    /**
     * @OA\Post(
     *     path="/api/tasks/{taskId}/attachments",
     *     operationId="addAttachment",
     *     tags={"Task"},
     *     description="Метод для добавления вложения к задаче",
     *     summary="Метод для добавления вложения к задаче",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="id",
     *          description="id задачи",
     *          required=true,
     *          in="path",
     *
     *          @OA\Schema(
     *              type="string",
     *          )
     *      ),
     *
     *     @OA\RequestBody(
     *
     *        @OA\MediaType(
     *           mediaType="muptipart/form-data",
     *
     *           @OA\Schema(
     *
     *              @OA\Property(
     *                  property="attachments",
     *                  type="array",
     *
     *                  @OA\Items(
     *                      type="string",
     *                      format="file"
     *                  )
     *              )
     *           ),
     *       )
     *     ),
     *
     *     @OA\Response(response="200",
     *          description="OK",
     *
     *          @OA\MediaType(
     *              mediaType="application/json",
     *
     *              @OA\Schema(
     *
     *                 @OA\Property(
     *                     property="data",
     *                     ref="#/components/schemas/Task"
     *                 ),
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(response="404",
     *          description="NOT FOUND"
     *      ),
     *      @OA\Response(response="403",
     *          description="FORBIDDEN"
     *      ),
     * )
     */
    public function addAttachment(Request $request, Task $task)
    {
        if ($request->has('attachments')) {
            foreach ($request['attachments'] as $attachment) {
                $task->addMedia($attachment)->toMediaCollection();
            }
        }

        return ResponseService::success(
            TaskResource::make($task)
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/tasks/{taskId}/attachments/{attachmentId}",
     *     operationId="deleteAttachment",
     *     tags={"Task"},
     *     description="Метод для удаления вложения у задачи",
     *     summary="Метод для удаления вложения у задачи",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="taskId",
     *          description="id задачи",
     *          required=true,
     *          in="path",
     *
     *          @OA\Schema(
     *              type="string",
     *          )
     *      ),
     *
     *     @OA\Parameter(
     *          name="attachmentId",
     *          description="id вложения",
     *          required=true,
     *          in="path",
     *
     *          @OA\Schema(
     *              type="string",
     *          )
     *      ),
     *
     *     @OA\Response(response="200",
     *          description="OK",
     *
     *          @OA\MediaType(
     *              mediaType="application/json",
     *
     *              @OA\Schema(
     *
     *                 @OA\Property(
     *                     property="data",
     *                     ref="#/components/schemas/Task"
     *                 ),
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(response="404",
     *          description="NOT FOUND"
     *      ),
     *      @OA\Response(response="403",
     *          description="FORBIDDEN"
     *      ),
     * )
     */
    public function deleteAttachment(Task $task, int $attachmentId)
    {
        $task->deleteMedia($attachmentId);
        $task->refresh();

        return ResponseService::success(
            TaskResource::make($task)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/tasks/{id}/activity",
     *     operationId="getTaskActivity",
     *     tags={"Task"},
     *     description="Метод для просмотра всей активности сделки",
     *     summary="Метод для просмотра всей активности сделки",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="id",
     *          description="id задачи",
     *          in="path",
     *
     *          @OA\Schema(
     *              type="string",
     *              example="1"
     *          )
     *      ),
     *
     *     @OA\Parameter(
     *          name="search",
     *          description="Поиск по контексту. В случае задач это название. В случае комментария это контент",
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string",
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="author_id",
     *          description="Идентификатор автора",
     *          in="query",
     *
     *          @OA\Schema(
     *              type="int",
     *          )
     *      ),
     *
     *     @OA\Parameter(
     *          name="date_from",
     *          description="Дата создания событий (от)",
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string",
     *          )
     *      ),
     *
     *     @OA\Parameter(
     *          name="date_to",
     *          description="Дата создания событий (до)",
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string",
     *          )
     *      ),
     *
     *     @OA\Response(response="200",
     *          description="OK",
     *
     *          @OA\MediaType(
     *              mediaType="application/json",
     *
     *              @OA\Schema(
     *
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *
     *                     @OA\Items(
     *                          type="object",
     *
     *                          @OA\Property(
     *                             property="type",
     *                             type="string",
     *                             example="comment"
     *                          ),
     *                          @OA\Property(
     *                             property="data",
     *                             ref="#/components/schemas/Comment"
     *                          )
     *                      )
     *                 ),
     *              )
     *          ),
     *      )
     * )
     */
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
