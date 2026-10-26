<?php

namespace App\OpenApi;

use OpenApi\Annotations as OA;

final class DocumentEndpoints
{
    /**
     * @OA\Get(
     *     path="/api/document-templates/",
     *     operationId="getDocumentTemplates",
     *     tags={"DocumentTemplate"},
     *     description="Метод для просмотра списка шаблонов документов",
     *     summary="Метод для просмотра списка шаблонов документов",
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
     *          description="фильтр по title (через оператор like)",
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
     *          description="фильтр по создателю шаблона",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="int"
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="entity",
     *          description="фильтр по сущности привязанной к шаблону",
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
     *                     ref="#/components/schemas/DocumentTemplate"
     *                 ),
     *              )
     *          ),
     *      )
     * )
     */
    public function index(): void {}

    /**
     * @OA\Get(
     *     path="/api/document-templates/{id}",
     *     operationId="getDocumentTemplate",
     *     tags={"DocumentTemplate"},
     *     description="Метод для детального просмотра шаблона документа",
     *     summary="Метод для детального просмотра шаблона документа",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="id",
     *          description="id шаблона",
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
     *
     *          @OA\MediaType(
     *              mediaType="application/json",
     *
     *              @OA\Schema(
     *
     *                 @OA\Property(
     *                     property="data",
     *                     ref="#/components/schemas/DocumentTemplate"
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
     *     path="/api/document-templates/",
     *     operationId="createDocumentTemplate",
     *     tags={"DocumentTemplate"},
     *     description="Метод для загрузки нового шаблона",
     *     summary="Метод для загрузки нового шаблона",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\RequestBody(
     *
     *       @OA\MediaType(
     *           mediaType="multipart/form-data",
     *
     *           @OA\Schema(
     *
     *              @OA\Property(property="title", type="string", example="new template"),
     *              @OA\Property(property="entity", type="string", example="deal"),
     *              @OA\Property(property="template", type="binary"),
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
     *                     ref="#/components/schemas/DocumentTemplate"
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
    public function store(): void {}

    /**
     * @OA\Put(
     *     path="/api/document-templates/{id}",
     *     operationId="updateDocumentTemplate",
     *     tags={"DocumentTemplate"},
     *     description="Метод для обновления шаблона",
     *     summary="Метод для обновления шаблона",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="id",
     *          description="id шаблона",
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
     *           mediaType="multipart/form-data",
     *
     *           @OA\Schema(
     *
     *              @OA\Property(property="title", type="string", example="new template"),
     *              @OA\Property(property="entity", type="string", example="deal"),
     *              @OA\Property(property="template", type="binary"),
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
     *                     ref="#/components/schemas/DocumentTemplate"
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
     *
     * @throws Exception
     */
    public function update(): void {}

    /**
     * @OA\Delete(
     *     path="/api/document-templates/{id}",
     *     operationId="deleteDocumentTemplate",
     *     tags={"DocumentTemplate"},
     *     description="Метод для удаления шаблона документа",
     *     summary="Метод для удаления шаблона документа",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="id",
     *          description="id",
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

    /**
     * @OA\Post(
     *     path="/api/document-templates/{templateId}/uploadNewTemplate",
     *     operationId="updateDocumentTemplateFile",
     *     tags={"DocumentTemplate"},
     *     description="Метод для загрузки нового docx файла в шаблон",
     *     summary="Метод для загрузки нового docx файла в шаблон",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\RequestBody(
     *
     *       @OA\MediaType(
     *           mediaType="multipart/form-data",
     *
     *           @OA\Schema(
     *
     *              @OA\Property(property="template", type="binary"),
     *           )
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
     *                     type="string",
     *                     example="document template has been updated"
     *                 ),
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(response="403",
     *          description="FORBIDDEN"
     *     ),
     *     @OA\Response(response="404",
     *          description="Not found"
     *     ),
     * )
     */
    public function updateTemplate(): void {}
}
