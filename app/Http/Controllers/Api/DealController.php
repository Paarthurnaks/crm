<?php

namespace App\Http\Controllers\Api;

use App\Enums\EntityTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Filters\DealActivityFilter;
use App\Http\Filters\DealFilter;
use App\Http\Requests\Deal\AddCommentRequest;
use App\Http\Requests\Deal\GenerateDocumentRequest;
use App\Http\Requests\DealStoreUpdateRequest;
use App\Http\Resources\CommentResource;
use App\Http\Resources\DealResource;
use App\Http\Resources\ListResource;
use App\Models\Deal;
use App\Models\DocumentTemplate;
use App\Services\DealService;
use App\Services\DocumentService;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mockery\Exception;
use PhpOffice\PhpWord\Exception\CopyFileException;
use PhpOffice\PhpWord\Exception\CreateTemporaryFileException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class DealController extends Controller
{
    public function __construct(
        private readonly DealService $dealService,
        private readonly DocumentService $documentService,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/deals/",
     *     operationId="getDeals",
     *     tags={"Deal"},
     *     description="Метод для просмотра списка сделок",
     *     summary="Метод для просмотра списка сделок",
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
     *     @OA\Parameter(
     *           name="has_clients",
     *           description="Получает сделки с привязанными клиентами или без",
     *           required=false,
     *           in="query",
     *
     *           @OA\Schema(
     *               type="boolean"
     *           )
     *       ),
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
     *      @OA\Parameter(
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
     *          name="sales_stage",
     *          description="фильтр по sales_stage_id",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="int"
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="sales_funnel",
     *          description="фильтр по sales_funnel_id",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="int"
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="creator",
     *          description="фильтр по creator_id",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="int"
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="responsible",
     *          description="фильтр по responsible_id",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="int"
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="note",
     *          description="фильтр по note",
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
     *          description="фильтр по client",
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
     *          description="фильтр по amount (от)",
     *          required=false,
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),     *
     *
     *      @OA\Parameter(
     *          name="amount_to",
     *          description="фильтр по amount (до)",
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
     *                     ref="#/components/schemas/Deal"
     *                 ),
     *              )
     *          ),
     *      )
     * )
     */
    public function index(Request $request, DealFilter $filter): JsonResponse
    {
        $queryParams = $request->all();

        $deals = $this->dealService->getDeals($filter, $queryParams);
        $total = $deals->toArray()['total'];

        return ResponseService::success(
            ListResource::make(DealResource::class, $deals, $total)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/deals/{id}",
     *     operationId="getDeal",
     *     tags={"Deal"},
     *     description="Метод для детального просмотра сделки",
     *     summary="Метод для детального просмотра сделки",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="id",
     *          description="id сделки",
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
     *                     ref="#/components/schemas/Deal"
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
    public function show(Deal $deal): JsonResponse
    {
        return ResponseService::success(
            DealResource::make($deal)
        );
    }

    /**
     * @OA\Post(
     *     path="/api/deals/",
     *     operationId="createDeal",
     *     tags={"Deal"},
     *     description="Метод для создания новой сделки",
     *     summary="Метод для создания новой сделки",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\RequestBody(
     *
     *       @OA\MediaType(
     *           mediaType="application/json",
     *
     *           @OA\Schema(
     *
     *              @OA\Property(property="title", type="string", example="my deal"),
     *              @OA\Property(property="sales_funnel_id", type="int64", example=1),
     *              @OA\Property(property="sales_stage_id", type="int64", example=1),
     *              @OA\Property(property="responsible_id", type="int64", example=1),
     *              @OA\Property(property="note", type="string", example="text"),
     *              @OA\Property(property="amount", type="float", example=10.5),
     *              @OA\Property(property="client", type="string", example="2"),
     *              @OA\Property(property="partner_client_id", type="integer", example="23"),
     *              @OA\Property(property="user_field_values", type="object",
     *              @OA\Property(property="uf_name_index", type="string", example="uf_value")
     *              ),
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
     *                     ref="#/components/schemas/Deal"
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
    public function store(DealStoreUpdateRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $deal = $this->dealService->createDeal($validated);

        return ResponseService::created(
            DealResource::make($deal)
        );
    }

    /**
     * @OA\Put(
     *     path="/api/deals/{id}",
     *     operationId="updateDeal",
     *     tags={"Deal"},
     *     description="Метод для обновления сделки",
     *     summary="Метод для обновления сделки",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="id",
     *          description="id сделки",
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
     *              @OA\Property(property="title", type="string", example="my deal"),
     *              @OA\Property(property="sales_funnel_id", type="int64", example=1),
     *              @OA\Property(property="sales_stage_id", type="int64", example=1),
     *              @OA\Property(property="responsible_id", type="int64", example=1),
     *              @OA\Property(property="note", type="string", example="text"),
     *              @OA\Property(property="amount", type="float", example=10.5),
     *              @OA\Property(property="client", type="string", example="2"),
     *              @OA\Property(property="partner_client_id", type="integer", example="23"),
     *              @OA\Property(property="user_field_values", type="object",
     *                  @OA\Property(property="uf_name_index", type="string", example="uf_value")
     *              ),
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
     *                     ref="#/components/schemas/Deal"
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
     */
    public function update(Deal $deal, DealStoreUpdateRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $deal = $this->dealService->updateDeal($deal, $validated);

            return ResponseService::success(
                DealResource::make($deal)
            );
        } catch (Exception $e) {
            return ResponseService::badRequest(message: $e->getMessage());
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/deals/{id}",
     *     operationId="deleteDeal",
     *     tags={"Deal"},
     *     description="Метод для удаления сделки",
     *     summary="Метод для удаления сделки",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="id",
     *          description="id сделки",
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
    public function destroy(Deal $deal): JsonResponse
    {
        $this->dealService->deleteDeal($deal);

        return ResponseService::success(message: 'Сделка успешно удалена');
    }

    /**
     * @OA\Post(
     *     path="/api/deals/{id}/comments",
     *     operationId="addCommentForDeal",
     *     tags={"Deal"},
     *     description="Метод для добавления комментария к сделке",
     *     summary="Метод для добавления комментария к сделке",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="id",
     *          description="id сделки",
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
     *              @OA\Property(property="comment", type="string", example="my first comment"),
     *              @OA\Property(property="attachments", type="array", @OA\Items(type="string", example="filename.jpg")),
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
     *                     ref="#/components/schemas/Comment"
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
     */
    public function addComment(int $dealId, AddCommentRequest $request): JsonResponse
    {
        $deal = Deal::find($dealId);

        if (! $deal) {
            return ResponseService::notFound(
                message: 'Сделка не найдена'
            );
        }

        $validated = $request->validated();

        try {
            $comment = $this->dealService->addComment($deal, $validated);

            return ResponseService::success(
                CommentResource::make($comment)
            );
        } catch (\Throwable $e) {
            return ResponseService::badRequest($e->getMessage());
        }

    }

    /**
     * @OA\Get(
     *     path="/api/deals/{id}/comments",
     *     operationId="getDealComments",
     *     tags={"Deal"},
     *     description="Метод для просмотра списка комментариев к сделке",
     *     summary="Метод для просмотра списка комментариев к сделке",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="page",
     *          description="Номер страницы",
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
     *                          ref="#/components/schemas/Comment"
     *                      )
     *                 ),
     *              )
     *          ),
     *      )
     * )
     */
    public function getComments(int $dealId, Request $request): JsonResponse
    {
        $deal = Deal::find($dealId);

        if (! $deal) {
            return ResponseService::notFound(
                message: 'Сделка не найдена'
            );
        }

        $comments = $deal->comments()->latest()->paginate(
            $request->input('limit', config('constants.paginator.limit'))
        );

        return ResponseService::success(
            CommentResource::collection($comments)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/deals/{id}/activity",
     *     operationId="getDealActivity",
     *     tags={"Deal"},
     *     description="Метод для просмотра всей активности сделки",
     *     summary="Метод для просмотра всей активности сделки",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="id",
     *          description="id сделки",
     *          in="path",
     *
     *          @OA\Schema(
     *              type="string",
     *              example="1"
     *          )
     *      ),
     *
     *     @OA\Parameter(
     *          name="search",
     *          description="Поиск по контексту. В случае задач это название. В случае комментария это контент",
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string",
     *              example="перезвонить"
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="author_id",
     *          description="Идентификатор автора",
     *          in="query",
     *
     *          @OA\Schema(
     *              type="int",
     *              example=12
     *          )
     *      ),
     *
     *     @OA\Parameter(
     *          name="date_from",
     *          description="Дата создания событий (от)",
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string",
     *              example="2023-12-11"
     *          )
     *      ),
     *
     *     @OA\Parameter(
     *          name="date_to",
     *          description="Дата создания событий (до)",
     *          in="query",
     *
     *          @OA\Schema(
     *              type="string",
     *              example="2023-12-17"
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
     *                          type="object",
     *
     *                          @OA\Property(
     *                             property="type",
     *                             type="string",
     *                             example="comment"
     *                          ),
     *                          @OA\Property(
     *                             property="data",
     *                             ref="#/components/schemas/Comment"
     *                          )
     *                      )
     *                 ),
     *              )
     *          ),
     *      )
     * )
     */
    public function getActivity(int $dealId, DealActivityFilter $filter): JsonResponse
    {
        $deal = Deal::find($dealId);

        if (! $deal) {
            return ResponseService::notFound(
                message: __('Сделка не найдена')
            );
        }

        $activity = $this->dealService->getActivity($deal, $filter);

        return ResponseService::success($activity);
    }

    /**
     * @OA\Post(
     *     path="/api/deals/{dealId}/generateDocument/{templateId}",
     *     operationId="generateDealDocument",
     *     tags={"Deal"},
     *     description="Метод для генерации документа в сделки. Добавляем данные в шаблон из имеющихся в сделке по примеру ${id} или же ${creator.name}",
     *     summary="Метод для генерации документа в сделки",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *          name="dealId",
     *          description="id сделки",
     *          required=true,
     *          in="path",
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="templateId",
     *          description="id шаблона",
     *          required=true,
     *          in="path",
     *
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *
     *     @OA\Parameter(
     *           name="title",
     *           description="название шиблона",
     *           required=true,
     *           in="query",
     *
     *           @OA\Schema(
     *               type="string"
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
     *                     type="object",
     *                      @OA\Property(
     *                         property="pdf",
     *                         type="string",
     *                         example="https://example.com/storage/example.pdf"
     *                      ),
     *                      @OA\Property(
     *                         property="docx",
     *                         type="string",
     *                         example="https://example.com/storage/example.docx"
     *                      )
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
     * @throws CopyFileException
     * @throws CreateTemporaryFileException
     * @throws \PhpOffice\PhpWord\Exception\Exception
     */
    public function generateDocument(GenerateDocumentRequest $request, int $dealId, int $templateId): JsonResponse
    {
        $validated = $request->validated();

        $deal = Deal::with(
            'creator', 'salesFunnel', 'salesStage', 'responsible', 'userFieldValues'
        )->find($dealId);

        $firstMediaExists = DB::table('media')
            ->where('model_id', $dealId)
            ->where('model_type', Deal::class)
            ->where('collection_name', 'docs')
            ->where(function ($query) use ($validated) {
                $query->where('name', $validated['title']);
            })
            ->exists();

        if ($firstMediaExists) {
            return ResponseService::forbidden(message: 'Документ с таким названием уже существует у данной сделки');
        }

        if (! $deal) {
            return ResponseService::notFound(
                message: __('Сделка не найдена')
            );
        }

        $template = DocumentTemplate::find($templateId);

        if (! $template) {
            return ResponseService::notFound(
                message: __('Шаблон не найден')
            );
        }

        if ($template->entity != EntityTypeEnum::DEALS->value) {
            return ResponseService::forbidden(
                message: __('Шаблон не относится к сущности "сделка"')
            );
        }

        $document = $this->documentService->generateDocumentFromTemplate($deal, $template);

        $docxMedia = $deal->addMedia($document['docx'])
            ->setName($validated['title'])
            ->setFileName($validated['title'].'.docx')
            ->withCustomProperties(['user_id' => auth()->id(), 'template_id' => $template->id])
            ->toMediaCollection('docs');

        $pdfMedia = $deal->addMedia($document['pdf'])
            ->setName($validated['title'])
            ->setFileName($validated['title'].'.pdf')
            ->withCustomProperties(['user_id' => auth()->id(), 'template_id' => $template->id])
            ->toMediaCollection('docs');

        return ResponseService::success([
            'id' => $docxMedia->id,
            'docx' => $docxMedia->getUrl(),
            'pdf' => $pdfMedia->getUrl(),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/deals/{id}/documents",
     *     operationId="getDealDocuments",
     *     tags={"Deal"},
     *     description="Метод для получения списка документов (позже будет дорабатываться. пока отсутствуют фильтры и адекватный вывод)",
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
     *                     type="array",
     *
     *                     @OA\Items(
     *                      )
     *                 ),
     *              )
     *          ),
     *      )
     * )
     */
    public function documents(int $dealId): JsonResponse
    {
        $deal = Deal::find($dealId);

        if (! $deal || ! auth()->user()->hasDealAccess($dealId)) {
            return ResponseService::notFound(
                message: __('Сделка не найдена')
            );
        }

        $media = $this->dealService->getMediaDocs($deal);

        return ResponseService::success($media);
    }

    /**
     * @OA\Get(
     *     path="/api/deals/documents/{id}",
     *     operationId="getDocument",
     *     tags={"Deal"},
     *     summary="Retrieve a document by its ID",
     *     description="Returns a single document's details including URL.",
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the document to retrieve",
     *         required=true,
     *
     *         @OA\Schema(
     *             type="integer",
     *             format="int64"
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="url", type="string", description="URL of the document"),
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Document not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Документ не найден")
     *         )
     *     ),
     *     security={
     *         {"bearerAuth": {}}
     *     }
     * )
     */
    public function getDocument(Request $request, int $id): JsonResponse
    {
        $media = Media::find($id);

        if (! $media || ! $request->user()->hasDealAccess($media->model_id)) {
            return ResponseService::notFound(
                message: __('Документ не найден')
            );
        }

        $docs = $this->dealService->getDocument($media);

        return ResponseService::success($docs);
    }

    /**
     * @OA\Get(
     *     path="/api/stats/deals/in-progress",
     *     operationId="getInProgressDealsCount",
     *     tags={"Stats"},
     *     summary="Get count of in-progress deals",
     *     description="Returns the count of deals that are currently in progress for a partner.",
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="count",
     *                     type="integer",
     *                     description="Number of in-progress deals"
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="This action is unauthorized.")
     *         )
     *     ),
     *     security={
     *         {"bearer_token": {}}
     *     }
     * )
     */
    public function inProgress(Request $request): JsonResponse
    {
        $count = Deal::whereHas('salesStage', function ($query) {
            $query->where('type', 'default');
        })
            ->where(function ($query) {
                $query->where('creator_id', auth()->id())
                    ->orWhere('responsible_id', auth()->id());
            })
            ->count();

        return ResponseService::success(['count' => $count]);
    }
}
