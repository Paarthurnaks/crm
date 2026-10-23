<?php

namespace App\Observers;

use App\Enums\DealEventTypeEnum;
use App\Enums\DefaultRolesEnum;
use App\Enums\SalesStageEnum;
use App\Events\Deal\PartnerInProgressDealUpdateBroadcast;
use App\Models\Deal;
use App\Models\DealEvent;
use App\Models\SalesStage;
use App\Models\SalesTunnel;
use App\Models\Section;
use App\Models\User;
use App\Notifications\DealEventNotification;
use App\Services\UserFieldService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Lang;

class DealObserver
{
    public function __construct(
        private readonly UserFieldService $userFieldService
    ) {}

    public function created(Deal $deal): void
    {
        $dealEvent = DealEvent::create([
            'deal_id' => $deal->id,
            'initiator_id' => $deal->creator_id,
            'type' => DealEventTypeEnum::NEW_DEAL->value,
        ]);

        if (auth()->user()) {
            $sections = Section::where('entity', 'deals')
                ->where('is_template', true)
                ->where('sales_funnel_id', $deal->sales_funnel_id)
                ->where('user_id', Auth::user()?->id)
                ->get();

            foreach ($sections as $section) {
                $cloneSection = $section->replicate();
                $cloneSection->deal_id = $deal->id;
                $cloneSection->is_template = false;

                $cloneSection->save();

                foreach ($section->fields as $sectionField) {
                    $cloneSectionField = $sectionField->replicate();
                    $cloneSectionField->section_id = $cloneSection->id;
                    $cloneSectionField->save();
                }
            }
        }

        $this->userFieldService->setupBooleanValues();
    }

    public function updated(Deal $deal): void
    {
        if ($deal->isDirty('sales_stage_id')) {
            $salesTunnels = SalesTunnel::where('from_stage_id', $deal->sales_stage_id)->get();
            foreach ($salesTunnels as $salesTunnel) {
                $newDeal = $deal->replicate();
                $newDeal->sales_funnel_id = $salesTunnel->toStage->salesFunnel->id;
                $newDeal->sales_stage_id = $salesTunnel->to_stage_id;
                $newDeal->save();
            }

            $oldStageId = $deal->getOriginal('sales_stage_id');
            $oldStage = SalesStage::find($oldStageId);

            if (auth()->user()) {
                $dealEvent = DealEvent::create([
                    'deal_id' => $deal->id,
                    'initiator_id' => Auth::user()->id,
                    'type' => DealEventTypeEnum::CHANGE_STAGE->value,
                    'changed_field' => trans('deals.sales_stage_id'),
                    'old_value' => $oldStage->title,
                    'new_value' => $deal->salesStage->title,
                ]);
            }

            $defaultStageType = SalesStageEnum::DEFAULT->value;
            if (($deal->salesStage->type === $defaultStageType) !== ($oldStage->type === $defaultStageType)) {
                $responsibleId = $deal->responsible()->whereHas('role', function ($query) {
                    $query->where('original_title', DefaultRolesEnum::PARTNER->value);
                })->value('id');

                $creatorId = $deal->creator()->whereHas('role', function ($query) {
                    $query->where('original_title', DefaultRolesEnum::PARTNER->value);
                })->value('id');

                collect([$creatorId, $responsibleId])->filter()->unique()->each(function ($id) {
                    $count = Deal::whereHas('salesStage', function ($query) {
                        $query->where('type', SalesStageEnum::DEFAULT->value);
                    })
                        ->where(function ($query) use ($id) {
                            $query->where('creator_id', $id)
                                ->orWhere('responsible_id', auth()->id());
                        })
                        ->count();

                    broadcast(new PartnerInProgressDealUpdateBroadcast($id, $count));
                });
            }
        } elseif ($deal->isDirty('responsible_id')) {
            $oldResponsibleId = $deal->getOriginal('responsible_id');
            $oldResponsible = User::find($oldResponsibleId);

            if (auth()->user()) {
                $dealEvent = DealEvent::create([
                    'deal_id' => $deal->id,
                    'initiator_id' => Auth::user()->id,
                    'type' => DealEventTypeEnum::CHANGE_RESPONSIBLE->value,
                    'changed_field' => trans('deals.responsible_id'),
                    'old_value' => $oldResponsible->name.' '.$oldResponsible->surname,
                    'new_value' => $deal->responsible->name.' '.$deal->responsible->surname,
                ]);
            }

            $oldResponsible->notify(new DealEventNotification($dealEvent, compact('oldResponsibleId')));
        } elseif ($deal->isDirty('client')) {
            $oldClientData = $deal->getOriginal('client');

            $deal = $deal->fresh();

            $oldValue = $oldClientData instanceof User ? $oldClientData->name.' '.$oldClientData->surname : $oldClientData;
            $newValue = $deal->client instanceof User ? $deal->client->name.' '.$deal->client->surname : $deal->client;

            if (auth()->user()) {
                DealEvent::create([
                    'deal_id' => $deal->id,
                    'initiator_id' => Auth::user()->id,
                    'type' => DealEventTypeEnum::CHANGE_CLIENT->value,
                    'changed_field' => trans('deals.client'),
                    'old_value' => $oldValue,
                    'new_value' => $newValue,
                ]);
            }

        } else {
            $values = Lang::get('deals');
            foreach ($values as $key => $value) {
                if ($key == 'responsible_id' || $key == 'sales_stage_id' || $key == 'client') {
                    continue;
                }
                if ($deal->isDirty($key) && auth()->user()) {
                    DealEvent::create([
                        'deal_id' => $deal->id,
                        'initiator_id' => Auth::user()->id,
                        'type' => DealEventTypeEnum::CHANGE_FIELD->value,
                        'changed_field' => trans('deals.'.$key),
                        'old_value' => $deal->getOriginal($key),
                        'new_value' => $deal->{$key},
                    ]);
                }
            }
        }
    }

    public function deleting(Deal $deal): void
    {
        $deal->activities()->delete();
    }
}
