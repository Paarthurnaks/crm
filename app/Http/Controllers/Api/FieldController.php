<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fields\UpdateRequest;
use App\Http\Resources\FieldResource;
use App\Http\Resources\UserFieldResource;
use App\Models\Field;
use App\Models\UserField;
use App\Services\FieldService;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FieldController extends Controller
{
    public function __construct(private FieldService $fieldService) {}

    /**
     * @OA\Get(
     *     path="/api/fields/{entity}",
     *     operationId="getFieldsAndUserFields",
     *     tags={"Fields"},
     *     description="Метод для получения всех полей и пользовательских полей сущности",
     *     summary="Метод для получения всех полей и пользовательских полей сущности",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="entity",
     *          description="entity (deals, tasks, users, document_templates)",
     *          required=true,
     *          in="path",
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *     @OA\Parameter(
     *          name="type",
     *          description="type (view, create, update)",
     *          required=false,
     *          in="query",
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
     *                     property="fields",
     *                     ref="#/components/schemas/Field"
     *                 ),
     *                 @OA\Property(
     *                     property="user_fields",
     *                     ref="#/components/schemas/UserField"
     *                 ),
     *              )
     *          )
     *      ),
     *
     *     @OA\Response(response="404",
     *          description="NOT FOUND"
     *      ),
     * )
     */
    public function getFieldsAndUserFields(Request $request, string $entity): JsonResponse
    {
        $fields = $this->fieldService->getFieldsAndUserFields($entity, $request->all());

        $response = [
            'fields' => FieldResource::collection($fields['fields']),
            'user_fields' => UserFieldResource::collection($fields['user_fields']),
        ];

        return ResponseService::success($response);
    }

    /**
     * @OA\Put(
     *     path="/api/fields",
     *     summary="Update fields or user fields",
     *     description="Обновляет поле в зависимости от значения is_userfield",
     *     tags={"Fields"},
     *     security={{"bearer_token":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"is_userfield", "id", "field"},
     *
     *             @OA\Property(property="is_userfield", type="boolean", example=true),
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(
     *                 property="field",
     *                 type="object",
     *                 @OA\Property(property="is_required", type="boolean", example=true)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Успешное обновление",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="success")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Ошибка валидации",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 additionalProperties={
     *                     @OA\Property(type="array", @OA\Items(type="string"))
     *                 }
     *             )
     *         )
     *     )
     * )
     */
    public function updateFieldsOrUserFields(UpdateRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $field = $validated['is_userfield'] ?
            UserField::find($validated['id']) :
            Field::find($validated['id']);

        $this->fieldService->update($validated, $field);

        return ResponseService::success();
    }
}
