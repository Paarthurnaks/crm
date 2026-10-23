<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Filters\DocumentTemplateFilter;
use App\Http\Requests\Document\UploadRequest;
use App\Http\Requests\Document\UploadUpdateRequest;
use App\Http\Resources\DocumentTemplateResource;
use App\Http\Resources\ListResource;
use App\Models\DocumentTemplate;
use App\Services\DocumentService;
use App\Services\ResponseService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;
use Symfony\Component\Translation\Exception\NotFoundResourceException;

class DocumentController extends Controller
{
    public function __construct(private readonly DocumentService $service)
    {

    }

    /**
     * @OA\Get(
     *     path="/api/document-templates/",
     *     operationId="getDocumentTemplates",
     *     tags={"DocumentTemplate"},
     *     description="Метод для просмотра списка шаблонов документов",
     *     summary="Метод для просмотра списка шаблонов документов",
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
     *          description="фильтр по title (через оператор like)",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="creator",
     *          description="фильтр по создателю шаблона",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="int"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="entity",
     *          description="фильтр по сущности привязанной к шаблону",
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
     *     @OA\Response(response="200",
     *          description="OK",
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(
     *                 @OA\Property(
     *                     property="data",
     *                     ref="#/components/schemas/DocumentTemplate"
     *                 ),
     *              )
     *          ),
     *      )
     * )
     *
     * @param DocumentTemplateFilter $filter
     * @param Request $request
     * @return JsonResponse
     */
    public function index(DocumentTemplateFilter $filter, Request $request): JsonResponse
    {
        $queryParams = $request->all();
        $documentTemplates = $this->service->getList($filter, $queryParams);
        $total = $documentTemplates->toArray()["total"];

        return ResponseService::success(
            ListResource::make(DocumentTemplateResource::class, $documentTemplates, $total)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/document-templates/{id}",
     *     operationId="getDocumentTemplate",
     *     tags={"DocumentTemplate"},
     *     description="Метод для детального просмотра шаблона документа",
     *     summary="Метод для детального просмотра шаблона документа",
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *          name="id",
     *          description="id шаблона",
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
     *                     ref="#/components/schemas/DocumentTemplate"
     *                 ),
     *              )
     *          )
     *      ),
     *      @OA\Response(response="404",
     *          description="NOT FOUND"
     *      ),
     * )
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $documentTemplate = DocumentTemplate::find($id);

        if (!$documentTemplate) {
            return ResponseService::notFound(message: __('document template not found'));
        }

        return ResponseService::success(
            DocumentTemplateResource::make($documentTemplate)
        );
    }

    /**
     * @OA\Post(
     *     path="/api/document-templates/",
     *     operationId="createDocumentTemplate",
     *     tags={"DocumentTemplate"},
     *     description="Метод для загрузки нового шаблона",
     *     summary="Метод для загрузки нового шаблона",
     *     security={{"bearer_token":{}}},
     *     @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="multipart/form-data",
     *           @OA\Schema(
     *              @OA\Property(property="title", type="string", example="new template"),
     *              @OA\Property(property="entity", type="string", example="deal"),
     *              @OA\Property(property="template", type="binary"),
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
     *                     ref="#/components/schemas/DocumentTemplate"
     *                 ),
     *              )
     *          )
     *      ),
     *      @OA\Response(response="403",
     *          description="FORBIDDEN"
     *     ),
     * )
     * @param UploadRequest $request
     * @return JsonResponse
     */
    public function store(UploadRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $uploadTemplate = $this->service->create($validated);
        return ResponseService::success(
            DocumentTemplateResource::make($uploadTemplate)
        );
    }

    /**
     * @OA\Put(
     *     path="/api/document-templates/{id}",
     *     operationId="updateDocumentTemplate",
     *     tags={"DocumentTemplate"},
     *     description="Метод для обновления шаблона",
     *     summary="Метод для обновления шаблона",
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *          name="id",
     *          description="id шаблона",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *     @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="multipart/form-data",
     *           @OA\Schema(
     *              @OA\Property(property="title", type="string", example="new template"),
     *              @OA\Property(property="entity", type="string", example="deal"),
     *              @OA\Property(property="template", type="binary"),
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
     *                     ref="#/components/schemas/DocumentTemplate"
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
     * @param int $id
     * @param UploadUpdateRequest $request
     * @return JsonResponse
     * @throws Exception
     */
    public function update(int $id, UploadUpdateRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            $documentTemplate = $this->service->update($id, $validated);

            return ResponseService::success(
                DocumentTemplateResource::make($documentTemplate)
            );
        } catch (NotFoundResourceException) {
            return ResponseService::notFound(message: __('document template not found'));
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/document-templates/{id}",
     *     operationId="deleteDocumentTemplate",
     *     tags={"DocumentTemplate"},
     *     description="Метод для удаления шаблона документа",
     *     summary="Метод для удаления шаблона документа",
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *          name="id",
     *          description="id",
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
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $documentTemplate = DocumentTemplate::find($id);

        if (!$documentTemplate) {
            return ResponseService::notFound(message: __('document template not found'));
        }

        $this->service->delete($documentTemplate);

        return ResponseService::success(message: __('document has been deleted'));
    }

    /**
     * @OA\Post(
     *     path="/api/document-templates/{templateId}/uploadNewTemplate",
     *     operationId="updateDocumentTemplateFile",
     *     tags={"DocumentTemplate"},
     *     description="Метод для загрузки нового docx файла в шаблон",
     *     summary="Метод для загрузки нового docx файла в шаблон",
     *     security={{"bearer_token":{}}},
     *     @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="multipart/form-data",
     *           @OA\Schema(
     *              @OA\Property(property="template", type="binary"),
     *           )
     *       )
     *     ),
     *     @OA\Response(response="200",
     *          description="OK",
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(
     *                 @OA\Property(
     *                     property="data",
     *                     type="string",
     *                     example="document template has been updated"
     *                 ),
     *              )
     *          )
     *      ),
     *      @OA\Response(response="403",
     *          description="FORBIDDEN"
     *     ),
     *     @OA\Response(response="404",
     *          description="Not found"
     *     ),
     * )
     * @param int $templateId
     * @param Request $request
     * @return JsonResponse
     */
    public function updateTemplate(int $templateId, Request $request): JsonResponse
    {
        if (!$request->has('template')) {
            return ResponseService::badRequest(message: __('template field required'));
        }

        try {
            $this->service->updateTemplate($templateId);
            return ResponseService::success(message: __('document template has been updated'));
        } catch (NotFoundResourceException $e) {
            return ResponseService::notFound(message: __('template not found'));
        }
    }
}
