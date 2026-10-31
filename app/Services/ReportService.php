<?php

namespace App\Services;

use App\Enums\EntityTypeEnum;
use App\Enums\PermissionEntityEnum;
use App\Enums\PermissionTypeEnum;
use App\Http\Resources\UserResource;
use App\Models\Deal;
use App\Models\Report;
use App\Models\ReportField;
use App\Models\RoleUserField;
use App\Models\User;
use App\Enums\DefaultRolesEnum;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReportService
{
    public function __construct(private ReportFieldService $reportFieldService) {}

    public function getReports(array $queryParams, User $actor): LengthAwarePaginator
    {
        $limit = $queryParams['limit'] ?? config('constants.paginator.limit');

        if ($actor->hasAccess(PermissionEntityEnum::ANALYTICS->value, 'view', PermissionTypeEnum::ALL->value)) {
            return Report::whereHas('userAccess', function ($query) use ($actor) {
                $query->where('user_id', $actor->id);
            })->orWhereDoesntHave('userAccess')
                ->orWhere('author_id', $actor->id)
                ->paginate($limit);
        }

        return new LengthAwarePaginator([], 0, $limit);
    }

    public function createReport(array $reportData, User $actor): Report
    {
        $report = DB::transaction(function () use ($reportData, $actor): Report {
            $report = Report::create([
                'title' => $reportData['title'],
                'description' => $reportData['description'] ?? null,
                'period_from' => $reportData['period_from'] ?? Carbon::minValue()->format('Y-m-d H:i:s'),
                'period_to' => $reportData['period_to'] ?? Carbon::maxValue()->format('Y-m-d H:i:s'),
                'filter' => $reportData['filter'],
                'author_id' => $actor->id,
            ]);

            foreach ($reportData['report_fields'] as $reportFieldData) {
                $this->reportFieldService->createReportField($reportFieldData, $report->id);
            }

            if (! empty($reportData['user_access'])) {
                $report->userAccess()->attach($reportData['user_access']);
            }

            return $report;
        });

        $report->deals = $this->getDealsByFilter($report->filter, []);

        return $report;
    }

    public function updateReport(Report $report, array $reportData): Report
    {
        $report = DB::transaction(function () use ($report, $reportData) {
            $reportFields = [];

            if (isset($reportData['report_fields'])) {
                $reportFields = $reportData['report_fields'];
                unset($reportData['report_fields']);
            }
            if (isset($reportData['user_access'])) {
                $report->userAccess()->detach();
                $report->userAccess()->attach($reportData['user_access']);
                unset($reportData['user_access']);
            }
            $report->update($reportData);
            $arrayFieldsIds = [];
            if ($reportFields) {
                foreach ($reportFields as $reportFieldData) {
                    if (array_key_exists('id', $reportFieldData)) {
                        $arrayFieldsIds[] = $reportFieldData['id'];
                        $field = ReportField::find($reportFieldData['id']);
                        if ($field) {
                            $this->reportFieldService->updateReportField($field, $reportFieldData);
                        }
                    } else {
                        $field = $this->reportFieldService->createReportField($reportFieldData, $report->id);
                        $arrayFieldsIds[] = $field->id;
                    }
                }
                $this->syncFields($report, $arrayFieldsIds);
            }

            return $report;
        });

        $report->deals = $this->getDealsByFilter($report->filter, []);

        return $report;
    }

    public function deleteReport(Report $report, User $actor): void
    {
        if ($actor->hasAccess(EntityTypeEnum::ANALYTICS->value, 'delete', PermissionTypeEnum::ALL->value)) {
            $report->delete();
        } else {
            throw new AuthorizationException('Недостаточно прав');
        }
    }

    public function loadVisibleFields(Report $report, User $actor): void
    {
        if ($actor->role?->original_title === DefaultRolesEnum::SUPER_ADMIN->value) {
            $report->load('fields.field', 'fields.userField');

            return;
        }

        $userFieldIds = RoleUserField::where('role_id', $actor->role_id)->pluck('user_field_id');
        $report->load(['fields' => function ($query) use ($userFieldIds): void {
            $query->with(['field', 'userField'])
                ->where(function ($visible) use ($userFieldIds): void {
                    $visible->whereIn('user_field_id', $userFieldIds)
                        ->orWhereNull('user_field_id');
                });
        }]);

        Log::debug('report.fields.loaded', ['report_id' => $report->id, 'field_count' => $report->fields->count()]);
    }

    public function prepareReport(Report $report, User $actor, LengthAwarePaginator|EloquentCollection $deals): void
    {
        $this->loadVisibleFields($report, $actor);
        $report->deals = $deals;

        foreach ($deals as $deal) {
            $this->setReportFieldsValues($report, $deal);
        }
    }

    public function setReportFieldsValues(
        Report $report,
        Deal $entityObj,
    ): void {
        foreach ($report->fields as $reportField) {
            if ($reportField->userField) {
                $reportField->value = $this->fieldValueForDeal($reportField, $entityObj);
            } elseif ($reportField->field) {
                if ($reportField->field->code == 'responsible' || $reportField->field->code == 'creator') {
                    $user = $this->fieldValueForDeal($reportField, $entityObj);
                    $reportField->model = UserResource::make($user);
                    $reportField->value = $user->name.' '.$user->surname;
                } elseif ($reportField->field->code == 'sales_stage' || $reportField->field->code == 'sales_funnel') {
                    $camelCaseCode = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $reportField->field->code))));
                    $obj = $entityObj->{$camelCaseCode};
                    $reportField->model = $obj;
                    $reportField->value = $obj->title;
                } else {
                    $reportField->value = $this->fieldValueForDeal($reportField, $entityObj);
                }
            }
        }
    }

    private function fieldValueForDeal(ReportField $reportField, Deal $deal): mixed
    {
        if ($reportField->userField) {
            return $deal->getCustomFieldValue($reportField->userField);
        }

        return $deal->{$reportField->field->code};
    }

    public function exportRows(Report $report, EloquentCollection $deals): array
    {
        $rows = [];
        foreach ($deals as $deal) {
            $row = [];
            foreach ($report->fields as $reportField) {
                if ($reportField->field) {
                    $label = $reportField->field->name;
                } elseif ($reportField->userField) {
                    $label = $reportField->userField->name;
                } else {
                    continue;
                }

                $value = $this->fieldValueForDeal($reportField, $deal);
                $row[$label] = is_scalar($value) || $value === null
                    ? $value
                    : json_encode($value, JSON_UNESCAPED_UNICODE);
            }
            $rows[] = $row;
        }

        Log::debug('report.export.rows_prepared', ['report_id' => $report->id, 'row_count' => count($rows)]);

        return $rows;
    }

    public function getDealsByFilter(array $filters, array $queryParams): LengthAwarePaginator
    {
        $limit = $queryParams['limit'] ?? config('constants.paginator.limit');

        try {
            return $this->filteredDealsQuery($filters)->paginate($limit);
        } catch (\Throwable $exception) {
            Log::error('report.deal_filter.failed', ['step' => 'paginate', 'filter_count' => count($filters), 'exception' => $exception::class]);
            throw $exception;
        }
    }

    public function getFilteredDeals(array $filters): EloquentCollection
    {
        try {
            return $this->filteredDealsQuery($filters)->get();
        } catch (\Throwable $exception) {
            Log::error('report.deal_filter.failed', ['step' => 'export', 'filter_count' => count($filters), 'exception' => $exception::class]);
            throw $exception;
        }
    }

    private function filteredDealsQuery(array $filters): Builder
    {
        $query = Deal::query();
        $this->filterQuery($query, $filters);

        return $query;
    }

    public function filterQuery(Builder $query, array $filters): void
    {
        foreach ($filters as $filter) {
            $condition = $filter['condition'];

            if ($condition === 'Все' || $condition === 'Любой') {
                $this->applyFilterGroup($query, $condition, $filter['value']);

                continue;
            }

            $this->applyFilterComparison($query, $this->normalizeFilterKey($filter['key']), $condition, $filter['value']);
        }

        Log::debug('report.filter.applied', ['filter_count' => count($filters)]);
    }

    private function normalizeFilterKey(string $key): string
    {
        return in_array($key, ['responsible', 'creator', 'sales_stage', 'sales_funnel'], true)
            ? $key.'_id'
            : $key;
    }

    private function applyFilterGroup(Builder $query, string $condition, array $children): void
    {
        $method = $condition === 'Все' ? 'where' : 'orWhere';

        $query->{$method}(function ($nested) use ($children): void {
            $this->filterQuery($nested, $children);
        });
    }

    private function applyFilterComparison(Builder $query, string $key, string $condition, mixed $value): void
    {
        $operators = [
            'Равно' => '=',
            'Не равно' => '!=',
            'Больше или равно' => '>=',
            'Меньше или равно' => '<=',
            'Больше' => '>',
            'Меньше' => '<',
        ];

        if (isset($operators[$condition])) {
            $query->where($key, $operators[$condition], $value);

            return;
        }

        if ($condition === 'Начинается с') {
            $query->where($key, 'LIKE', $value.'%');
        } elseif ($condition === 'Содержит') {
            $query->where($key, 'LIKE', '%'.$value.'%');
        } elseif ($condition === 'Не содержит') {
            $query->whereNot($key, 'LIKE', '%'.$value.'%');
        }
    }

    /**
     * @param  array<int, int>  $arrayFieldsIds
     */
    private function syncFields(Report $report, array $arrayFieldsIds): Report
    {
        foreach ($report->fields as $field) {
            if (! in_array($field->id, $arrayFieldsIds)) {
                $field->delete();
            }
        }

        $report->refresh();

        return $report;
    }
}
