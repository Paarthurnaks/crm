<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaskEventResource;
use App\Services\TaskEventService;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;
use OpenApi\Annotations as OA;

class TaskEventController extends Controller
{
    public function __construct(private TaskEventService $taskEventService)
    {

    }

    /**
     * @OA\Get(
     *     path="/api/tasks/{taskId}/events",
     *     operationId="getTaskEvents",
     *     tags={"TaskEvent"},
     *     description="Метод для просмотра таймлайна задачи",
     *     summary="Метод для просмотра таймлайна задачи",
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *          name="taskId",
     *          description="id задачи",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *     @OA\Response(response="200",
     *          description="OK",
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(
     *                 @OA\Property(
     *                     property="data",
     *                     ref="#/components/schemas/TaskEvent"
     *                 ),
     *              )
     *          ),
     *      )
     * )
     *
     * @param int $taskId
     * @return JsonResponse
     */
    public function getTaskEvents(int $taskId): JsonResponse
    {
        $taskEvents = $this->taskEventService->getTaskEvents($taskId);

        return ResponseService::success(
            TaskEventResource::collection($taskEvents)
        );
    }
}
