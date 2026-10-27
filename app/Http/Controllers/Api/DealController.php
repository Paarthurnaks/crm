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
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class DealController extends Controller
{
    public function __construct(
        private readonly DealService $dealService,
        private readonly DocumentService $documentService,
    ) {}

    public function index(Request $request, DealFilter $filter): JsonResponse
    {
        $queryParams = $request->all();

        $deals = $this->dealService->getDeals($filter, $queryParams, $request->user());
        $total = $deals->toArray()['total'];

        return ResponseService::success(
            ListResource::make(DealResource::class, $deals, $total)
        );
    }

    public function show(Deal $deal): JsonResponse
    {
        return ResponseService::success(
            DealResource::make($deal)
        );
    }

    public function store(DealStoreUpdateRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $deal = $this->dealService->createDeal($validated);

        return ResponseService::created(
            DealResource::make($deal)
        );
    }

    public function update(Deal $deal, DealStoreUpdateRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $deal = $this->dealService->updateDeal($deal, $validated);

            return ResponseService::success(
                DealResource::make($deal)
            );
        } catch (\Throwable $e) {
            return ResponseService::fromException($e, 'deal.update');
        }
    }

    public function destroy(Deal $deal): JsonResponse
    {
        $this->dealService->deleteDeal($deal);

        return ResponseService::success(message: 'Сделка успешно удалена');
    }

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

    public function getActivity(int $dealId, DealActivityFilter $filter, Request $request): JsonResponse
    {
        $deal = Deal::find($dealId);

        if (! $deal) {
            return ResponseService::notFound(
                message: __('Сделка не найдена')
            );
        }

        $activity = $this->dealService->getActivity($deal, $filter, [
            'limit' => $request->query('limit', config('constants.paginator.limit')),
            'path' => $request->url(),
            'query' => $request->query(),
        ], $request->user());

        return ResponseService::success($activity);
    }

    public function generateDocument(GenerateDocumentRequest $request, int $dealId, int $templateId): JsonResponse
    {
        $validated = $request->validated();

        $deal = Deal::with(
            'creator', 'salesFunnel', 'salesStage', 'responsible', 'userFieldValues'
        )->find($dealId);

        if (! $deal) {
            return ResponseService::notFound(
                message: __('Сделка не найдена')
            );
        }

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

        $media = $this->documentService->generateDealDocument($deal, $template, $validated['title'], $request->user());

        return ResponseService::success([
            'id' => $media['docx']->id,
            'docx' => $media['docx']->getUrl(),
            'pdf' => $media['pdf']->getUrl(),
        ]);
    }

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
