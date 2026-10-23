<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\KnowledgeBase\StoreRequest;
use App\Http\Requests\KnowledgeBase\UpdateRequest;
use App\Http\Resources\KnowledgeBaseResource;
use App\Models\KnowledgeBase;
use App\Services\KnowledgeBaseService;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KnowledgeBaseController extends Controller
{
    public function __construct(
        private readonly KnowledgeBaseService $service
    ) {}

    /**
     * @OA\Get(
     *     path="/api/knowledge-bases",
     *     tags={"KnowledgeBase"},
     *     summary="Получить список баз знаний",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Успешная операция",
     *
     *         @OA\JsonContent(
     *             type="array",
     *
     *             @OA\Items(ref="#/components/schemas/KnowledgeBaseResource")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Ошибка запроса"
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $queryParams = $request->all();

        $limit = $queryParams['limit'] ?? config('constants.paginator.limit');

        $knowledgeBases = KnowledgeBase::latest()->paginate($limit);

        return ResponseService::success(
            KnowledgeBaseResource::collection($knowledgeBases)
        );
    }

    /**
     * @OA\Post(
     *     path="/api/knowledge-bases",
     *     tags={"KnowledgeBase"},
     *     summary="Создать новую базу знаний",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(ref="#/components/schemas/KnowledgeBaseStoreRequest")
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Ресурс создан",
     *
     *         @OA\JsonContent(ref="#/components/schemas/KnowledgeBaseResource")
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Ошибка запроса"
     *     )
     * )
     */
    public function store(StoreRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $knowledgeBase = $this->service->create($validated);

            return ResponseService::success(
                KnowledgeBaseResource::make($knowledgeBase)
            );
        } catch (\Exception $e) {
            return ResponseService::badRequest(message: $e->getMessage());
        }
    }

    /**
     * @OA\Get(
     *     path="/api/knowledge-bases/{id}",
     *     tags={"KnowledgeBase"},
     *     summary="Получить одну базу знаний",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID базы знаний",
     *         required=true,
     *
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Успешная операция",
     *
     *         @OA\JsonContent(ref="#/components/schemas/KnowledgeBaseResource")
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Ресурс не найден"
     *     )
     * )
     */
    public function show(string $id): JsonResponse
    {
        $knowledgeBase = KnowledgeBase::find($id);

        if (! $knowledgeBase) {
            return ResponseService::notFound();
        }

        return ResponseService::success(
            KnowledgeBaseResource::make($knowledgeBase)
        );
    }

    /**
     * @OA\Put(
     *     path="/api/knowledge-bases/{id}",
     *     tags={"KnowledgeBase"},
     *     summary="Обновить базу знаний",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID базы знаний",
     *         required=true,
     *
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(ref="#/components/schemas/KnowledgeBaseUpdateRequest")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Успешная операция",
     *
     *         @OA\JsonContent(ref="#/components/schemas/KnowledgeBaseResource")
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Ошибка запроса"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Ресурс не найден"
     *     )
     * )
     */
    public function update(UpdateRequest $request, string $id): JsonResponse
    {
        $knowledgeBase = KnowledgeBase::find($id);

        if (! $knowledgeBase) {
            return ResponseService::notFound();
        }

        $validated = $request->validated();

        try {
            $this->service->update($knowledgeBase, $validated);

            return ResponseService::success(
                KnowledgeBaseResource::make($knowledgeBase)
            );
        } catch (\Exception $e) {
            return ResponseService::badRequest(message: $e->getMessage());
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/knowledge-bases/{id}",
     *     tags={"KnowledgeBase"},
     *     summary="Удалить базу знаний",
     *     security={{"bearer_token":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID базы знаний",
     *         required=true,
     *
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Успешная операция"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Ресурс не найден"
     *     )
     * )
     */
    public function destroy(string $id): JsonResponse
    {
        $knowledgeBase = KnowledgeBase::find($id);

        if (! $knowledgeBase) {
            return ResponseService::notFound();
        }

        $knowledgeBase->delete();

        return ResponseService::success();
    }
}
