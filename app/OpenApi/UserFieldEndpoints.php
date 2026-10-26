<?php

namespace App\OpenApi;

use OpenApi\Annotations as OA;

final class UserFieldEndpoints
{
    /**
     * @OA\Get(
     *     path="/api/user-fields",
     *     operationId="getUserFields",
     *     tags={"UserField"},
     *     description="Получение списка пользовательских полей",
     *     summary="Получение списка пользовательских полей",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="page",
     *          description="Номер страницы",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="int",
     *              example=1
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
     *              default=50,
     *              example=50
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="entity",
     *          description="сущность к которой прикреплено пользовательское поле",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string",
     *              example="deal"
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="name",
     *          description="название пользовательского поля (используя like)",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string",
     *              example="тестовое поле"
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="type",
     *          description="тип поля",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string",
     *              example="integer"
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
     *                        ref="#/components/schemas/UserField"
     *                     )
     *                 ),
     *              )
     *          ),
     *      )
     * )
     */
    public function index(): void {}

    /**
     * @OA\Get(
     *     path="/api/user-fields/{id}",
     *     operationId="getUserField",
     *     tags={"UserField"},
     *     description="Метод для получения конкретного пользовательского поля",
     *     summary="Метод для получения конкретного пользовательского поля",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="id",
     *          description="id пользовательского поля",
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
     *                     ref="#/components/schemas/UserField"
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
     *     path="/api/user-fields",
     *     operationId="createUserField",
     *     tags={"UserField"},
     *     description="Метод для создания пользовательского поля",
     *     summary="Метод для создания пользовательского поля",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\RequestBody(
     *
     *       @OA\MediaType(
     *           mediaType="application/json",
     *
     *           @OA\Schema(
     *
     *              @OA\Property(property="name", type="string", example="тестовое поле"),
     *              @OA\Property(property="entity", type="string", example="Deal"),
     *              @OA\Property(property="type", type="string", example="integer"),
     *              @OA\Property(property="options", type="array", @OA\Items(
     *                 ref="#/components/schemas/UserFieldEnumValue"
     *              )),
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
     *                     ref="#/components/schemas/UserField"
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
     *     path="/api/user-fields/{id}",
     *     operationId="updateUserField",
     *     tags={"UserField"},
     *     description="Метод для обновления пользовательского поля",
     *     summary="Метод для обновления пользовательского поля",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="id",
     *          description="id пользовательского поля",
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
     *              @OA\Property(property="name", type="string", example="тестовое поле"),
     *              @OA\Property(property="entity", type="string", example="Deal"),
     *              @OA\Property(property="type", type="string", example="integer"),
     *              @OA\Property(property="options", type="array", @OA\Items(
     *                 ref="#/components/schemas/UserFieldEnumValue"
     *              )),
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
     *                     ref="#/components/schemas/UserField"
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
     *     path="/api/user-fields/{id}",
     *     operationId="deleteUserField",
     *     tags={"UserField"},
     *     description="Метод для удаления пользовательского поля",
     *     summary="Метод для удаления пользовательского поля",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="id",
     *          description="id пользовательского поля",
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
     * Возвращает список доступных типов пользовательских полей
     *
     * @OA\Get(
     *     path="/api/user-fields/types",
     *     operationId="getUserFieldTypes",
     *     tags={"UserField"},
     *     description="Метод для получения списка доступных типов пользовательских полей",
     *     summary="Метод для получения списка доступных типов пользовательских полей",
     *     security={{"bearer_token":{}}},
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
     *                     description="ответственные",
     *                     type="array",
     *
     *                     @OA\Items(
     *
     *                        @OA\Property(
     *                            property="code",
     *                            example="integer"
     *                        ),
     *                        @OA\Property(
     *                            property="description",
     *                            example="Целое число"
     *                        )
     *                     )
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
    public function types(): void {}

    /**
     * @OA\Post(
     *     path="/api/user-field-values/attachments",
     *     operationId="addAttachmentUF",
     *     tags={"UserField"},
     *     description="Метод для добавления вложений к пользовательскому полю",
     *     summary="Метод для добавления вложений к пользовательскому полю",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="user_field_id",
     *          description="id пользовательского поля",
     *          required=true,
     *          in="path",
     *
     *          @OA\Schema(
     *              type="string",
     *          )
     *      ),
     *
     *     @OA\Parameter(
     *          name="entity_id",
     *          description="id сущности",
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
     *                     ref="#/components/schemas/UserFieldValue"
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
    public function addAttachment(): void {}

    /**
     * @OA\Delete(
     *     path="/api/user-field-values/attachments/{attachmentId}",
     *     operationId="deleteAttachmentUF",
     *     tags={"UserField"},
     *     description="Метод для удаления вложения у пользовательского поля",
     *     summary="Метод для удаления вложения у пользовательского поля",
     *     security={{"bearer_token":{}}},
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
     *     @OA\Parameter(
     *          name="user_field_id",
     *          description="id пользовательского поля",
     *          required=true,
     *          in="path",
     *
     *          @OA\Schema(
     *              type="string",
     *          )
     *      ),
     *
     *     @OA\Parameter(
     *          name="entity_id",
     *          description="id сущности",
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
     *                     ref="#/components/schemas/UserFieldValue"
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
    public function deleteAttachment(): void {}
}
