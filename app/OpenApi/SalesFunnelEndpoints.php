<?php

namespace App\OpenApi;

use OpenApi\Annotations as OA;

final class SalesFunnelEndpoints
{
    /**
     * @OA\Get(
     *     path="/api/sales-funnels/",
     *     operationId="getSalesFunnels",
     *     tags={"SalesFunnel"},
     *     description="Метод для просмотра воронок продаж",
     *     summary="Метод для просмотра воронок продаж",
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
     *     @OA\Parameter(
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
     *                     ref="#/components/schemas/SalesFunnel"
     *                 ),
     *              )
     *          ),
     *      )
     * )
     */
    public function index(): void {}

    /**
     * @OA\Get(
     *     path="/api/sales-funnels/{id}",
     *     operationId="getSalesFunnel",
     *     tags={"SalesFunnel"},
     *     description="Метод для детального просмотра воронки продаж",
     *     summary="Метод для детального просмотра воронки продаж",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="id",
     *          description="id воронки продаж",
     *          required=true,
     *          in="path",
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *     @OA\Parameter(
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
     *                     ref="#/components/schemas/SalesFunnel"
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
    public function show(): void {}

    /**
     * @OA\Post(
     *     path="/api/sales-funnels/",
     *     operationId="createSalesFunnel",
     *     tags={"SalesFunnel"},
     *     description="Метод для создания новой воронки продаж",
     *     summary="Метод для создания новой воронки продаж",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\RequestBody(
     *
     *       @OA\MediaType(
     *           mediaType="application/json",
     *
     *           @OA\Schema(
     *              required={"title", "position"},
     *
     *              @OA\Property(property="title",type="string",example="Новая воронка"),
     *              @OA\Property(property="position", type="int64", example=1),
     *              @OA\Property(
     *                  property="stages",
     *                  type="array",
     *
     *                  @OA\Items(
     *
     *                      @OA\Property(property="title", type="string", example="Новая сделка"),
     *                      @OA\Property(property="color", type="string", example="#FFFFFF"),
     *                      @OA\Property(property="position", type="string", example="1"),
     *                      @OA\Property(property="type", type="string", example="default"),
     *                  ),
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
     *                     ref="#/components/schemas/SalesFunnel"
     *                 ),
     *              )
     *          )
     *      ),
     * )
     */
    public function store(): void {}

    /**
     * @OA\Put(
     *     path="/api/sales-funnels/{id}",
     *     operationId="updateSalesFunnel",
     *     tags={"SalesFunnel"},
     *     description="Метод для обновления воронки продаж",
     *     summary="Метод для обновления воронки продаж",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="id",
     *          description="id воронки продаж",
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
     *              @OA\Property(property="title",type="string",example="Новая воронка"),
     *              @OA\Property(property="position", type="string", example="1"),
     *              @OA\Property(
     *                  property="stages",
     *                  type="array",
     *
     *                  @OA\Items(
     *
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
     *
     *                   @OA\Items(
     *
     *                       @OA\Property(property="field_id", type="integer", example=1),
     *                   ),
     *
     *                  @OA\Items(
     *
     *                        @OA\Property(property="field_id", type="integer", example=1),
     *                   )
     *               )
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
     *                     ref="#/components/schemas/SalesFunnel"
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
    public function update(): void {}

    /**
     * @OA\Delete(
     *     path="/api/sales-funnels/{id}",
     *     operationId="deleteSalesFunnel",
     *     tags={"SalesFunnel"},
     *     description="Метод для удаления воронки продаж",
     *     summary="Метод для удаления воронки продаж",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="id",
     *          description="id воронки продаж",
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
    public function destroy(): void {}
}
