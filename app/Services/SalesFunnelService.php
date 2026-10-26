<?php

namespace App\Services;

use App\Enums\SalesStageEnum;
use App\Enums\SectionFieldEntityEnum;
use App\Http\Filters\DealFilter;
use App\Http\Filters\SalesFunnelFilter;
use App\Models\SalesFunnel;
use App\Models\SalesStage;
use App\Models\Section;
use App\Models\SectionField;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class SalesFunnelService
{
    public function __construct(private SalesStageService $salesStageService) {}

    public function getSalesFunnels(SalesFunnelFilter $filter, DealFilter $dealFilter, array $queryParams): LengthAwarePaginator
    {
        $limit = $queryParams['limit'] ?? config('constants.paginator.limit');
        $salesFunnels = SalesFunnel::filter($filter)->orderBy('position')->paginate($limit);

        foreach ($salesFunnels as $salesFunnel) {
            $this->getMetricsFromAllStages($salesFunnel, $dealFilter);
        }

        return $salesFunnels;
    }

    public function getMetricsFromAllStages(SalesFunnel $salesFunnel, DealFilter $dealFilter): SalesFunnel
    {
        foreach ($salesFunnel->salesStages as $stage) {
            $deals = $stage->deals()->where(function ($query) {
                $query->where('creator_id', auth()->id())
                    ->orWhere('responsible_id', auth()->id());
            })->filter($dealFilter)->get();

            $stage->metrics = [
                'count' => $deals->count(),
                'total_amount' => $deals->sum('amount'),
            ];
        }

        return $salesFunnel;
    }

    public function createSalesFunnel(array $salesFunnelData): SalesFunnel
    {

        if (Auth::user()->role->title != 'Руководитель') {
            throw new AccessDeniedHttpException;
        }

        if (! $this->checkAllStages($salesFunnelData['stages'])) {
            throw new BadRequestHttpException;
        }

        $salesFunnel = DB::transaction(function () use ($salesFunnelData) {
            $salesFunnel = SalesFunnel::create([
                'title' => $salesFunnelData['title'] ?? config('constants.funnels.default_title'),
                'position' => $salesFunnelData['position'] ?? 1,
            ]);

            foreach ($salesFunnelData['stages'] as $salesStageData) {
                $this->salesStageService->createSalesStage($salesStageData, $salesFunnel->id);
            }

            return $salesFunnel;
        });

        return $salesFunnel;
    }

    public function updateSalesFunnel(SalesFunnel $salesFunnel, array $salesFunnelData): SalesFunnel
    {
        if (! empty($salesFunnelData['stages']) && ! $this->checkAllStages($salesFunnelData['stages'])) {
            throw new BadRequestHttpException('Должны быть обязательно 1 обычная, 1 успешная и 1 неуспешная стадия');
        }

        $step = 'attributes';

        try {
            DB::transaction(function () use ($salesFunnel, $salesFunnelData, &$step): void {
                $attributes = array_intersect_key($salesFunnelData, array_flip(['title', 'position']));
                if ($attributes !== []) {
                    $salesFunnel->update($attributes);
                }

                if (! empty($salesFunnelData['stages'])) {
                    $step = 'stages';
                    $this->updateStages($salesFunnel, $salesFunnelData['stages']);
                }

                if (array_key_exists('fields', $salesFunnelData)) {
                    $step = 'fields';
                    $this->syncFunnelFields($salesFunnel, $salesFunnelData['fields']);
                }
            });
        } catch (\Throwable $exception) {
            Log::error('sales_funnel.update.failed', ['funnel_id' => $salesFunnel->id, 'step' => $step, 'exception' => $exception::class]);
            throw $exception;
        }

        Log::info('sales_funnel.update.completed', ['funnel_id' => $salesFunnel->id]);

        return $salesFunnel;
    }

    private function updateStages(SalesFunnel $salesFunnel, array $stages): void
    {
        $stageIds = [];

        foreach ($stages as $stageData) {
            if (isset($stageData['id'])) {
                $stage = SalesStage::where('sales_funnel_id', $salesFunnel->id)->findOrFail($stageData['id']);
                $this->salesStageService->updateSalesStage($stage, $stageData);
            } else {
                $stage = $this->salesStageService->createSalesStage($stageData, $salesFunnel->id);
            }

            $stageIds[] = $stage->id;
        }

        $this->syncStages($salesFunnel, $stageIds);
        Log::debug('sales_funnel.stages.synced', ['funnel_id' => $salesFunnel->id, 'stage_count' => count($stageIds)]);
    }

    private function syncFunnelFields(SalesFunnel $salesFunnel, array $fieldData): void
    {
        $fields = collect($fieldData);
        $fieldIds = $fields->pluck('field_id')->filter();
        $userFieldIds = $fields->pluck('user_field_id')->filter();

        $fieldsResult = $salesFunnel->fields()->syncWithPivotValues($fieldIds, ['sales_funnel_id' => $salesFunnel->id]);
        $userFieldsResult = $salesFunnel->userFields()->syncWithPivotValues($userFieldIds, ['sales_funnel_id' => $salesFunnel->id]);

        SectionField::whereHas('section', function ($query) use ($salesFunnel) {
            $query->where('sales_funnel_id', $salesFunnel->id)
                ->where('is_customized', false)
                ->where('is_template', true)
                ->where('entity', SectionFieldEntityEnum::DEALS->value);
        })
            ->where(function ($query) use ($fieldsResult, $userFieldsResult) {
                $query->whereIn('field_id', $fieldsResult['detached'])
                    ->orWhereIn('user_field_id', $userFieldsResult['detached']);
            })
            ->delete();

        $this->syncTemplateSections($salesFunnel, $fieldsResult['attached'], $userFieldsResult['attached']);
    }

    private function syncTemplateSections(SalesFunnel $salesFunnel, array $fieldIds, array $userFieldIds): void
    {
        $processed = 0;

        User::query()->chunkById(200, function ($users) use ($salesFunnel, $fieldIds, $userFieldIds, &$processed): void {
            foreach ($users as $user) {
                $section = Section::updateOrCreate([
                    'user_id' => $user->id,
                    'entity' => SectionFieldEntityEnum::DEALS->value,
                    'title' => 'Общее',
                    'position' => 1,
                    'is_template' => true,
                    'main_section' => true,
                    'sales_funnel_id' => $salesFunnel->id,
                ]);

                $lastPosition = $section->fields()->max('position') ?? 0;

                foreach ($fieldIds as $index => $fieldId) {
                    SectionField::updateOrCreate(
                        ['section_id' => $section->id, 'field_id' => $fieldId],
                        ['position' => $lastPosition + $index + 1]
                    );
                }

                foreach ($userFieldIds as $index => $userFieldId) {
                    SectionField::updateOrCreate(
                        ['section_id' => $section->id, 'user_field_id' => $userFieldId],
                        ['position' => $lastPosition + count($fieldIds) + $index + 1]
                    );
                }

                $processed++;
            }
        });

        Log::debug('sales_funnel.template_sections.synced', ['funnel_id' => $salesFunnel->id, 'user_count' => $processed]);
    }

    public function deleteSalesFunnel(SalesFunnel $salesFunnel): void
    {
        if (SalesFunnel::count() === 1) {
            throw new AccessDeniedHttpException('Невозможно удалить направление, так как оно является последним');
        }

        if (Auth::user()->role->title != 'Руководитель') {
            throw new AccessDeniedHttpException('Удалять направления может только администратор');
        }

        if (empty($salesFunnel->deals)) {
            throw new AccessDeniedHttpException('Невозможно удалить направление: есть сделки');
        }

        $salesFunnel->delete();
    }

    private function checkAllStages(array $stagesData): bool
    {
        $types = array_column($stagesData, 'type');

        return in_array(SalesStageEnum::DEFAULT->value, $types) &&
                in_array(SalesStageEnum::SUCCESS->value, $types) &&
                in_array(SalesStageEnum::UNSUCCESS->value, $types);
    }

    /**
     * @param array @arrayStagesIds
     * @return SalesFunnel $salesFunnel
     */
    private function syncStages(SalesFunnel $salesFunnel, array $arrayStagesIds): SalesFunnel
    {
        foreach ($salesFunnel->salesStages as $stage) {
            if (! in_array($stage->id, $arrayStagesIds)) {
                $stage->delete();
            }
        }

        $salesFunnel->refresh();

        return $salesFunnel;
    }
}
