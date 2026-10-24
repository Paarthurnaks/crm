<?php

namespace App\Services;

use App\Enums\EntityTypeEnum;
use App\Enums\PermissionEntityEnum;
use App\Enums\PermissionTypeEnum;
use App\Http\Resources\UserResource;
use App\Models\Deal;
use App\Models\Report;
use App\Models\ReportField;
use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Auth\Access\AuthorizationException;

class ReportService
{
    public function __construct(private ReportFieldService $reportFieldService) {}

    public function getReports(array $queryParams): LengthAwarePaginator
    {
        $user = auth()->user();
        $limit = $queryParams['limit'] ?? config('constants.paginator.limit');

        if ($user->hasAccess(PermissionEntityEnum::ANALYTICS->value, 'view', PermissionTypeEnum::ALL->value)) {
            return Report::whereHas('userAccess', function ($query) {
                $query->where('user_id', Auth::id());
            })->orWhereDoesntHave('userAccess')
                ->orWhere('author_id', Auth::id())
                ->paginate($limit);
        }

        return new LengthAwarePaginator([], 0, $limit);
    }

    public function createReport(array $reportData)
    {
        $report = Report::create([
            'title' => $reportData['title'],
            'description' => $reportData['description'] ?? null,
            'period_from' => $reportData['period_from'] ?? Carbon::minValue()->format('Y-m-d H:i:s'),
            'period_to' => $reportData['period_to'] ?? Carbon::maxValue()->format('Y-m-d H:i:s'),
            'filter' => $reportData['filter'],
            'author_id' => Auth::user()->id,
        ]);

        foreach ($reportData['report_fields'] as $reportFieldData) {
            $this->reportFieldService->createReportField($reportFieldData, $report->id);
        }

        if (! empty($reportData['user_access'])) {
            $report->userAccess()->attach($reportData['user_access']);
        }

        $report->deals = $this->getDealsByFilter($report->filter, []);

        return $report;
    }

    public function updateReport(Report $report, array $reportData)
    {
        $report = DB::transaction(function () use ($report, $reportData) {
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

    public function deleteReport(Report $report): void
    {
        if (auth()->user()->hasAccess(EntityTypeEnum::ANALYTICS->value, 'delete', PermissionTypeEnum::ALL->value)) {
            $report->delete();
        } else {
            throw new AuthorizationException('Недостаточно прав');
        }
    }

    public function setReportFieldsValues(
        Report $report,
        $entityObj,
    ): void {
        foreach ($report->fields as $reportField) {
            if ($reportField->userField) {
                $reportField->value = $entityObj->getCustomFieldValue($reportField->userField);
            } elseif ($reportField->field) {
                if ($reportField->field->code == 'responsible' || $reportField->field->code == 'creator') {
                    $user = $entityObj->{$reportField->field->code};
                    $reportField->model = UserResource::make($user);
                    $reportField->value = $user->name.' '.$user->surname;
                } elseif ($reportField->field->code == 'sales_stage' || $reportField->field->code == 'sales_funnel') {
                    $camelCaseCode = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $reportField->field->code))));
                    $obj = $entityObj->{$camelCaseCode};
                    $reportField->model = $obj;
                    $reportField->value = $obj->title;
                } else {
                    $reportField->value = $entityObj->{$reportField->field->code};
                }
            }
        }
    }

    public function getDealsByFilter(array $filters, array $queryParams)
    {
        $limit = $queryParams['limit'] ?? config('constants.paginator.limit');
        $query = Deal::query();

        $this->filterQuery($query, $filters);

        return $query->paginate($limit);
    }

    public function getDealsByFilterWithoutPaginate(array $filters, array $queryParams)
    {
        $query = Deal::query();

        $this->filterQuery($query, $filters);

        return $query->get();
    }

    public function filterQuery(Builder $query, array $filters)
    {
        foreach ($filters as $filter) {
            if (in_array($filter['key'], ['responsible', 'creator', 'sales_stage', 'sales_funnel'])) {
                $filter['key'] = $filter['key'].'_id';
            }
            if ($filter['condition'] == 'Все') {
                $query->where(function ($query) use ($filter) {
                    $this->filterQuery($query, $filter['value']);
                });
            } elseif ($filter['condition'] == 'Любой') {
                $query->orWhere(function ($query) use ($filter) {
                    $this->filterQuery($query, $filter['value']);
                });
            } elseif ($filter['condition'] == 'Равно') {
                $query->where($filter['key'], '=', $filter['value']);
            } elseif ($filter['condition'] == 'Не равно') {
                $query->where($filter['key'], '!=', $filter['value']);
            } elseif ($filter['condition'] == 'Больше или равно') {
                $query->where($filter['key'], '>=', $filter['value']);
            } elseif ($filter['condition'] == 'Меньше или равно') {
                $query->where($filter['key'], '<=', $filter['value']);
            } elseif ($filter['condition'] == 'Больше') {
                $query->where($filter['key'], '>', $filter['value']);
            } elseif ($filter['condition'] == 'Меньше') {
                $query->where($filter['key'], '<', $filter['value']);
            } elseif ($filter['condition'] == 'Начинается с') {
                $query->where($filter['key'], 'LIKE', $filter['value'].'%');
            } elseif ($filter['condition'] == 'Содержит') {
                $query->where($filter['key'], 'LIKE', '%'.$filter['value'].'%');
            } elseif ($filter['condition'] == 'Не содержит') {
                $query->whereNot($filter['key'], 'LIKE', '%'.$filter['value'].'%');
            }
        }
    }

    /**
     * @param array @arrayFieldsIds
     * @return Section $section
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
