<?php

namespace App\Services;

use App\Enums\DealActivityEnum;
use App\Enums\DefaultRolesEnum;
use App\Enums\PermissionEntityEnum;
use App\Enums\PermissionTypeEnum;
use App\Enums\SectionFieldEntityEnum;
use App\Http\Filters\DealActivityFilter;
use App\Http\Filters\DealFilter;
use App\Http\Resources\CommentResource;
use App\Http\Resources\DealEventResource;
use App\Http\Resources\TaskResource;
use App\Models\Comment;
use App\Models\Deal;
use App\Models\DealEvent;
use App\Models\SalesFunnel;
use App\Models\SalesStage;
use App\Models\Task;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Mockery\Exception;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class DealService
{
    public function __construct(
        private readonly EntityActivityService $activityService,
        private readonly UserFieldService $userFieldService,
    ) {}

    public function getDeals(DealFilter $filter, array $queryParams): LengthAwarePaginator
    {
        $user = auth()->user();
        $limit = $queryParams['limit'] ?? config('constants.paginator.limit');

        if ($user->hasRole(DefaultRolesEnum::CLIENT->value)) {
            // клиенту выводим его единственную сделку с партнером
            return auth()->user()->partnerDeals()->filter($filter)->paginate($limit);
        } else {
            $dealScopes = [
                PermissionTypeEnum::ALL->value => 'allDeals',
                PermissionTypeEnum::OWN->value => 'ownDeals',
                PermissionTypeEnum::OPENED->value => 'openedDeals',
            ];

            foreach ($dealScopes as $type => $scopeMethod) {
                if ($user->hasAccess(PermissionEntityEnum::DEALS->value, 'view', $type)) {
                    // вызов соответствующего скоупа из модели Deal
                    return Deal::with(['userFieldValues', 'responsible', 'creator', 'salesFunnel', 'salesStage'])
                        ->$scopeMethod()
                        ->filter($filter)
                        ->paginate($limit);
                }
            }

            return new LengthAwarePaginator([], 0, $limit);
        }
    }

    public function createDeal(array $dealData): Deal
    {
        $sales_funnel_id = $dealData['sales_funnel_id'] ?? SalesFunnel::first()->id;
        $sales_stage = SalesStage::where('sales_funnel_id', $sales_funnel_id)->first();
        $user = Auth::user();

        $deal = Deal::query()->create([
            'title' => $dealData['title'] ?? $this->generateDealTitle(),
            'sales_funnel_id' => $sales_funnel_id,
            'sales_stage_id' => $dealData['sales_stage_id'] ?? $sales_stage->id,
            'creator_id' => $user->id,
            'responsible_id' => $dealData['responsible_id'] ?? $user->id,
            'note' => $dealData['note'] ?? '',
            'amount' => $dealData['amount'] ?? 0,
            'client' => $dealData['client_id'] ?? null,
        ]);

        if (! empty($dealData['user_field_values'])) {
            $this->userFieldService->addUserFieldValues($deal->id, $dealData['user_field_values'], SectionFieldEntityEnum::DEALS);
        }

        return Deal::where('id', $deal->id)->with('userFieldValues')->first();
    }

    /**
     * @throws Exception
     */
    public function updateDeal(Deal $deal, array $dealData): Deal
    {
        $this->userFieldService->updateUserFieldEntity($deal, $dealData, SectionFieldEntityEnum::DEALS);

        $deal->save();

        return Deal::where('id', $deal->id)->with('userFieldValues')->first();
    }

    public function deleteDeal(Deal $deal): void
    {
        $deal->delete();
    }

    private function generateDealTitle(): string
    {
        $dealId = Deal::latest()->first()?->id + 1;

        return 'Сделка #'.$dealId;
    }

    public function getActivity(Deal $deal, DealActivityFilter $filter): LengthAwarePaginator
    {
        $activityMap = [
            DealEvent::class => ['type' => DealActivityEnum::EVENT, 'resource' => DealEventResource::class],
            Comment::class => ['type' => DealActivityEnum::COMMENT, 'resource' => CommentResource::class],
            Task::class => ['type' => DealActivityEnum::TASK, 'resource' => TaskResource::class],
        ];

        return $this->activityService->getActivity($deal, $filter, $activityMap);

    }

    public function getMediaDocs(Deal $deal): array
    {
        $mediaFiles = $deal->getMedia('docs');
        $organizedFiles = [];

        foreach ($mediaFiles as $mediaItem) {
            $extension = $mediaItem->extension;
            $name = explode('.', $mediaItem->name);
            $name = $name[0];

            if (! isset($organizedFiles[$name])) {
                $organizedFiles[$name] = [
                    'id' => $mediaItem->id,
                    'name' => $name,
                ];
            }
            $organizedFiles[$name][$extension] = $mediaItem->getUrl();
        }

        usort($organizedFiles, function ($a, $b) {
            return $b['id'] - $a['id'];
        });

        return ['files' => array_values($organizedFiles)];
    }

    public function getDocument(Media $media): array
    {
        $documents = Media::where([
            'model_type' => $media->model_type,
            'model_id' => $media->model_id,
            'collection_name' => 'docs',
        ])->get();

        $result = [];

        foreach ($documents as $document) {
            $extension = $document->extension;

            if (in_array($extension, ['pdf', 'docx'])) {
                $result[$extension]['id'] = $document->id;
                $result[$extension]['url'] = $document->getUrl();
                $result[$extension]['name'] = $document->file_name;
            }
        }

        return $result;
    }

    public function addComment(Deal $deal, array $data): Comment
    {
        $text = $data['comment'] ?? '';
        $attachments = $data['attachments'] ?? [];

        return DB::transaction(function () use ($deal, $text, $attachments) {
            $comment = $deal->comment($text);

            $comment->approve();

            if ($attachments) {
                foreach ($attachments as $attachment) {
                    $comment->addMedia($attachment)
                        ->toMediaCollection();
                }
            }

            $comment->load('media');

            return $comment;
        });
    }
}
