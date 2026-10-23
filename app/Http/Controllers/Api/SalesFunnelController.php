<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Filters\DealFilter;
use App\Http\Filters\SalesFunnelFilter;
use App\Http\Requests\SalesFunnelStoreRequest;
use App\Http\Requests\SalesFunnelUpdateRequest;
use App\Http\Resources\ListResource;
use App\Http\Resources\SalesFunnelResource;
use App\Models\SalesFunnel;
use App\Services\ResponseService;
use App\Services\SalesFunnelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class SalesFunnelController extends Controller
{
    public function __construct(private readonly SalesFunnelService $salesFunnelService)
    {  }

    /**
     * @OA\Get(
     *     path="/api/sales-funnels/",
     *     operationId="getSalesFunnels",
     *     tags={"SalesFunnel"},
     *     description="Метод для просмотра воронок продаж",
     *     summary="Метод для просмотра воронок продаж",
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *          name="page",
     *          description="Номер страницы",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="int"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="limit",
     *          description="Максимальное количество элементов на странице",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="int"
     *          )
     *      ),
     *     @OA\Parameter(
     *          name="search",
     *          description="поиск",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *     @OA\Parameter(
     *          name="title",
     *          description="фильтр по title",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *     @OA\Parameter(
     *          name="created_at_from",
     *          description="фильтр по дате создания (от)",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *          )
     *      ),
     *     @OA\Parameter(
     *          name="created_at_to",
     *          description="фильтр по дате создания (до)",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *          )
     *      ),
     *     @OA\Parameter(
     *          name="updated_at_from",
     *          description="фильтр по дате обновления (от)",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *          )
     *      ),
     *     @OA\Parameter(
     *          name="updated_at_to",
     *          description="фильтр по дате обновления (до)",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *          )
     *      ),
     *     @OA\Parameter(
     *          name="title",
     *          description="фильтр сделок по title (для метрик)",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="creator_id",
     *          description="фильтр сделок по creator_id (для метрик)",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="responsible_id",
     *          description="фильтр сделок по responsible_id (для метрик)",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="note",
     *          description="фильтр сделок по note (для метрик)",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="client",
     *          description="фильтр сделок по client (для метрик)",
     *          required=false,
     *          in="query",
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
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *     @OA\Parameter(
     *          name="created_at_from",
     *          description="фильтр сделок по дате создания от (для метрик)",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *          )
     *      ),
     *     @OA\Parameter(
     *          name="created_at_to",
     *          description="фильтр сделок по дате создания до (для метрик)",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *          )
     *      ),
     *     @OA\Parameter(
     *          name="updated_at_from",
     *          description="фильтр сделок по дате обновления от (для метрик)",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *          )
     *      ),
     *     @OA\Parameter(
     *          name="updated_at_to",
     *          description="фильтр сделок по дате обновления до (для метрик)",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *          )
     *      ),
     *     @OA\Response(response="200",
     *          description="OK",
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(
     *                 @OA\Property(
     *                     property="data",
     *                     ref="#/components/schemas/SalesFunnel"
     *                 ),
     *              )
     *          ),
     *      )
     * )
     *
     * @param Request $request
     * @param SalesFunnelFilter $salesFunnelFilter
     * @param DealFilter $dealFilter
     * @return JsonResponse
     */
    public function index(Request $request, SalesFunnelFilter $salesFunnelFilter, DealFilter $dealFilter): JsonResponse
    {
        $queryParams = $request->all();
        $salesFunnels = $this->salesFunnelService->getSalesFunnels($salesFunnelFilter, $dealFilter, $queryParams);
        $total = $salesFunnels->toArray()["total"];

        return ResponseService::success(
            ListResource::make(SalesFunnelResource::class, $salesFunnels, $total)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/sales-funnels/{id}",
     *     operationId="getSalesFunnel",
     *     tags={"SalesFunnel"},
     *     description="Метод для детального просмотра воронки продаж",
     *     summary="Метод для детального просмотра воронки продаж",
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *          name="id",
     *          description="id воронки продаж",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *     @OA\Parameter(
     *          name="title",
     *          description="фильтр сделок по title (для метрик)",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="creator_id",
     *          description="фильтр сделок по creator_id (для метрик)",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="responsible_id",
     *          description="фильтр сделок по responsible_id (для метрик)",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="note",
     *          description="фильтр сделок по note (для метрик)",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="client",
     *          description="фильтр сделок по client (для метрик)",
     *          required=false,
     *          in="query",
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
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *     @OA\Parameter(
     *          name="created_at_from",
     *          description="фильтр сделок по дате создания от (для метрик)",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *          )
     *      ),
     *     @OA\Parameter(
     *          name="created_at_to",
     *          description="фильтр сделок по дате создания до (для метрик)",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *          )
     *      ),
     *     @OA\Parameter(
     *          name="updated_at_from",
     *          description="фильтр сделок по дате обновления от (для метрик)",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *          )
     *      ),
     *     @OA\Parameter(
     *          name="updated_at_to",
     *          description="фильтр сделок по дате обновления до (для метрик)",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *          )
     *      ),
     *     @OA\Response(response="200",
     *          description="OK",
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(
     *                 @OA\Property(
     *                     property="data",
     *                     ref="#/components/schemas/SalesFunnel"
     *                 ),
     *              )
     *          )
     *      ),
     *      @OA\Response(response="404",
     *          description="NOT FOUND"
     *      ),
     * )
     *
     * @param SalesFunnel $salesFunnel
     * @param DealFilter $dealFilter
     * @return JsonResponse
     */
    public function show(SalesFunnel $salesFunnel, DealFilter $dealFilter): JsonResponse
    {
        $this->salesFunnelService->getMetricsFromAllStages($salesFunnel, $dealFilter);

        return ResponseService::success(
            SalesFunnelResource::make($salesFunnel)
        );
    }

    /**
     * @OA\Post(
     *     path="/api/sales-funnels/",
     *     operationId="createSalesFunnel",
     *     tags={"SalesFunnel"},
     *     description="Метод для создания новой воронки продаж",
     *     summary="Метод для создания новой воронки продаж",
     *     security={{"bearer_token":{}}},
     *     @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *           @OA\Schema(
     *              required={"title", "position"},
     *              @OA\Property(property="title",type="string",example="Новая воронка"),
     *              @OA\Property(property="position", type="int64", example=1),
     *              @OA\Property(
     *                  property="stages",
     *                  type="array",
     *                  @OA\Items(
     *                      @OA\Property(property="title", type="string", example="Новая сделка"),
     *                      @OA\Property(property="color", type="string", example="#FFFFFF"),
     *                      @OA\Property(property="position", type="string", example="1"),
     *                      @OA\Property(property="type", type="string", example="default"),
     *                  ),
     *              )
     *           ),
     *       ),
     *     ),
     *     @OA\Response(response="200",
     *          description="OK",
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(
     *                 @OA\Property(
     *                     property="data",
     *                     ref="#/components/schemas/SalesFunnel"
     *                 ),
     *              )
     *          )
     *      ),
     * )
     *
     * @param SalesFunnelStoreRequest $request
     * @return JsonResponse
     */
    public function store(SalesFunnelStoreRequest $request): JsonResponse
    {

        try {
            $validated = $request->validated();
            $salesFunnel = $this->salesFunnelService->createSalesFunnel($validated);

            return ResponseService::created(
                SalesFunnelResource::make($salesFunnel)
            );
        } catch (AccessDeniedHttpException) {
            return ResponseService::forbidden(message: "Создавать направления может только администратор");
        } catch (BadRequestHttpException) {
            return ResponseService::badRequest(message: "Должны быть обязательно 1 обычная, 1 успешная и 1 неуспешная стадия");
        }
    }

    /**
     * @OA\Put(
     *     path="/api/sales-funnels/{id}",
     *     operationId="updateSalesFunnel",
     *     tags={"SalesFunnel"},
     *     description="Метод для обновления воронки продаж",
     *     summary="Метод для обновления воронки продаж",
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *          name="id",
     *          description="id воронки продаж",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *     @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *           @OA\Schema(
     *              @OA\Property(property="title",type="string",example="Новая воронка"),
     *              @OA\Property(property="position", type="string", example="1"),
     *              @OA\Property(
     *                  property="stages",
     *                  type="array",
     *                  @OA\Items(
     *                      @OA\Property(property="id", type="int64", example=1),
     *                      @OA\Property(property="title", type="string", example="Новая сделка"),
     *                      @OA\Property(property="color", type="string", example="#FFFFFF"),
     *                      @OA\Property(property="position", type="string", example="1"),
     *                      @OA\Property(property="type", type="string", example="default")
     *                  ),
     *              ),
     *              @OA\Property(
     *                   property="fields",
     *                   type="array",
     *                   @OA\Items(
     *                       @OA\Property(property="field_id", type="integer", example=1),
     *                   ),
     *                  @OA\Items(
     *                        @OA\Property(property="field_id", type="integer", example=1),
     *                   )
     *               )
     *           ),
     *       ),
     *     ),
     *     @OA\Response(response="200",
     *          description="OK",
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(
     *                 @OA\Property(
     *                     property="data",
     *                     ref="#/components/schemas/SalesFunnel"
     *                 ),
     *              )
     *          )
     *      ),
     *      @OA\Response(response="404",
     *          description="NOT FOUND"
     *      ),
     * )
     *
     * @param SalesFunnel $salesFunnel
     * @param SalesFunnelUpdateRequest $request
     * @return JsonResponse
     */
    public function update(SalesFunnel $salesFunnel, SalesFunnelUpdateRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            $salesFunnel = $this->salesFunnelService->updateSalesFunnel($salesFunnel, $validated);

            return ResponseService::success(
                SalesFunnelResource::make($salesFunnel)
            );
        } catch (AccessDeniedHttpException $e) {
            return ResponseService::forbidden(message: $e->getMessage());
        } catch (BadRequestHttpException $e) {
            return ResponseService::badRequest(message: $e->getMessage());
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/sales-funnels/{id}",
     *     operationId="deleteSalesFunnel",
     *     tags={"SalesFunnel"},
     *     description="Метод для удаления воронки продаж",
     *     summary="Метод для удаления воронки продаж",
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *          name="id",
     *          description="id воронки продаж",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
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
     *
     * @param SalesFunnel $salesFunnel
     * @return JsonResponse
     */
    public function destroy(SalesFunnel $salesFunnel): JsonResponse
    {
        try {
            $this->salesFunnelService->deleteSalesFunnel($salesFunnel);

            return ResponseService::success(message: "Направление успешно удалено");
        } catch (AccessDeniedHttpException $e) {
            return ResponseService::forbidden($e->getMessage());
        }
    }
}
