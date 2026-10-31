<?php

namespace App\Services;

use App\Enums\DealActivityEnum;
use App\Enums\DealEventTypeEnum;
use App\Enums\DefaultRolesEnum;
use App\Enums\PermissionEntityEnum;
use App\Enums\PermissionTypeEnum;
use App\Enums\SalesStageEnum;
use App\Enums\SectionFieldEntityEnum;
use App\Events\Deal\PartnerInProgressDealUpdateBroadcast;
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
use App\Models\SalesTunnel;
use App\Models\Section;
use App\Models\Task;
use App\Models\User;
use App\Notifications\DealEventNotification;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class DealService
{
    public function __construct(
        private readonly EntityActivityService $activityService,
        private readonly UserFieldService $userFieldService,
    ) {}

    public function getDeals(DealFilter $filter, array $queryParams, User $actor): LengthAwarePaginator
    {
        $limit = $queryParams['limit'] ?? config('constants.paginator.limit');

        if ($actor->hasRole(DefaultRolesEnum::CLIENT->value)) {
            // клиенту выводим его единственную сделку с партнером
            return $this->preloadClients($actor->partnerDeals()->filter($filter)->paginate($limit));
        }

        $dealScopes = [
            PermissionTypeEnum::ALL->value => 'allDeals',
            PermissionTypeEnum::OWN->value => 'ownDeals',
            PermissionTypeEnum::OPENED->value => 'openedDeals',
        ];

        foreach ($dealScopes as $type => $scopeMethod) {
            if ($actor->hasAccess(PermissionEntityEnum::DEALS->value, 'view', $type)) {
                $query = Deal::with(['userFieldValues', 'responsible', 'creator', 'salesFunnel', 'salesStage']);
                if ($scopeMethod === 'ownDeals') {
                    $query->ownDeals($actor->id);
                } else {
                    $query->{$scopeMethod}();
                }

                return $this->preloadClients($query->filter($filter)->paginate($limit));
            }
        }

        return new LengthAwarePaginator([], 0, $limit);
    }

    private function preloadClients(LengthAwarePaginator $deals): LengthAwarePaginator
    {
        $ids = $deals->getCollection()
            ->map(fn (Deal $deal) => (string) $deal->getRawOriginal('client'))
            ->filter(fn (string $value) => ctype_digit($value))
            ->unique()
            ->values();
        $clients = User::query()->whereIn('id', $ids)->get()->keyBy('id');

        $deals->getCollection()->each(function (Deal $deal) use ($clients): void {
            $id = (string) $deal->getRawOriginal('client');
            $deal->setRelation('resolvedClient', ctype_digit($id) ? $clients->get((int) $id) : null);
        });

        Log::debug('deal.clients.preloaded', ['deal_count' => $deals->count(), 'client_count' => $clients->count()]);

        return $deals;
    }

    public function createDeal(array $dealData, ?User $actor = null): Deal
    {
        $user = $actor ?? Auth::user();

        if ($user === null) {
            throw new AuthenticationException;
        }
        Log::info('deal.create.started', ['actor_id' => $user?->id]);

        try {
            return DB::transaction(function () use ($dealData, $user): Deal {
                $salesFunnelId = $dealData['sales_funnel_id'] ?? SalesFunnel::firstOrFail()->id;
                $salesStage = SalesStage::where('sales_funnel_id', $salesFunnelId)->firstOrFail();

                $deal = Deal::query()->create([
                    'title' => $dealData['title'] ?? $this->generateDealTitle(),
                    'sales_funnel_id' => $salesFunnelId,
                    'sales_stage_id' => $dealData['sales_stage_id'] ?? $salesStage->id,
                    'creator_id' => $user->id,
                    'responsible_id' => $dealData['responsible_id'] ?? $user->id,
                    'note' => $dealData['note'] ?? '',
                    'amount' => $dealData['amount'] ?? 0,
                    'client' => $dealData['client_id'] ?? null,
                ]);

                $this->initializeCreatedDeal($deal, $user->id);

                if (! empty($dealData['user_field_values'])) {
                    $this->userFieldService->addUserFieldValues($deal->id, $dealData['user_field_values'], SectionFieldEntityEnum::DEALS);
                }

                Log::info('deal.create.completed', ['deal_id' => $deal->id, 'actor_id' => $user->id]);

                return $deal->load('userFieldValues');
            });
        } catch (\Throwable $exception) {
            Log::error('deal.create.failed', ['actor_id' => $user?->id, 'exception' => $exception::class]);
            throw $exception;
        }
    }

    public function updateDeal(Deal $deal, array $dealData, ?User $actor = null): Deal
    {
        $actorId = $actor?->id ?? Auth::id();
        Log::info('deal.update.started', ['deal_id' => $deal->id, 'actor_id' => $actorId]);

        try {
            return DB::transaction(function () use ($deal, $dealData, $actorId): Deal {
                $this->userFieldService->updateUserFieldEntity($deal, $dealData, SectionFieldEntityEnum::DEALS);
                $changes = $deal->getDirty();
                $original = $deal->getRawOriginal();
                $deal->save();

                $this->recordDealChanges($deal, $changes, $original, $actorId);
                Log::info('deal.update.completed', ['deal_id' => $deal->id, 'actor_id' => $actorId, 'changed_count' => count($changes)]);

                return $deal->load('userFieldValues');
            });
        } catch (\Throwable $exception) {
            Log::error('deal.update.failed', ['deal_id' => $deal->id, 'actor_id' => $actorId, 'exception' => $exception::class]);
            throw $exception;
        }
    }

    private function initializeCreatedDeal(Deal $deal, ?int $actorId): void
    {
        DealEvent::create([
            'deal_id' => $deal->id,
            'initiator_id' => $deal->creator_id,
            'type' => DealEventTypeEnum::NEW_DEAL->value,
        ]);

        if ($actorId !== null) {
            $this->copyTemplateSectionsToDeal($deal, $actorId);
        }

        $this->userFieldService->setupBooleanValues();
        Log::debug('deal.initialized', ['deal_id' => $deal->id, 'actor_id' => $actorId]);
    }

    private function copyTemplateSectionsToDeal(Deal $deal, int $actorId): void
    {
        $sections = Section::where('entity', 'deals')
            ->where('is_template', true)
            ->where('sales_funnel_id', $deal->sales_funnel_id)
            ->where('user_id', $actorId)
            ->with('fields')
            ->get();

        foreach ($sections as $section) {
            $this->copyTemplateSection($section, $deal);
        }
    }

    private function copyTemplateSection(Section $section, Deal $deal): void
    {
        $copy = $section->replicate();
        $copy->deal_id = $deal->id;
        $copy->is_template = false;
        $copy->save();

        foreach ($section->fields as $field) {
            $fieldCopy = $field->replicate();
            $fieldCopy->section_id = $copy->id;
            $fieldCopy->save();
        }
    }

    private function recordDealChanges(Deal $deal, array $changes, array $original, ?int $actorId): void
    {
        if (array_key_exists('sales_stage_id', $changes)) {
            $this->recordStageChange($deal, $original, $actorId);
        }

        if (array_key_exists('responsible_id', $changes)) {
            $this->recordResponsibleChange($deal, $original, $actorId);
        }

        if ($actorId === null) {
            return;
        }

        if (array_key_exists('client', $changes)) {
            $this->recordClientChange($deal, $original, $actorId);
        }

        $this->recordOtherFieldChanges($deal, $changes, $original, $actorId);
    }

    private function recordStageChange(Deal $deal, array $original, ?int $actorId): void
    {
        $this->replicateForStageTransitions($deal, $actorId);
        $oldStage = SalesStage::find($original['sales_stage_id'] ?? null);
        $newStage = $deal->salesStage;

        if ($actorId !== null) {
            DealEvent::create([
                'deal_id' => $deal->id,
                'initiator_id' => $actorId,
                'type' => DealEventTypeEnum::CHANGE_STAGE->value,
                'changed_field' => trans('deals.sales_stage_id'),
                'old_value' => $oldStage?->title,
                'new_value' => $newStage?->title,
            ]);
        }

        $wasDefault = $oldStage?->type === SalesStageEnum::DEFAULT->value;
        $isDefault = $newStage?->type === SalesStageEnum::DEFAULT->value;
        if ($oldStage && $newStage && $wasDefault !== $isDefault) {
            DB::afterCommit(fn () => $this->broadcastPartnerDealCounts($deal));
        }

        Log::debug('deal.stage_change.recorded', ['deal_id' => $deal->id, 'actor_id' => $actorId]);
    }

    private function recordResponsibleChange(Deal $deal, array $original, ?int $actorId): void
    {
        $oldResponsible = User::find($original['responsible_id'] ?? null);
        $newResponsible = $deal->responsible;

        if ($actorId === null || ! $oldResponsible || ! $newResponsible) {
            return;
        }

        $event = DealEvent::create([
            'deal_id' => $deal->id,
            'initiator_id' => $actorId,
            'type' => DealEventTypeEnum::CHANGE_RESPONSIBLE->value,
            'changed_field' => trans('deals.responsible_id'),
            'old_value' => $oldResponsible->name.' '.$oldResponsible->surname,
            'new_value' => $newResponsible->name.' '.$newResponsible->surname,
        ]);

        DB::afterCommit(function () use ($oldResponsible, $event): void {
            try {
                $oldResponsible->notify(new DealEventNotification($event, ['oldResponsibleId' => $oldResponsible->id]));
            } catch (\Throwable $exception) {
                Log::error('deal.responsible_notification.failed', [
                    'deal_id' => $event->deal_id,
                    'user_id' => $oldResponsible->id,
                    'exception' => $exception::class,
                ]);
            }
        });

        Log::debug('deal.responsible_change.recorded', ['deal_id' => $deal->id, 'actor_id' => $actorId]);
    }

    private function recordClientChange(Deal $deal, array $original, int $actorId): void
    {
        DealEvent::create([
            'deal_id' => $deal->id,
            'initiator_id' => $actorId,
            'type' => DealEventTypeEnum::CHANGE_CLIENT->value,
            'changed_field' => trans('deals.client'),
            'old_value' => $this->formatClientValue($original['client'] ?? null),
            'new_value' => $this->formatClientValue($deal->getRawOriginal('client')),
        ]);

        Log::debug('deal.client_change.recorded', ['deal_id' => $deal->id, 'actor_id' => $actorId]);
    }

    private function recordOtherFieldChanges(Deal $deal, array $changes, array $original, int $actorId): void
    {
        foreach (Lang::get('deals') as $key => $label) {
            if (in_array($key, ['responsible_id', 'sales_stage_id', 'client'], true) || ! array_key_exists($key, $changes)) {
                continue;
            }

            DealEvent::create([
                'deal_id' => $deal->id,
                'initiator_id' => $actorId,
                'type' => DealEventTypeEnum::CHANGE_FIELD->value,
                'changed_field' => $label,
                'old_value' => $key === 'note' ? strip_tags($original[$key] ?? '') : ($original[$key] ?? null),
                'new_value' => $deal->{$key},
            ]);
        }
    }

    private function replicateForStageTransitions(Deal $deal, ?int $actorId): void
    {
        $tunnels = SalesTunnel::with('toStage.salesFunnel')->where('from_stage_id', $deal->sales_stage_id)->get();

        foreach ($tunnels as $tunnel) {
            $replica = $deal->replicate();
            $replica->sales_funnel_id = $tunnel->toStage->salesFunnel->id;
            $replica->sales_stage_id = $tunnel->to_stage_id;
            $replica->save();
            $this->initializeCreatedDeal($replica, $actorId);
        }

        Log::debug('deal.stage_replicas.created', ['deal_id' => $deal->id, 'count' => $tunnels->count()]);
    }

    private function formatClientValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (ctype_digit($value)) {
            $client = User::find((int) $value);

            return $client ? $client->name.' '.$client->surname : $value;
        }

        return str_starts_with($value, 'str_') ? substr($value, 4) : $value;
    }

    private function broadcastPartnerDealCounts(Deal $deal): void
    {
        $partnerRole = DefaultRolesEnum::PARTNER->value;
        $partnerIds = [
            $deal->responsible()->whereHas('role', fn ($query) => $query->where('original_title', $partnerRole))->value('id'),
            $deal->creator()->whereHas('role', fn ($query) => $query->where('original_title', $partnerRole))->value('id'),
        ];

        foreach (array_unique(array_filter($partnerIds)) as $partnerId) {
            try {
                $count = Deal::whereHas('salesStage', fn ($query) => $query->where('type', SalesStageEnum::DEFAULT->value))
                    ->where(fn ($query) => $query->where('creator_id', $partnerId)->orWhere('responsible_id', $partnerId))
                    ->count();

                broadcast(new PartnerInProgressDealUpdateBroadcast($partnerId, $count));
            } catch (\Throwable $exception) {
                Log::error('deal.partner_count_broadcast.failed', [
                    'deal_id' => $deal->id,
                    'user_id' => $partnerId,
                    'exception' => $exception::class,
                ]);
            }
        }
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

    public function getActivity(Deal $deal, DealActivityFilter $filter, array $pagination, User $actor): LengthAwarePaginator
    {
        $activityMap = [
            DealEvent::class => ['type' => DealActivityEnum::EVENT, 'resource' => DealEventResource::class],
            Comment::class => ['type' => DealActivityEnum::COMMENT, 'resource' => CommentResource::class],
            Task::class => ['type' => DealActivityEnum::TASK, 'resource' => TaskResource::class],
        ];

        $scope = null;
        if ($actor->hasRole(DefaultRolesEnum::CLIENT->value)) {
            $scope = function ($query) use ($actor): void {
                $query->where('activityable_type', \BeyondCode\Comments\Comment::class)
                    ->whereHasMorph('activityable', [\BeyondCode\Comments\Comment::class], function ($comments) use ($actor) {
                        $comments->where('user_id', $actor->id)
                            ->orWhere('user_id', $actor->partner?->id);
                    });
            };
        }

        Log::debug('deal.activity.scope_selected', [
            'deal_id' => $deal->id,
            'actor_id' => $actor->id,
            'client_scope' => $scope !== null,
        ]);

        return $this->activityService->getActivity($deal, $filter, $activityMap, $pagination, $scope);

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
