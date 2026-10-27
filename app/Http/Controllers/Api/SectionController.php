<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Filters\SectionFilter;
use App\Http\Requests\SectionStoreRequest;
use App\Http\Requests\SectionUpdateRequest;
use App\Http\Resources\SectionResource;
use App\Models\Section;
use App\Services\ResponseService;
use App\Services\SectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SectionController extends Controller
{
    public function __construct(private readonly SectionService $sectionService) {}

    /**
     * @OA\Get(
     *     path="/api/sections/{entity}/{id}",
     *     operationId="getSection",
     *     tags={"Section"},
     *     description="Метод для просмотра разделов конкретной сущности текущего пользователя",
     *     summary="Метод для просмотра разделов конкретной сущности текущего пользователя",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="entity",
     *          description="entity (deals)",
     *          required=true,
     *          in="path",
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="id",
     *          description="id в сущности (dealId)",
     *          required=true,
     *          in="path",
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
     *              type="int64"
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="type",
     *          description="тип полей раздела",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="user_id",
     *          description="фильтр по user_id",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="int64"
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
     *           name="sales_funnel_id",
     *           description="фильтр по воронке",
     *           required=false,
     *           in="query",
     *
     *           @OA\Schema(
     *               type="int64",
     *           )
     *       ),
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
     *                     ref="#/components/schemas/Section"
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
    public function getSectionsByEntity(Request $request, string $entity, int $id, SectionFilter $filter): JsonResponse
    {
        $queryParams = $request->all();

        $sections = $this->sectionService->getSectionsByEntity($entity, $id, $filter, $queryParams, $request->user());

        $serializedData = $sections->map(function ($section) use ($queryParams) {
            return new SectionResource($section, $queryParams['type'] ?? 'view');
        });

        return ResponseService::success(
            $serializedData
        );
    }

    /**
     * @OA\Post(
     *     path="/api/sections/",
     *     operationId="createSection",
     *     tags={"Section"},
     *     description="Метод для создания нового раздела",
     *     summary="Метод для создания нового раздела",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="entity_id",
     *          description="id сущности",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *     @OA\Parameter(
     *          name="from_create_page",
     *          description="раздел создается со страницы создания",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="bool"
     *          )
     *      ),
     *
     *     @OA\RequestBody(
     *
     *       @OA\MediaType(
     *           mediaType="application/json",
     *
     *           @OA\Schema(
     *              required={"entity"},
     *
     *              @OA\Property(property="entity",type="string",example="deals"),
     *              @OA\Property(property="title", type="string", example="my section"),
     *              @OA\Property(property="position", type="int64", example=1),
     *              @OA\Property(property="sales_funnel_id", type="int64", example=1),
     *              @OA\Property(
     *                  property="section_fields",
     *                  type="array",
     *
     *                  @OA\Items(
     *
     *                      @OA\Property(property="field_id", type="int64", example=4),
     *                      @OA\Property(property="user_field_id", type="int64", example=7),
     *                      @OA\Property(property="position", type="int64", example=1),
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
     *                     ref="#/components/schemas/Section"
     *                 ),
     *              )
     *          )
     *      ),
     * )
     */
    public function store(SectionStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $section = $this->sectionService->createSection($validated, $validated, $request->user());

        return ResponseService::created(
            SectionResource::make($section, 'view')
        );
    }

    /**
     * @OA\Put(
     *     path="/api/sections/{id}",
     *     operationId="updateSection",
     *     tags={"Section"},
     *     description="Метод для обновления раздела",
     *     summary="Метод для обновления раздела",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="id",
     *          description="id раздела",
     *          required=true,
     *          in="path",
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *     @OA\Parameter(
     *          name="entity_id",
     *          description="id сущности",
     *          required=false,
     *          in="query",
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
     *            @OA\Schema(
     *
     *              @OA\Property(property="title", type="string", example="my section"),
     *              @OA\Property(property="position", type="int64", example=2),
     *              @OA\Property(
     *                  property="section_fields",
     *                  type="array",
     *
     *                  @OA\Items(
     *
     *                      @OA\Property(property="id", type="int64", example=4),
     *                      @OA\Property(property="field_id", type="int64", example=4),
     *                      @OA\Property(property="user_field_id", type="int64", example=7),
     *                      @OA\Property(property="position", type="int64", example=1),
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
     *                     ref="#/components/schemas/Section"
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
    public function update(Section $section, SectionUpdateRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $section = $this->sectionService->updateSection($section, $validated, $validated);

        return ResponseService::success(
            new SectionResource($section, $validated['type'] ?? 'view')
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/sections/{id}",
     *     operationId="deleteSection",
     *     tags={"Section"},
     *     description="Метод для удаления раздела",
     *     summary="Метод для удаления раздела",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="id",
     *          description="id раздела",
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
    public function destroy(Section $section): JsonResponse
    {
        if ($section->main_section) {
            return ResponseService::forbidden(message: 'Запрещено удалять основной раздел');
        }

        $hasRequiredFields = $section->fields()->whereHas('field', function ($query) {
            $query->where('is_required', true);
        })->exists();

        if ($hasRequiredFields) {
            return ResponseService::forbidden(message: 'Нельзя удалить раздел, содержащий обязательные поля');
        }

        $this->sectionService->deleteSection($section);

        return ResponseService::success(message: 'Раздел успешно удален');
    }
}
