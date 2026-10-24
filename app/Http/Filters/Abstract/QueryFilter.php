<?php

namespace App\Http\Filters\Abstract;

use App\Models\UserField;
use App\Models\UserFieldValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use ReflectionMethod;

abstract class QueryFilter
{
    protected Builder $builder;

    public function __construct(protected Request $request) {}

    public function apply(Builder $builder): void
    {
        $this->builder = $builder;

        foreach ($this->fields() as $method => $value) {
            if ($value === null) {
                continue;
            }

            if (! is_string($method) || ! is_scalar($value)) {
                Log::warning('Invalid filter parameter', ['model' => get_class($builder->getModel())]);

                continue;
            }

            if ($this->isNamedFilter($method)) {
                $this->{$method}($value);
                Log::debug('Named filter applied', ['model' => get_class($builder->getModel()), 'filter' => $method]);
            } elseif (! $this->userFieldFilter($method, $value)) {
                Log::warning('Unknown filter parameter', ['model' => get_class($builder->getModel()), 'filter' => $method]);
            }
        }

        $orderBy = $this->request->get('order_by');
        if (is_string($orderBy) && $orderBy !== '') {
            $this->applySorting($orderBy);
        }

        $model = $this->builder->getModel();
        $this->builder->orderBy($model->getTable().'.id', 'desc');
        Log::debug('Query filter applied', ['model' => get_class($model)]);
    }

    protected function fields(): array
    {
        $requestData = $this->request->all();
        unset($requestData['order_by'], $requestData['order_direction'], $requestData['page'], $requestData['limit']);

        array_walk_recursive($requestData, function (&$value) {
            $value = is_string($value) ? trim($value) : $value;
        });

        return $requestData;
    }

    protected function userFieldFilter(string $code, $value): bool
    {
        $operator = '=';
        if (Str::endsWith($code, '_from')) {
            $code = Str::beforeLast($code, '_from');
            $operator = '>=';
        } elseif (Str::endsWith($code, '_to')) {
            $code = Str::beforeLast($code, '_to');
            $operator = '<=';
        }

        $userField = UserField::query()
            ->where('code', $code)
            ->where('entity', $this->builder->getModel()->getTable())
            ->first();

        if (! $userField) {
            return false;
        }

        $value = $userField->type === 'integer' ? (int) $value : $value;
        $matchingEntityIds = UserFieldValue::query()
            ->select('entity_id')
            ->where('field_id', $userField->id)
            ->where('value', $operator, $value);

        $this->builder->whereIn($this->builder->getModel()->getTable().'.id', $matchingEntityIds);

        Log::debug('Custom field filter applied', ['model' => get_class($this->builder->getModel()), 'field_id' => $userField->id]);

        return true;
    }

    private function isNamedFilter(string $method): bool
    {
        if (! method_exists($this, $method)) {
            return false;
        }

        $reflection = new ReflectionMethod($this, $method);

        return $reflection->isPublic()
            && $reflection->getDeclaringClass()->getName() !== self::class
            && ! $reflection->isConstructor();
    }

    private function applySorting(string $orderBy): void
    {
        $model = $this->builder->getModel();
        $direction = strtolower((string) $this->request->get('order_direction', 'asc'));

        if (! in_array($direction, ['asc', 'desc'], true)) {
            Log::warning('Invalid filter sort direction', ['model' => get_class($model)]);
            $direction = 'asc';
        }

        $userField = UserField::query()
            ->select('id')
            ->where('code', $orderBy)
            ->where('entity', $model->getTable())
            ->first();

        if ($userField && method_exists($model, 'userFieldValues')) {
            $table = $model->getTable();
            $this->builder
                ->select("$table.*")
                ->with('userFieldValues')
                ->leftJoin('user_field_values', function (JoinClause $join) use ($userField, $table) {
                    $join->on('user_field_values.entity_id', '=', "$table.id")
                        ->where('user_field_values.field_id', '=', $userField->id);
                })
                ->orderBy('user_field_values.value', $direction);

            Log::debug('Custom field sorting applied', ['model' => get_class($model), 'field_id' => $userField->id, 'direction' => $direction]);

            return;
        }

        $relationFields = [
            'who_changed', 'responsible', 'creator', 'sales_stage',
            'sales_funnel', 'deal', 'role', 'division',
        ];
        if (in_array($orderBy, $relationFields, true)) {
            $orderBy .= '_id';
        }

        $allowedColumns = array_merge($model->getFillable(), ['id', 'created_at', 'updated_at']);
        if (! in_array($orderBy, $allowedColumns, true)) {
            Log::warning('Invalid filter sort column', ['model' => get_class($model)]);

            return;
        }

        $this->builder->orderBy($model->getTable().'.'.$orderBy, $direction);
        Log::debug('Column sorting applied', ['model' => get_class($model), 'column' => $orderBy, 'direction' => $direction]);
    }
}
