<?php

namespace App\Http\Controllers\Api;

use App\Enums\EntityTypeEnum;
use App\Enums\UserFieldTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Filters\UserFieldFilter;
use App\Http\Requests\UserField\StoreRequest;
use App\Http\Requests\UserField\UpdateRequest;
use App\Http\Resources\ListResource;
use App\Http\Resources\UserFieldResource;
use App\Http\Resources\UserFieldValueResource;
use App\Models\UserField;
use App\Models\UserFieldValue;
use App\Services\ResponseService;
use App\Services\UserFieldService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class UserFieldController extends Controller
{
    public function __construct(private readonly UserFieldService $service)
    {

    }

    /**
     * @OA\Get(
     *     path="/api/user-fields",
     *     operationId="getUserFields",
     *     tags={"UserField"},
     *     description="Получение списка пользовательских полей",
     *     summary="Получение списка пользовательских полей",
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *          name="page",
     *          description="Номер страницы",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="int",
     *              example=1
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="limit",
     *          description="Количество элементов на странице",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="int",
     *              default=50,
     *              example=50
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="entity",
     *          description="сущность к которой прикреплено пользовательское поле",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *              example="deal"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="name",
     *          description="название пользовательского поля (используя like)",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *              example="тестовое поле"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="type",
     *          description="тип поля",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string",
     *              example="integer"
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
     *     @OA\Response(response="200",
     *          description="OK",
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                        ref="#/components/schemas/UserField"
     *                     )
     *                 ),
     *              )
     *          ),
     *      )
     * )
     *
     * @param Request $request
     * @param UserFieldFilter $filter
     * @return JsonResponse
     */
    public function index(Request $request, UserFieldFilter $filter): JsonResponse
    {
        $queryParams = $request->all();
        $fields = $this->service->getList($filter, $queryParams);
        $total = $fields->toArray()["total"];

        return ResponseService::success(
            ListResource::make(UserFieldResource::class, $fields, $total)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/user-fields/{id}",
     *     operationId="getUserField",
     *     tags={"UserField"},
     *     description="Метод для получения конкретного пользовательского поля",
     *     summary="Метод для получения конкретного пользовательского поля",
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *          name="id",
     *          description="id пользовательского поля",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string",
     *              example=19
     *          )
     *      ),
     *     @OA\Response(response="200",
     *          description="OK",
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(
     *                 @OA\Property(
     *                     property="data",
     *                     ref="#/components/schemas/UserField"
     *                 ),
     *              )
     *          )
     *      ),
     *      @OA\Response(response="404",
     *          description="NOT FOUND"
     *      ),
     * )
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $userField = UserField::with('userFieldEnumValues')->find($id);

        if (!$userField) {
            return ResponseService::notFound(
                message: __('Пользовательское поле не найдено')
            );
        }

        return ResponseService::success(
            UserFieldResource::make($userField)
        );
    }

    /**
     * @OA\Post(
     *     path="/api/user-fields",
     *     operationId="createUserField",
     *     tags={"UserField"},
     *     description="Метод для создания пользовательского поля",
     *     summary="Метод для создания пользовательского поля",
     *     security={{"bearer_token":{}}},
     *     @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *           @OA\Schema(
     *              @OA\Property(property="name", type="string", example="тестовое поле"),
     *              @OA\Property(property="entity", type="string", example="Deal"),
     *              @OA\Property(property="type", type="string", example="integer"),
     *              @OA\Property(property="options", type="array", @OA\Items(
     *                 ref="#/components/schemas/UserFieldEnumValue"
     *              )),
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
     *                     ref="#/components/schemas/UserField"
     *                 ),
     *              )
     *          )
     *      ),
     *      @OA\Response(response="403",
     *          description="FORBIDDEN"
     *     ),
     * )
     *
     * @param StoreRequest $request
     * @return JsonResponse
     */
    public function store(StoreRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $userField = $this->service->create($validated);

            return ResponseService::created(
                UserFieldResource::make($userField)
            );
        } catch (Exception $e) {
            return ResponseService::badRequest(
                $e->getMessage()
            );
        }
    }

    /**
     * @OA\Put(
     *     path="/api/user-fields/{id}",
     *     operationId="updateUserField",
     *     tags={"UserField"},
     *     description="Метод для обновления пользовательского поля",
     *     summary="Метод для обновления пользовательского поля",
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *          name="id",
     *          description="id пользовательского поля",
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
     *              @OA\Property(property="name", type="string", example="тестовое поле"),
     *              @OA\Property(property="entity", type="string", example="Deal"),
     *              @OA\Property(property="type", type="string", example="integer"),
     *              @OA\Property(property="options", type="array", @OA\Items(
     *                 ref="#/components/schemas/UserFieldEnumValue"
     *              )),
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
     *                     ref="#/components/schemas/UserField"
     *                 ),
     *              )
     *          )
     *      ),
     *      @OA\Response(response="404",
     *          description="NOT FOUND"
     *      ),
     *      @OA\Response(response="403",
     *          description="FORBIDDEN"
     *     ),
     * )
     *
     * @param int $id
     * @param UpdateRequest $request
     * @return JsonResponse
     * @throws Exception
     */
    public function update(int $id, UpdateRequest $request): JsonResponse
    {
        try {
            $userField = UserField::find($id);

            if (!$userField) {
                return ResponseService::notFound(
                    message: __('Пользовательское поле не найдено')
                );
            }

            $validated = $request->validated();

            $userField = $this->service->update($userField, $validated);

            return ResponseService::success(
                UserFieldResource::make($userField)
            );
        } catch (\Throwable $e) {
            return ResponseService::badRequest(
                $e->getMessage()
            );
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/user-fields/{id}",
     *     operationId="deleteUserField",
     *     tags={"UserField"},
     *     description="Метод для удаления пользовательского поля",
     *     summary="Метод для удаления пользовательского поля",
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *          name="id",
     *          description="id пользовательского поля",
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
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $userField = UserField::find($id);

            if (!$userField) {
                return ResponseService::notFound(message: __('Пользовательское поле не найдено'));
            }

            $this->service->delete($userField);

            return ResponseService::success(message: __("Пользовательское поле удалено"));
        } catch (\Throwable $e) {
            return ResponseService::badRequest($e->getMessage());
        }
    }

    /**
     * Возвращает список доступных типов пользовательских полей
     * @OA\Get(
     *     path="/api/user-fields/types",
     *     operationId="getUserFieldTypes",
     *     tags={"UserField"},
     *     description="Метод для получения списка доступных типов пользовательских полей",
     *     summary="Метод для получения списка доступных типов пользовательских полей",
     *     security={{"bearer_token":{}}},
     *     @OA\Response(response="200",
     *          description="OK",
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(
     *                 @OA\Property(
     *                     property="data",
     *                     description="ответственные",
     *                     type="array",
     *                     @OA\Items(
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
     *      @OA\Response(response="404",
     *          description="NOT FOUND"
     *      ),
     * )
     * @return JsonResponse
     */
    public function types(): JsonResponse
    {
        $types = UserFieldTypeEnum::values();

        $response = [];

        foreach ($types as $type) {
            $response[] = [
                'code' => $type,
                'description' => trans('userFields.'.$type)
            ];
        }
        return ResponseService::success(collect($response));
    }

    /**
     * @OA\Post(
     *     path="/api/user-field-values/attachments",
     *     operationId="addAttachmentUF",
     *     tags={"UserField"},
     *     description="Метод для добавления вложений к пользовательскому полю",
     *     summary="Метод для добавления вложений к пользовательскому полю",
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *          name="user_field_id",
     *          description="id пользовательского поля",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string",
     *          )
     *      ),
     *     @OA\Parameter(
     *          name="entity_id",
     *          description="id сущности",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string",
     *          )
     *      ),
     *     @OA\RequestBody(
     *        @OA\MediaType(
     *           mediaType="muptipart/form-data",
     *           @OA\Schema(
     *              @OA\Property(
     *                  property="attachments",
     *                  type="array",
     *                  @OA\Items(
     *                      type="string",
     *                      format="file"
     *                  )
     *              )
     *           ),
     *       )
     *     ),
     *     @OA\Response(response="200",
     *          description="OK",
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(
     *                 @OA\Property(
     *                     property="data",
     *                     ref="#/components/schemas/UserFieldValue"
     *                 ),
     *              )
     *          )
     *      ),
     *      @OA\Response(response="404",
     *          description="NOT FOUND"
     *      ),
     *      @OA\Response(response="403",
     *          description="FORBIDDEN"
     *      ),
     * )
     */
    public function addAttachment(Request $request): JsonResponse
    {
        $queryParams = $request->all();
        if (isset($queryParams["user_field_id"]) && isset($queryParams["entity_id"])) {
            $userFieldValue = UserFieldValue::firstOrCreate([
                "entity_id" => $queryParams["entity_id"],
                "field_id" => $queryParams["user_field_id"]
            ],
            [
                "entity_id" => $queryParams["entity_id"],
                "field_id" => $queryParams["user_field_id"],
                "value" => null
            ]);

            if ($request->has("attachments")) {
                foreach ($request["attachments"] as $attachment) {
                    $userFieldValue->addMedia($attachment)->toMediaCollection();
                }
            }

            return ResponseService::success(
                UserFieldValueResource::make($userFieldValue)
            );
        }

        return ResponseService::unSuccess(message:"Не отправлено id сущности и пользовательского поля");
    }

    /**
     * @OA\Delete(
     *     path="/api/user-field-values/attachments/{attachmentId}",
     *     operationId="deleteAttachmentUF",
     *     tags={"UserField"},
     *     description="Метод для удаления вложения у пользовательского поля",
     *     summary="Метод для удаления вложения у пользовательского поля",
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *          name="attachmentId",
     *          description="id вложения",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string",
     *          )
     *      ),
     *     @OA\Parameter(
     *          name="user_field_id",
     *          description="id пользовательского поля",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string",
     *          )
     *      ),
     *     @OA\Parameter(
     *          name="entity_id",
     *          description="id сущности",
     *          required=true,
     *          in="path",
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
     *                     ref="#/components/schemas/UserFieldValue"
     *                 ),
     *              )
     *          )
     *      ),
     *      @OA\Response(response="404",
     *          description="NOT FOUND"
     *      ),
     *      @OA\Response(response="403",
     *          description="FORBIDDEN"
     *      ),
     * )
     */
    public function deleteAttachment(Request $request, int $attachmentId)
    {
        $queryParams = $request->all();
        $userFieldValue = UserFieldValue::where("entity_id", $queryParams["entity_id"])
                                        ->where("field_id", $queryParams["user_field_id"])
                                        ->first();
        $userFieldValue->deleteMedia($attachmentId);
        $userFieldValue->refresh();

        return ResponseService::success(
            UserFieldValueResource::make($userFieldValue)
        );
    }
}
