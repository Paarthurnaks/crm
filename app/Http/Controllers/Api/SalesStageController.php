<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Filters\DealFilter;
use App\Http\Filters\SalesStageFilter;
use App\Http\Requests\SalesStageStoreRequest;
use App\Http\Requests\SalesStageUpdateRequest;
use App\Http\Resources\ListResource;
use App\Http\Resources\SalesStageResource;
use App\Models\SalesStage;
use App\Services\ResponseService;
use App\Services\SalesStageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class SalesStageController extends Controller
{
    public function __construct(private SalesStageService $salesStageService) {}

    /**
     * @OA\Get(
     *     path="/api/sales-stages/",
     *     operationId="getSalesStages",
     *     tags={"SalesStage"},
     *     description="Метод для просмотра списка cтадий",
     *     summary="Метод для просмотра списка cтадий (канбан)",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="page",
     *          description="Номер страницы",
     *          required=false,
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
     *     @OA\Parameter(
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
     *     @OA\Parameter(
     *          name="title",
     *          description="фильтр по title",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="color",
     *          description="фильтр по color",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="position",
     *          description="фильтр по position",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="type",
     *          description="фильтр по type",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="sales_funnel_id",
     *          description="фильтр по sales_funnel_id",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string"
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
     *                     ref="#/components/schemas/SalesStage"
     *                 ),
     *              )
     *          ),
     *      )
     * )
     */
    public function index(Request $request, SalesStageFilter $filter): JsonResponse
    {
        $queryParams = $request->all();
        $salesStages = $this->salesStageService->getSalesStages($filter, $queryParams);
        $total = $salesStages->toArray()['total'];

        return ResponseService::success(
            ListResource::make(SalesStageResource::class, $salesStages, $total)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/sales-stages/{id}",
     *     operationId="getSalesStage",
     *     tags={"SalesStage"},
     *     description="Метод для просмотра этапа продаж",
     *     summary="Метод для просмотра этапа продаж",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="id",
     *          description="id этапа",
     *          required=false,
     *          in="path",
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="title",
     *          description="фильтр сделок по title (для метрик)",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="creator_id",
     *          description="фильтр сделок по creator_id (для метрик)",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="responsible_id",
     *          description="фильтр сделок по responsible_id (для метрик)",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="note",
     *          description="фильтр сделок по note (для метрик)",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="client",
     *          description="фильтр сделок по client (для метрик)",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="amount_from",
     *          description="фильтр сделок по amount от (для метрик)",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="amount_to",
     *          description="фильтр сделок по amount до (для метрик)",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *     @OA\Parameter(
     *          name="created_at_from",
     *          description="фильтр сделок по дате создания от (для метрик)",
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
     *          description="фильтр сделок по дате создания до (для метрик)",
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
     *          description="фильтр сделок по дате обновления от (для метрик)",
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
     *          description="фильтр сделок по дате обновления до (для метрик)",
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
     *                     ref="#/components/schemas/SalesStage"
     *                 ),
     *              )
     *          ),
     *      )
     * )
     */
    public function show(SalesStage $salesStage, DealFilter $filter): JsonResponse
    {
        $this->salesStageService->setStageMetrics($salesStage, $filter);

        return ResponseService::success(
            SalesStageResource::make($salesStage)
        );
    }

    /**
     * @OA\Post(
     *     path="/api/sales-stages/",
     *     operationId="createSalesStage",
     *     tags={"SalesStage"},
     *     description="Метод для создания новой стадии",
     *     summary="Метод для создания новой стадии",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\RequestBody(
     *
     *       @OA\MediaType(
     *           mediaType="application/json",
     *
     *           @OA\Schema(
     *              required={"sales_funnel_id", "title", "color", "position", "type"},
     *
     *              @OA\Property(property="sales_funnel_id", type="int64", example=1),
     *              @OA\Property(property="title", type="string", example="Новая сделка"),
     *              @OA\Property(property="color", type="string", example="#FFFFFF"),
     *              @OA\Property(property="position", type="string", example="1"),
     *              @OA\Property(property="type", type="string", example="default")
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
     *                     ref="#/components/schemas/SalesStage"
     *                 ),
     *              )
     *          )
     *      ),
     * )
     */
    public function store(SalesStageStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $stage = $this->salesStageService->createSalesStage($validated, $validated['sales_funnel_id']);

        return ResponseService::success(
            SalesStageResource::make($stage)
        );
    }

    /**
     * @OA\Put(
     *     path="/api/sales-stages/{id}",
     *     operationId="updateSalesStage",
     *     tags={"SalesStage"},
     *     description="Метод для обновления стадии",
     *     summary="Метод для обновления стадии",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="id",
     *          description="id стадии",
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
     *              @OA\Property(property="sales_funnel_id", type="int64", example=1),
     *              @OA\Property(property="title", type="string", example="Новая сделка"),
     *              @OA\Property(property="color", type="string", example="#FFFFFF"),
     *              @OA\Property(property="position", type="string", example="1"),
     *              @OA\Property(property="type", type="string", example="default")
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
     *                     ref="#/components/schemas/SalesStage"
     *                 ),
     *              )
     *          )
     *      ),
     * )
     */
    public function update(SalesStage $salesStage, SalesStageUpdateRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $stage = $this->salesStageService->updateSalesStage($salesStage, $validated);

        return ResponseService::success(
            SalesStageResource::make($stage)
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/sales-stages/{id}",
     *     operationId="deleteSalesStage",
     *     tags={"SalesStage"},
     *     description="Метод для удаления стадии",
     *     summary="Метод для удаления стадии",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="id",
     *          description="id стадии",
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
    public function destroy(SalesStage $salesStage): JsonResponse
    {
        $this->salesStageService->deleteSalesStage($salesStage);

        return ResponseService::success(message: 'Стадия успешно удалена');
    }
}
